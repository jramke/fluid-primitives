import { createMachine } from '@zag-js/core';
import { FieldApi as TanStackFieldApi } from '@tanstack/form-core';
import { getFormMachineFor, type FormMachine } from '../../Form/src/form.registry';
import * as dom from './field.dom';
import type { FieldSchema } from './field.types';

function createFieldApi(formMachine: FormMachine | null, fieldName: string) {
	const formApi = formMachine?.context.get('formApi');
	if (!formApi) {
		return { fieldApi: null, unsubscribe: null } as const;
	}

	const fieldApi = new TanStackFieldApi({
		form: formApi,
		name: fieldName as never,
	});

	return {
		fieldApi,
		unsubscribe: fieldApi.mount(),
	} as const;
}

export const machine = createMachine<FieldSchema>({
	// debug: true,
	initialState() {
		return 'ready';
	},
	context({ bindable, prop }) {
		return {
			invalid: bindable(() => ({ defaultValue: prop('invalid') ?? false })),
			required: bindable(() => ({ defaultValue: prop('required') ?? false })),
			disabled: bindable(() => ({ defaultValue: prop('disabled') ?? false })),
			readOnly: bindable(() => ({ defaultValue: prop('readOnly') ?? false })),
			formMachine: bindable(() => ({ defaultValue: null as FormMachine | null })),
			fieldApi: bindable(() => ({
				defaultValue: null as ReturnType<typeof createFieldApi>['fieldApi'],
				hash: value =>
					JSON.stringify({
						errors: value?.state.meta.errors ?? [],
						isTouched: value?.state.meta.isTouched ?? false,
						isDirty: value?.state.meta.isDirty ?? false,
					}),
			})),
			fieldApiUnsubscribe: bindable(() => ({ defaultValue: null as (() => void) | null })),
			describeIds: bindable<string | undefined>(() => ({ defaultValue: undefined })),
			hasDescription: bindable(() => ({ defaultValue: false })),
		};
	},
	entry: [
		'getFormMachine',
		'initializeFieldApi',
		'checkForDescription',
		'determineDescribeIds',
		'updateInvalid',
	],
	exit: ['cleanupFieldApi'],
	states: {
		ready: {},
	},
	on: {
		FORM_API_READY: { actions: ['initializeFieldApi', 'updateInvalid'] },
	},
	computed: {
		errors({ context, prop }) {
			const fieldApi = context.get('fieldApi');
			if (fieldApi) {
				return fieldApi.state.meta.errors.map(error => String(error));
			}

			const formMachineCtx = context.get('formMachine')?.context;
			const fieldError = formMachineCtx?.get('errors')?.[prop('name')];
			return fieldError?.messages ?? [];
		},
	},
	watch({ track, context, computed, action }) {
		track([() => context.hash('fieldApi'), () => computed('errors').join('|')], () => {
			action(['updateInvalid']);
		});

		track([() => context.get('invalid'), () => context.get('hasDescription')], () => {
			action(['determineDescribeIds']);
		});
	},
	implementations: {
		actions: {
			getFormMachine({ context, scope, prop }) {
				if (context.get('formMachine')) return;

				const fieldRootEl = dom.getRootEl(scope);
				if (!fieldRootEl) return;

				const formMachine = getFormMachineFor(fieldRootEl) ?? null;

				if (formMachine) {
					context.set('formMachine', formMachine);
					const { fieldApi, unsubscribe } = createFieldApi(formMachine, prop('name'));
					context.set('fieldApi', fieldApi);
					context.set('fieldApiUnsubscribe', unsubscribe);
				} else {
					const closestForm = fieldRootEl.closest('form');
					if (!closestForm) return;

					const handler = () => {
						const fs = getFormMachineFor(fieldRootEl) ?? null;
						context.set('formMachine', fs);
						const { fieldApi, unsubscribe } = createFieldApi(fs, prop('name'));
						context.set('fieldApi', fieldApi);
						context.set('fieldApiUnsubscribe', unsubscribe);
						closestForm.removeEventListener(
							'fluid-primitives:form:registered',
							handler
						);
					};
					closestForm.addEventListener('fluid-primitives:form:registered', handler);
				}
			},
			initializeFieldApi({ context, prop }) {
				if (context.get('fieldApi')) return;
				const { fieldApi, unsubscribe } = createFieldApi(
					context.get('formMachine'),
					prop('name')
				);
				context.set('fieldApi', fieldApi);
				context.set('fieldApiUnsubscribe', unsubscribe);
			},
			cleanupFieldApi({ context }) {
				context.get('fieldApiUnsubscribe')?.();
				context.set('fieldApiUnsubscribe', null);
				context.set('fieldApi', null);
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
				const fieldApi = context.get('fieldApi');
				if (fieldApi) {
					context.set('invalid', fieldApi.state.meta.errors.length > 0);
					return;
				}

				const formMachineCtx = context.get('formMachine')?.context;

				if (!formMachineCtx) {
					context.set('invalid', prop('invalid') ?? false);
					return;
				}

				const fieldError = formMachineCtx.get('errors')?.[prop('name')];
				const errs = fieldError?.messages ?? [];

				context.set('invalid', errs.length > 0);
			},
		},
	},
});
