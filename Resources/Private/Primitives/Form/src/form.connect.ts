import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { createFieldHandle } from '../../Field/src/field.connect';
import type { FieldHandle } from '../../Field/src/field.types';
import { parts } from './form.anatomy';
import * as dom from './form.dom';
import type { FormApi, FormDirty, FormErrors, FormSchema, FormTouched } from './form.types';
import { formDataToObject, getRegisteredFieldMachines } from './form.utils';

export function connect<T extends PropTypes>(
    service: Service<FormSchema>,
    normalize: NormalizeProps<T>
): FormApi {
    const { context, state, send, scope, prop } = service;

    const formEl = dom.getFormEl(scope);

    function getFieldHandles(): Map<string, FieldHandle> {
        return new Map(
            Array.from(getRegisteredFieldMachines(scope), ([name, fieldMachine]) => [
                name,
                createFieldHandle(fieldMachine),
            ])
        );
    }

    function getValues() {
        return formEl ? new FormData(formEl) : new FormData();
    }

    function getErrors(): FormErrors {
        const errors: FormErrors = {};

        for (const [name, field] of getFieldHandles()) {
            if (field.errors.length === 0) continue;
            errors[name] = {
                messages: [...field.errors],
                value: field.value,
            };
        }

        return errors;
    }

    function getDirty(): FormDirty {
        const dirty: FormDirty = {};

        for (const [name, field] of getFieldHandles()) {
            if (field.meta.isDirty) {
                dirty[name] = true;
            }
        }

        return dirty;
    }

    function getTouched(): FormTouched {
        const touched: FormTouched = {};

        for (const [name, field] of getFieldHandles()) {
            if (field.meta.isTouched) {
                touched[name] = true;
            }
        }

        return touched;
    }

    function getErrorText() {
        return context.get('errorText');
    }

    function getSuccessText() {
        return context.get('successText');
    }

    const fieldHandles = getFieldHandles();
    const isSubmitting = state.matches('submitting');
    const isDirty = Array.from(fieldHandles.values()).some(field => field.meta.isDirty);
    const isInvalid = Array.from(fieldHandles.values()).some(field => field.invalid);
    const isSuccessful = state.matches('success');
    const isError = state.matches('error');
    const isTouched = Array.from(fieldHandles.values()).some(field => field.meta.isTouched);
    const stateValue = state.get();

    return {
        isSubmitting,
        isDirty,
        isInvalid,
        isSuccessful,
        isError,

        getValues,
        getErrors,
        getDirty,
        getTouched,
        getErrorText,
        getSuccessText,

        setErrorText(text) {
            send({ type: 'SET_ERROR_TEXT', detail: { text } });
        },

        setSuccessText(text) {
            send({ type: 'SET_SUCCESS_TEXT', detail: { text } });
        },

        clearStatusText() {
            send({ type: 'CLEAR_STATUS_TEXT' });
        },

        _userRenderFn: prop('render'),

        getFormEl() {
            return formEl;
        },
        getAllFields() {
            return getFieldHandles();
        },
        getField(name) {
            return this.getAllFields().get(name);
        },
        getAction() {
            return formEl?.getAttribute('action') || '';
        },

        formDataToObject() {
            return formDataToObject(getValues());
        },

        reset() {
            send({ type: 'RESET' });
        },

        getContentProps() {
            return normalize.element({
                ...parts.content.attrs,
                hidden: isError || isSuccessful,
            });
        },

        getIndicatorProps(indicatorState) {
            return normalize.element({
                ...parts.indicator.attrs,
                hidden: stateValue !== indicatorState,
            });
        },

        getErrorTextProps() {
            return normalize.element({
                ...parts['error-text'].attrs,
                hidden: !isError,
                role: 'alert',
            });
        },

        getSuccessTextProps() {
            return normalize.element({
                ...parts['success-text'].attrs,
                hidden: !isSuccessful,
                role: 'status',
                'aria-live': 'polite',
            });
        },

        getFormProps() {
            return normalize.element({
                ...parts.form.attrs,
                noValidate: true,
                id: dom.getFormId(scope),
                'data-state': stateValue,
                'data-submitting': isSubmitting ? '' : undefined,
                'data-invalid': isInvalid ? '' : undefined,
                'data-dirty': isDirty ? '' : undefined,
                'data-touched': isTouched ? '' : undefined,
                onSubmit: event => {
                    event.preventDefault();
                    if (isSubmitting) return;
                    send({ type: 'SUBMIT', detail: { event, api: this } });
                },
                onReset: () => {
                    send({ type: 'RESET', detail: { omitManualReset: true } });
                },
            });
        },
    };
}
