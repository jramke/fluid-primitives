import { createMachine } from '@zag-js/core';
import { getClosestFieldName, isFocusMovingWithinSameField } from '../../Field/src/field.dom';
import * as dom from './form.dom';
import type { FormDirty, FormErrors, FormSchema, FormTouched } from './form.types';
import { ValidationError } from './form.types';
import {
	errorsFromServer,
	getErrorForValue,
	getErrorsForCurrentValues,
	getFieldElement,
	getFieldValue,
	getInputValue,
	normalizeFieldName,
	prefixFieldName,
	validateWithValidation,
	withCurrentValues,
} from './form.utils';

export const machine = createMachine<FormSchema>({
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
			errorText: bindable(() => ({ defaultValue: null as string | null })),
			successText: bindable(() => ({ defaultValue: null as string | null })),
		};
	},

	refs() {
		return {
			submitCount: 0,
			serverErrors: {} as FormErrors,
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
		SUBMIT: { target: 'submitting', actions: ['clearStatusText', 'validateAll'] },
		VALIDATE: { actions: ['validateAll'] },
		VALIDATE_FIELD: { actions: ['validateField'] },
		INVALID: { target: 'invalid' },
		INPUT: { actions: ['handleInput'] },
		FIELD_FOCUS: { actions: ['handleFocus'] },
		FIELD_BLUR: { actions: ['handleBlur'] },
		RESET: { target: 'ready', actions: ['resetForm'] },
		ERROR: { target: 'error' },
		SUCCESS: { target: 'success', actions: ['clearErrors'] },
		SET_ERROR_TEXT: { actions: ['setErrorText'] },
		SET_SUCCESS_TEXT: { actions: ['setSuccessText'] },
		CLEAR_STATUS_TEXT: { actions: ['clearStatusText'] },
	},

	entry: ['setupFormListeners', 'getInitialValues'],

	implementations: {
		actions: {
			validateAll({ context, send, prop, state, action, event, scope, refs }) {
				const submitting = state.matches('submitting');
				const validation = prop('validation');
				const formData = context.get('values');

				if (submitting) {
					const submitCount = refs.get('submitCount');
					refs.set('submitCount', submitCount + 1);
				}

				const cachedServerErrors = getErrorsForCurrentValues(
					refs.get('serverErrors'),
					formData
				);
				let errs = cachedServerErrors;

				if (validation) {
					errs = {
						...cachedServerErrors,
						...validateWithValidation(validation, formData),
					};
				}

				context.set('errors', errs);

				if (Object.keys(errs).length > 0) {
					send({ type: 'INVALID' });
					if (submitting) {
						action(['focusFirstInvalid']);
					}
					return;
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
					const invalidateWithErrors = (errors: FormErrors) => {
						const currentErrors = withCurrentValues(errors, context.get('values'));
						refs.set('serverErrors', { ...refs.get('serverErrors'), ...currentErrors });
						context.set('errors', currentErrors);
						send({ type: 'INVALID' });
						action(['focusFirstInvalid']);
					};

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

						if (result === true) {
							send({ type: 'SUCCESS' });
							return;
						}

						if (result === false) {
							send({ type: 'ERROR' });
							return;
						}

						if (isFormErrors(result)) {
							if (Object.keys(result).length === 0) {
								send({ type: 'SUCCESS' });
								return;
							}

							invalidateWithErrors(result);
							return;
						}

						send({ type: 'ERROR' });
					} catch (error) {
						if (error instanceof ValidationError) {
							invalidateWithErrors(error.errors);
							return;
						}
						send({ type: 'ERROR' });
					}
				})();
			},

			validateField({ context, prop, event, refs }) {
				const fieldName = event.detail?.fieldName;
				if (!fieldName) return;
				const normalizedFieldName = normalizeFieldName(fieldName);

				const formData = context.get('values');
				const currentErrors = context.get('errors');
				const currentValue = getFieldValue(formData, fieldName);
				const updatedErrors = { ...currentErrors };
				const serverError = getErrorForValue(
					refs.get('serverErrors'),
					normalizedFieldName,
					currentValue
				);

				if (serverError) {
					updatedErrors[normalizedFieldName] = serverError;
					context.set('errors', updatedErrors);
					return;
				}

				delete updatedErrors[normalizedFieldName];

				const validation = prop('validation');
				if (!validation) {
					context.set('errors', updatedErrors);
					return;
				}

				const allErrors = validateWithValidation(validation, formData, normalizedFieldName);

				// Update only errors for the specific field
				if (allErrors[normalizedFieldName]) {
					updatedErrors[normalizedFieldName] = allErrors[normalizedFieldName];
				} else {
					delete updatedErrors[normalizedFieldName];
				}

				context.set('errors', updatedErrors);
			},

			handleInput({ context, event, send }) {
				const e = event as any;
				const target = e?.detail?.target ?? e?.target ?? e?.currentTarget;
				const name: string | undefined = target?.name;
				if (!name) return;

				const value = e?.detail?.value ?? getInputValue(target);

				const values = context.get('values');
				values.set(name, value);
				context.set('values', values);

				const initial = context.get('initialValues').get(name);
				const dirty = { ...context.get('dirty') };
				dirty[name] = JSON.stringify(values.get(name)) !== JSON.stringify(initial);
				context.set('dirty', dirty);

				// Revalidate on input if field has errors OR if any validation has occurred
				// This ensures errors clear immediately when fixed
				const currentErrors = context.get('errors');
				const touched = context.get('touched');
				const normalizedName = normalizeFieldName(name);
				const hasFieldError = !!currentErrors[normalizedName];
				const fieldTouched = !!touched[normalizedName];

				if (hasFieldError || fieldTouched) {
					send({ type: 'VALIDATE_FIELD', detail: { fieldName: name } });
				}
			},

			handleFocus({ context, event }) {
				const e = event as any;
				const target = e?.detail?.target ?? e?.target ?? e?.currentTarget;

				const name = getClosestFieldName(target);
				if (!name) return;

				const touched = { ...context.get('touched') };
				touched[normalizeFieldName(name)] = true;
				context.set('touched', touched);
			},

			handleBlur({ context, send, prop, event }) {
				const e = event as any;
				const target = e?.detail?.target;

				const name = getClosestFieldName(target);
				if (!name) return;

				// Get current form values from DOM to ensure we have latest
				const form = target.form as HTMLFormElement | null;
				if (form) {
					const values = new FormData(form);
					context.set('values', values);
				}

				// Validate the field on blur
				if (isFocusMovingWithinSameField(target, e?.detail?.relatedTarget)) return;

				const validation = prop('validation');
				if (validation || context.get('errors')[normalizeFieldName(name)]) {
					send({ type: 'VALIDATE_FIELD', detail: { fieldName: name } });
				}
			},

			resetForm({ context, scope, event, refs }) {
				const form = dom.getFormEl(scope);

				if (form && !event?.detail?.omitManualReset) {
					form.reset();
				}

				const initial = form ? new FormData(form) : context.get('initialValues');

				context.set('values', initial);
				context.set('initialValues', initial);
				context.set('errors', {});
				context.set('errorText', null);
				context.set('successText', null);
				refs.set('serverErrors', {});
				context.set('touched', {});
				context.set('dirty', {});
			},

			setErrorText({ context, event }) {
				context.set('errorText', event.detail?.text ?? null);
			},

			setSuccessText({ context, event }) {
				context.set('successText', event.detail?.text ?? null);
			},

			clearStatusText({ context }) {
				context.set('errorText', null);
				context.set('successText', null);
			},

			focusFirstInvalid({ context, scope }) {
				const errors = context.get('errors');
				const firstKey = Object.keys(errors)[0];
				if (!firstKey) return;

				const form = dom.getFormEl(scope);
				if (!form) return;

				const invalidEl = getFieldElement(form, firstKey);
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

			clearErrors({ context, refs }) {
				context.set('errors', {});
				refs.set('serverErrors', {});
				context.set('touched', {});
			},

			// TODO: maybe this can be moved to the context default value declaration with zag v2
			getInitialValues({ scope, context }) {
				const form = dom.getFormEl(scope);
				if (!form) return;

				context.set('initialValues', new FormData(form));
			},
		},
	},
});

function isFormErrors(result: unknown): result is FormErrors {
	return typeof result === 'object' && result !== null && !Array.isArray(result);
}
