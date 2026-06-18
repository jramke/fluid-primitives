import { createMachine } from '@zag-js/core';
import { debounce } from '@zag-js/utils';
import { getFormMachineFor, type FormMachine } from '../../Form/src/form.registry';
import * as dom from './field.dom';
import type { FieldSchema } from './field.types';
import { getCurrentFieldValue, getDefaultFieldValue, isFieldValueEqual } from './field.utils';

export const machine = createMachine<FieldSchema>({
	initialState() {
		return 'ready';
	},
	context({ bindable, prop }) {
		return {
			invalid: bindable(() => ({ defaultValue: prop('invalid') ?? false })),
			required: bindable(() => ({ defaultValue: prop('required') ?? false })),
			disabled: bindable(() => ({ defaultValue: prop('disabled') ?? false })),
			readOnly: bindable(() => ({ defaultValue: prop('readOnly') ?? false })),
			formMachine: bindable(() => ({
				defaultValue: null as FormMachine | null,
			})),
			describeIds: bindable<string | undefined>(() => ({ defaultValue: undefined })),
			hasDescription: bindable(() => ({ defaultValue: false })),
			value: bindable(() => ({ defaultValue: getDefaultFieldValue(prop('defaultValue')) })),
			initialValue: bindable(() => ({
				defaultValue: getDefaultFieldValue(prop('defaultValue')),
			})),
			errors: bindable(() => ({ defaultValue: [] as string[] })),
			touched: bindable(() => ({ defaultValue: false })),
			dirty: bindable(() => ({ defaultValue: false })),
			blurred: bindable(() => ({ defaultValue: false })),
		};
	},
	entry: [
		'getFormMachine',
		'checkForDescription',
		'syncInitialValueFromDom',
		'determineDescribeIds',
		'updateInvalid',
		'setupFieldListeners',
	],
	states: {
		ready: {},
	},
	on: {
		VALUE_CHANGE: { actions: ['handleValueChange'] },
		FIELD_BLUR: { actions: ['handleBlur'] },
		SET_ERRORS: { actions: ['setErrors', 'updateInvalid', 'determineDescribeIds'] },
		CLEAR_ERRORS: { actions: ['clearErrors', 'updateInvalid', 'determineDescribeIds'] },
		RESET: {
			actions: ['resetField', 'updateInvalid', 'determineDescribeIds'],
		},
		SYNC_FROM_DOM: { actions: ['syncValueFromDom'] },
	},
	watch({ track, context, action }) {
		track([() => context.get('invalid'), () => context.get('hasDescription')], () => {
			action(['determineDescribeIds']);
		});
	},
	implementations: {
		actions: {
			getFormMachine({ context, scope }) {
				if (context.get('formMachine')) return;

				const fieldRootEl = dom.getRootEl(scope);
				if (!fieldRootEl) return;

				const formMachine = getFormMachineFor(fieldRootEl) ?? null;

				if (formMachine) {
					context.set('formMachine', formMachine);
				} else {
					const closestForm = fieldRootEl.closest('form');
					if (!closestForm) return;

					const handler = () => {
						const fs = getFormMachineFor(fieldRootEl) ?? null;
						context.set('formMachine', fs);
						closestForm.removeEventListener(
							'fluid-primitives:form:registered',
							handler
						);
					};
					closestForm.addEventListener('fluid-primitives:form:registered', handler);
				}
			},
			checkForDescription({ context, scope }) {
				const descriptionEl = dom.getDescriptionEl(scope);
				context.set('hasDescription', !!descriptionEl);
			},
			syncInitialValueFromDom({ context, scope, prop }) {
				const currentValue = getCurrentFieldValue(
					scope,
					prop('name'),
					prop('defaultValue')
				);
				context.set('value', currentValue);
				context.set('initialValue', currentValue);
			},
			syncValueFromDom({ context, scope, prop }) {
				context.set(
					'value',
					getCurrentFieldValue(scope, prop('name'), prop('defaultValue'))
				);
			},
			determineDescribeIds({ context, scope }) {
				const ids: string[] = [];
				if (context.get('hasDescription')) {
					ids.push(dom.getDescriptionId(scope));
				}
				if (context.get('invalid')) {
					ids.push(dom.getErrorId(scope));
				}
				const idsStr = ids.join(' ') || undefined;
				context.set('describeIds', idsStr);
			},
			updateInvalid({ context, prop }) {
				context.set(
					'invalid',
					context.get('errors').length > 0 || (prop('invalid') ?? false)
				);
			},
			setErrors({ context, event }) {
				context.set('errors', [...(event.detail?.errors ?? [])]);
			},
			clearErrors({ context }) {
				context.set('errors', []);
			},
			handleValueChange({ context, prop, action }) {
				const previousValue = context.get('value');
				action(['syncValueFromDom']);
				const nextValue = context.get('value');

				if (isFieldValueEqual(previousValue, nextValue)) {
					return;
				}

				context.set('touched', true);
				context.set('dirty', true);

				if (context.get('errors').length > 0) {
					context.get('formMachine')?.send({
						type: 'VALIDATE_FIELD',
						detail: { fieldName: prop('name') },
					});
				}
			},
			handleBlur({ context, prop, event, action }) {
				const target = event.detail?.target as Element | null;
				const relatedTarget = event.detail?.relatedTarget ?? null;

				if (dom.isFocusMovingWithinSameField(target, relatedTarget)) {
					return;
				}

				context.set('touched', true);
				context.set('blurred', true);
				action(['syncValueFromDom']);

				if (context.get('dirty')) {
					context.get('formMachine')?.send({
						type: 'VALIDATE_FIELD',
						detail: { fieldName: prop('name') },
					});
				}
			},
			resetField({ context, scope, prop }) {
				const value = getCurrentFieldValue(scope, prop('name'), prop('defaultValue'));
				context.set('value', value);
				context.set('errors', []);
				context.set('touched', false);
				context.set('dirty', false);
				context.set('blurred', false);
			},
			setupFieldListeners({ scope, context, send }) {
				const rootEl = dom.getRootEl(scope);
				if (!rootEl) return;

				const debounceMs = context.get('formMachine')?.prop('inputDebounceMs') ?? 100;
				const debouncedValueChange =
					debounceMs > 0
						? debounce((target: EventTarget | null) => {
								send({ type: 'VALUE_CHANGE', detail: { target } });
							}, debounceMs)
						: (target: EventTarget | null) => {
								send({ type: 'VALUE_CHANGE', detail: { target } });
							};

				rootEl.addEventListener(
					'input',
					event => {
						debouncedValueChange(event.target);
					},
					true
				);

				rootEl.addEventListener(
					'change',
					event => {
						send({ type: 'VALUE_CHANGE', detail: { target: event.target } });
					},
					true
				);

				rootEl.addEventListener(
					'focusout',
					event => {
						send({
							type: 'FIELD_BLUR',
							detail: {
								target: event.target,
								relatedTarget: event.relatedTarget,
							},
						});
					},
					true
				);
			},
		},
	},
});
