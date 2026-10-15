import { createMachine } from '@zag-js/core';
import { nextTick } from '@zag-js/dom-query';
import { devWarn, extbase, ExtbaseValidationError } from '../../../Client';
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
import type { FormErrors, FormSchema } from './form.types';
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
                        devWarn(
                            'Form has no onSubmit: a valid submit is only prevented and shown as success. Pass onSubmit to send the values.'
                        );
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
                            post: (url, init) =>
                                extbase.post(url, submittedFormData, {
                                    ...init,
                                    prefix: prop('fieldNamePrefix'),
                                    objectName: prop('objectName'),
                                }),
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
                        if (error instanceof ExtbaseValidationError) {
                            const objectName = prop('objectName');
                            const formErrorMessages = getFormErrorMessages(
                                error.errors,
                                objectName
                            );
                            if (formErrorMessages) {
                                send({
                                    type: 'SET_ERROR_TEXT',
                                    detail: { text: formErrorMessages.join(' ') },
                                });
                                send({ type: 'ERROR' });
                                return;
                            }

                            invalidateWithErrors(
                                mapServerErrors(error.errors, objectName, submittedValues)
                            );
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
