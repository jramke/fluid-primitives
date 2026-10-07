import { createMachine } from '@zag-js/core';
import { nextTick } from '@zag-js/dom-query';
import * as dom from './form.dom';
import {
    focusField,
    getFirstInvalidField,
    getFormData,
    hasInvalidFieldMachines,
    hasUnownedErrors,
    pruneStaleFieldMachines,
    sendToFieldMachines,
    settleFieldMachines,
} from './form.fields';
import { prefixFieldName } from './form.path';
import type { FormErrors, FormSchema } from './form.types';
import { FormError, ValidationError } from './form.types';
import {
    attachErrorValues,
    filterErrorsForCurrentValues,
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
        SYNC_FIELDS: { actions: ['syncFields'] },
        FIELDS_CHANGED: { actions: ['syncState'] },
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
                pruneStaleFieldMachines(scope);

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

                // Every field commits its own validity: its native constraints, this form's messages
                // for it (getFieldMessages) and its own validate. A submit is the submit-time invalid
                // notification of every field - the valid ones too, so they all latch the attempt and
                // revalidate as the user types from here on (upstream only does so for natively invalid ones).
                sendToFieldMachines(scope, submitting ? 'SUBMIT.INVALID' : 'VALIDATE');

                (async () => {
                    await settleFieldMachines(scope);

                    if (hasInvalidFieldMachines(scope) || hasUnownedErrors(scope, errors)) {
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

                    const invalidateWithErrors = (nextErrors: FormErrors) => {
                        const currentErrors = attachErrorValues(nextErrors, submittedValues);
                        refs.set('serverErrors', { ...refs.get('serverErrors'), ...currentErrors });
                        sendToFieldMachines(scope, 'VALIDATE');
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

            // The form is `invalid` while a field is, `ready` once none is; a submission, a success
            // or an error decides its state itself (and hides the content, so nothing changes under it).
            syncState({ scope, state }) {
                if (!state.matches('ready', 'invalid')) return;
                state.set(hasInvalidFieldMachines(scope) ? 'invalid' : 'ready');
            },

            resetForm({ context, scope, event, refs }) {
                const form = dom.getFormEl(scope);

                if (form && !event?.detail?.omitManualReset) {
                    form.reset();
                }

                context.set('errorText', null);
                context.set('successText', null);
                refs.set('serverErrors', {});
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
                    const firstInvalidField = getFirstInvalidField(scope);
                    const form = dom.getFormEl(scope);
                    if (!firstInvalidField || !form) return;

                    focusField(form, ...firstInvalidField);
                });
            },

            clearErrors({ scope, refs }) {
                refs.set('serverErrors', {});
                sendToFieldMachines(scope, 'ERRORS.CLEAR');
            },

            syncFields({ scope }) {
                pruneStaleFieldMachines(scope);
            },
        },
    },
});

function isFormErrors(result: unknown): result is FormErrors {
    return typeof result === 'object' && result !== null && !Array.isArray(result);
}
