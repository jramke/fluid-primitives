import { createMachine } from '@zag-js/core';
import { getFormMachineFor, type FormMachine } from '../../Form/src/form.registry';
import * as dom from './field.dom';
import type { FieldSchema } from './field.types';

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
				hash: value =>
					JSON.stringify({
						state: value?.state,
						errors: value?.ctx.get('errors'),
					}),
			})),
			describeIds: bindable<string | undefined>(() => ({ defaultValue: undefined })),
			hasDescription: bindable(() => ({ defaultValue: false })),
		};
	},
	entry: ['getFormMachine', 'checkForDescription', 'determineDescribeIds', 'updateInvalid'],
	states: {
		ready: {},
	},
	computed: {
		errors({ context, prop }) {
			const formMachineCtx = context.get('formMachine')?.ctx;
			if (!formMachineCtx) return [] as string[];
			const errs = formMachineCtx.get('errors')?.[prop('name')] ?? [];
			return errs;
		},
	},
	watch({ track, context, computed, action }) {
		track([() => context.hash('formMachine'), () => computed('errors').join('|')], () => {
			action(['updateInvalid']);
		});

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
				const formMachineCtx = context.get('formMachine')?.ctx;

				if (!formMachineCtx) {
					context.set('invalid', prop('invalid') ?? false);
					return;
				}
				const errs = formMachineCtx.get('errors')?.[prop('name')] ?? [];
				context.set('invalid', errs.length > 0);
			},
		},
	},
});
