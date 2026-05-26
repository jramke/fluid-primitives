import { createMachine } from '@zag-js/core';
import { FormApi as TanStackFormApi } from '@tanstack/form-core';
import * as dom from './form.dom';
import type { FormDirty, FormErrors, FormSchema, FormTouched } from './form.types';
import { ValidationError } from './form.types';
import {
	errorsFromServer,
	formDataToObject,
	getFormDataValue,
	getInputValue,
	objectToFormData,
	prefixFieldName,
	validateWithSchema,
} from './form.utils';

type FormCoreApi = TanStackFormApi<Record<string, unknown>>;

function getDirtyMap(formApi: FormCoreApi): FormDirty {
	const dirty: FormDirty = {};
	for (const [name, meta] of Object.entries(formApi.state.fieldMeta)) {
		if (meta?.isDirty) {
			dirty[name] = true;
		}
	}
	return dirty;
}

function getTouchedMap(formApi: FormCoreApi): FormTouched {
	const touched: FormTouched = {};
	for (const [name, meta] of Object.entries(formApi.state.fieldMeta)) {
		if (meta?.isTouched) {
			touched[name] = true;
		}
	}
	return touched;
}

function syncContextFromFormApi(context: any, formApi: FormCoreApi) {
	context.set('values', objectToFormData(formApi.state.values as Record<string, unknown>));
	context.set('dirty', getDirtyMap(formApi));
	context.set('touched', getTouchedMap(formApi));
}

export const machine = createMachine<FormSchema>({
	// debug: true,
	initialState() {
		return 'ready';
	},

	context({ bindable }) {
		return {
			values: bindable(() => ({ defaultValue: new FormData() })),
			initialValues: bindable(() => ({ defaultValue: new FormData() })),
			errors: bindable(() => ({ defaultValue: {} as FormErrors })),
			dirty: bindable(() => ({ defaultValue: {} as FormDirty })),
			touched: bindable(() => ({ defaultValue: {} as FormTouched })),
			formApi: bindable(() => ({ defaultValue: null as FormCoreApi | null })),
		};
	},

	states: {
		ready: {},
		invalid: {},
		submitting: {},
		success: {},
		error: {},
	},

	on: {
		SUBMIT: { target: 'submitting', actions: ['validateAll'] },
		VALIDATE: { actions: ['validateAll'] },
		VALIDATE_FIELD: { actions: ['validateField'] },
		INVALID: { target: 'invalid' },
		INPUT: { actions: ['handleInput'] },
		BLUR: { actions: ['handleBlur'] },
		RESET: { target: 'ready', actions: ['resetForm'] },
		ERROR: { target: 'error' },
		SUCCESS: { target: 'success', actions: ['clearErrors'] },
	},

	entry: ['initializeFormApi', 'setupFormListeners'],

	implementations: {
		actions: {
			initializeFormApi({ context, scope }) {
				const form = dom.getFormEl(scope);
				const initialValues = form ? new FormData(form) : new FormData();
				context.set('values', initialValues);
				context.set('initialValues', initialValues);

				const formApi = new TanStackFormApi<Record<string, unknown>>({
					defaultValues: formDataToObject(initialValues),
				});
				context.set('formApi', formApi);
				syncContextFromFormApi(context, formApi);

				formApi.store.subscribe(() => {
					syncContextFromFormApi(context, formApi);
				});
			},

			validateAll({ context, send, prop, state, action, event, scope }) {
				const submitting = state.matches('submitting');
				const schema = prop('schema');
				const formApi = context.get('formApi');
				const formEl = dom.getFormEl(scope);

				if (formApi && formEl) {
					formApi.baseStore.setState(prev => ({
						...prev,
						values: formDataToObject(new FormData(formEl)),
					}));
					syncContextFromFormApi(context, formApi);
				}

				if (schema) {
					const formData = context.get('values');
					const errs = validateWithSchema(schema, formData);
					context.set('errors', errs);

					if (Object.keys(errs).length > 0) {
						send({ type: 'INVALID' });
						if (submitting) {
							action(['focusFirstInvalid']);
						}
						return;
					}
				}

				if (!submitting) {
					state.set('ready');
					return;
				}

				const onSubmit = prop('onSubmit');
				if (!onSubmit) {
					send({ type: 'SUCCESS' });
					return;
				}

				// Async IIFE to handle both sync and async onSubmit with single try/catch
				(async () => {
					try {
						const result = await onSubmit({
							formData: context.get('values'),
							api: event.detail.api,
							event: event.detail.event,
							post: async (url: string, data: FormData): Promise<Response> => {
								const prefixedData = new FormData();
								const objectName = prop('objectName');
								const formEl = dom.getFormEl(scope);
								const prefix = formEl?.getAttribute('data-field-name-prefix') || '';

								for (const [key, value] of data.entries()) {
									if (key.includes(prefix)) {
										prefixedData.append(key, value);
										continue;
									}
									prefixedData.append(
										prefixFieldName(key, prefix, objectName),
										value
									);
								}

								const response = await fetch(url, {
									method: 'POST',
									body: prefixedData,
								});

								if (response.status === 422) {
									const errors = await response.json();
									const formErrors = errorsFromServer(errors, objectName, data);
									throw new ValidationError(formErrors);
								}

								return response;
							},
						});

						if (result) {
							send({ type: 'SUCCESS' });
						} else {
							send({ type: 'ERROR' });
						}
					} catch (error) {
						if (error instanceof ValidationError) {
							context.set('errors', error.errors);
							send({ type: 'INVALID' });
							action(['focusFirstInvalid']);
							return;
						}
						send({ type: 'ERROR' });
					}
				})();
			},

			validateField({ context, prop, event }) {
				const schema = prop('schema');
				if (!schema) return;

				let fieldName = event.detail?.fieldName;
				if (!fieldName) return;

				const formData = context.get('values');
				const currentErrors = context.get('errors');
				const existingError = currentErrors[fieldName];

				const currentValue = getFormDataValue(formData, fieldName);

				// Skip validation if the value hasn't changed from when the error was triggered
				// This preserves server-side validation errors until the user actually changes the value
				if (existingError) {
					const errorValue = existingError.value;
					if (JSON.stringify(currentValue) === JSON.stringify(errorValue)) {
						return;
					}
				}

				const allErrors = validateWithSchema(schema, formData);

				// Update only errors for the specific field
				const updatedErrors = { ...currentErrors };

				const fieldNameWithoutSuffix = fieldName.replace(/\[\]$/, '');

				if (allErrors[fieldNameWithoutSuffix]) {
					updatedErrors[fieldNameWithoutSuffix] = allErrors[fieldNameWithoutSuffix];
				} else {
					delete updatedErrors[fieldNameWithoutSuffix];
				}

				context.set('errors', updatedErrors);
			},

			handleInput({ context, event, send }) {
				const e = event as any;
				const target = e?.detail?.target ?? e?.target ?? e?.currentTarget;
				const name: string | undefined = target?.name;
				if (!name) return;
				const formApi = context.get('formApi');

				const value = e?.detail?.value ?? getInputValue(target);
				// console.log('input', value);

				if (formApi) {
					formApi.setFieldValue(name, value as any, { dontValidate: true });
					syncContextFromFormApi(context, formApi);
				} else {
					const values = context.get('values');
					values.set(name, value);
					context.set('values', values);
				}

				// Revalidate if field has errors OR if any validation has occurred
				// This ensures errors clear immediately when fixed
				const currentErrors = context.get('errors');
				const touched = context.get('touched');
				const hasFieldError = !!currentErrors[name];
				const fieldTouched = !!touched[name];

				if (hasFieldError || fieldTouched) {
					send({ type: 'VALIDATE_FIELD', detail: { fieldName: name } });
				}
			},

			handleBlur({ context, send, prop, event }) {
				const e = event as any;
				const target = e?.detail?.target;
				let name: string | undefined = target?.name;
				if (!name) {
					name =
						target?.closest('[data-scope="field"]')?.getAttribute('name') || undefined;
				}

				if (!name) return;
				const formApi = context.get('formApi');

				// Mark field as touched
				if (formApi) {
					formApi.setFieldMeta(name, prev => ({
						...(prev ?? {}),
						isTouched: true,
						isBlurred: true,
					}));
					syncContextFromFormApi(context, formApi);
				} else {
					const touched = { ...context.get('touched') };
					touched[name] = true;
					context.set('touched', touched);
				}

				// Get current form values from DOM to ensure we have latest
				const form = target.form as HTMLFormElement | null;
				if (form) {
					const values = new FormData(form);
					context.set('values', values);
					if (formApi) {
						formApi.baseStore.setState(prev => ({
							...prev,
							values: formDataToObject(values),
						}));
						syncContextFromFormApi(context, formApi);
					}
				}

				// Validate the field on blur
				const schema = prop('schema');
				if (schema) {
					send({ type: 'VALIDATE_FIELD', detail: { fieldName: name } });
				}
			},

			resetForm({ context, scope, event }) {
				const form = dom.getFormEl(scope);
				const formApi = context.get('formApi');

				if (form && !event?.detail?.omitManualReset) {
					form.reset();
				}

				const initial = form ? new FormData(form) : context.get('initialValues');
				const initialObj = formDataToObject(initial);

				context.set('values', initial);
				context.set('initialValues', initial);
				context.set('errors', {});
				context.set('touched', {});
				context.set('dirty', {});
				formApi?.reset(initialObj, { keepDefaultValues: false });
			},

			focusFirstInvalid({ context, scope }) {
				const errors = context.get('errors');
				const firstKey = Object.keys(errors)[0];
				if (!firstKey) return;

				const form = dom.getFormEl(scope);
				if (!form) return;

				const invalidEl = form.querySelector(
					`[name="${CSS.escape(firstKey)}"]`
				) as HTMLElement | null;
				invalidEl?.focus();
				if (
					invalidEl instanceof HTMLInputElement ||
					invalidEl instanceof HTMLTextAreaElement
				) {
					invalidEl.select();
				}
			},

			setupFormListeners({ scope, send }) {
				const form = dom.getFormEl(scope);
				if (!form) return;

				// We need to listen to the change event to capture changes in select elements or checkboxes
				// The input event does not always fire for these elements in all browsers
				// Also zag-js spreadProps maps onChange to onInput so we need to manually listen to change events here
				form.addEventListener(
					'change',
					event => {
						send({ type: 'INPUT', detail: { target: event.target } });
					},
					true
				);
			},

			clearErrors({ context }) {
				context.set('errors', {});
				context.set('touched', {});
			},
		},
	},
});
