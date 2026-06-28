import { createMachine } from '@zag-js/core';
import { nextTick } from '@zag-js/dom-query';
import * as dom from './form.dom';
import {
    distributeFieldErrors,
    getFieldElement,
    getFirstInvalidFieldMachine,
    getFormData,
    getRegisteredFieldMachines,
    hasInvalidFieldMachines,
    resetFieldMachines,
    setFieldMachineErrors,
    syncAllFieldMachines,
} from './form.fields';
import { normalizeFieldName, prefixFieldName } from './form.path';
import type { FormErrors, FormSchema } from './form.types';
import { FormError, ValidationError } from './form.types';
import {
    attachErrorValues,
    filterErrorsForCurrentValues,
    getCurrentErrorForField,
    getFormErrorMessages,
    mapServerErrors,
    validateWithValidation,
} from './form.validation';
import { createFormValues } from './form.values';

export const machine = createMachine<FormSchema>({
    initialState() {
        return 'ready';
    },

    context({ bindable }) {
        return {
            errorText: bindable(() => ({ defaultValue: null as string | null })),
            successText: bindable(() => ({ defaultValue: null as string | null })),
        };
    },

    refs() {
        return {
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
        RESET: { target: 'ready', actions: ['resetForm'] },
        ERROR: { target: 'error' },
        SUCCESS: { target: 'success', actions: ['clearErrors'] },
        SET_ERROR_TEXT: { actions: ['setErrorText'] },
        SET_SUCCESS_TEXT: { actions: ['setSuccessText'] },
        CLEAR_STATUS_TEXT: { actions: ['clearStatusText'] },
    },

    implementations: {
        actions: {
            validateAll({ send, prop, state, action, event, scope, refs }) {
                const submitting = state.matches('submitting');
                const validation = prop('validation');
                syncAllFieldMachines(scope);

                const submittedFormData = getFormData(scope);
                const submittedValues = createFormValues(submittedFormData);

                const cachedServerErrors = filterErrorsForCurrentValues(
                    refs.get('serverErrors'),
                    submittedValues
                );
                let errors = cachedServerErrors;

                if (validation) {
                    errors = {
                        ...cachedServerErrors,
                        ...validateWithValidation(validation, submittedValues),
                    };
                }

                distributeFieldErrors(scope, errors);

                if (Object.keys(errors).length > 0) {
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

                (async () => {
                    const invalidateWithErrors = (nextErrors: FormErrors) => {
                        const currentErrors = attachErrorValues(nextErrors, submittedValues);
                        refs.set('serverErrors', { ...refs.get('serverErrors'), ...currentErrors });
                        distributeFieldErrors(scope, currentErrors);
                        send({ type: 'INVALID' });
                        action(['focusFirstInvalid']);
                    };

                    try {
                        const result = await onSubmit({
                            values: submittedValues,
                            api: event.detail.api,
                            event: event.detail.event,
                            post: async (url: string): Promise<Response> => {
                                const prefixedData = new FormData();
                                const objectName = prop('objectName');
                                const formEl = dom.getFormEl(scope);
                                const prefix = formEl?.getAttribute('data-field-name-prefix') || '';

                                for (const [key, value] of submittedFormData.entries()) {
                                    if (prefix && key.startsWith(`${prefix}[`)) {
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
                                    const responseErrors = await response.json();
                                    const formErrorMessages = getFormErrorMessages(
                                        responseErrors,
                                        objectName
                                    );
                                    if (formErrorMessages) {
                                        throw new FormError(formErrorMessages);
                                    }

                                    throw new ValidationError(
                                        mapServerErrors(responseErrors, objectName, submittedValues)
                                    );
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
                        if (error instanceof FormError) {
                            send({
                                type: 'SET_ERROR_TEXT',
                                detail: { text: error.errors.join(' ') },
                            });
                            send({ type: 'ERROR' });
                            return;
                        }

                        if (error instanceof ValidationError) {
                            invalidateWithErrors(error.errors);
                            return;
                        }

                        send({ type: 'ERROR' });
                    }
                })();
            },

            validateField({ event, prop, refs, scope, state }) {
                const fieldName = event.detail?.fieldName;
                if (!fieldName) return;

                const normalizedFieldName = normalizeFieldName(fieldName);
                const formData = getFormData(scope);
                const values = createFormValues(formData);
                const serverError = getCurrentErrorForField(
                    refs.get('serverErrors'),
                    normalizedFieldName,
                    values
                );

                let fieldErrors: string[] = serverError?.messages ?? [];
                if (!serverError) {
                    const validation = prop('validation');
                    if (validation) {
                        fieldErrors =
                            validateWithValidation(validation, values, normalizedFieldName)[
                                normalizedFieldName
                            ]?.messages ?? [];
                    }
                }

                setFieldMachineErrors(scope, normalizedFieldName, fieldErrors);

                if (hasInvalidFieldMachines(scope)) {
                    state.set('invalid');
                } else if (!state.matches('submitting')) {
                    state.set('ready');
                }
            },

            resetForm({ context, scope, event, refs }) {
                const form = dom.getFormEl(scope);

                if (form && !event?.detail?.omitManualReset) {
                    form.reset();
                }

                context.set('errorText', null);
                context.set('successText', null);
                refs.set('serverErrors', {});
                resetFieldMachines(scope);
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

            focusFirstInvalid({ scope }) {
                nextTick(() => {
                    const firstInvalidField = getFirstInvalidFieldMachine(scope);
                    if (!firstInvalidField) return;

                    const form = dom.getFormEl(scope);
                    if (!form) return;

                    const invalidEl = getFieldElement(form, firstInvalidField);
                    invalidEl?.focus();
                    if (
                        invalidEl instanceof HTMLInputElement ||
                        invalidEl instanceof HTMLTextAreaElement
                    ) {
                        invalidEl.select();
                    }
                });
            },

            clearErrors({ scope, refs }) {
                refs.set('serverErrors', {});
                for (const [, fieldMachine] of getRegisteredFieldMachines(scope)) {
                    fieldMachine.send({ type: 'CLEAR_ERRORS' });
                }
            },
        },
    },
});

function isFormErrors(result: unknown): result is FormErrors {
    return typeof result === 'object' && result !== null && !Array.isArray(result);
}
