import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './field.anatomy';
import * as dom from './field.dom';
import type { FieldApi, FieldHandle, FieldMeta, FieldSchema } from './field.types';
import { isFieldValueEqual } from './field.utils';

type FieldServiceLike = Pick<Service<FieldSchema>, 'prop' | 'context'>;

export function createFieldHandle(service: FieldServiceLike): FieldHandle {
    const { prop, context } = service;
    const invalid = context.get('invalid');
    const disabled = context.get('disabled');
    const required = context.get('required');
    const readOnly = context.get('readOnly');
    const errors = context.get('errors');
    const value = context.get('value');
    const initialValue = context.get('initialValue');
    const meta: FieldMeta = {
        isTouched: context.get('touched'),
        isDirty: context.get('dirty'),
        isPristine: !context.get('dirty'),
        isBlurred: context.get('blurred'),
        isDefaultValue: isFieldValueEqual(value, initialValue),
    };

    return {
        getFormMachine: () => context.get('formMachine'),
        meta,
        value,
        invalid,
        disabled,
        required,
        readOnly,
        errors,
        name: prop('name'),
        getErrorText() {
            return errors.length > 0 ? errors.join(' ') : null;
        },
    };
}

export function connect<T extends PropTypes>(
    service: Service<FieldSchema>,
    normalize: NormalizeProps<T>
): FieldApi {
    const { scope } = service;
    const handle = createFieldHandle(service);

    return {
        ...handle,

        getRootProps() {
            return normalize.element({
                ...parts.root.attrs,
                id: dom.getRootId(scope),
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
                'data-required': handle.required ? '' : undefined,
                'data-touched': handle.meta.isTouched ? '' : undefined,
                'data-dirty': handle.meta.isDirty ? '' : undefined,
                'data-pristine': handle.meta.isPristine ? '' : undefined,
                'data-blurred': handle.meta.isBlurred ? '' : undefined,
                'data-default-value': handle.meta.isDefaultValue ? '' : undefined,
                'data-name': handle.name,
            });
        },

        getLabelProps() {
            return normalize.label({
                ...parts.label.attrs,
                id: dom.getLabelId(scope),
                htmlFor: dom.getControlId(scope),
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-required': handle.required ? '' : undefined,
            });
        },

        getControlProps() {
            return normalize.element({
                ...parts.control.attrs,
                id: dom.getControlId(scope),
                name: handle.name,
                disabled: handle.disabled || undefined,
                readOnly: handle.readOnly || undefined,
                required: handle.required || undefined,
                'aria-invalid': handle.invalid ? 'true' : undefined,
                'aria-describedby': service.context.get('describeIds') || undefined,
                'aria-required': handle.required ? 'true' : undefined,
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
            });
        },

        getErrorProps() {
            return normalize.element({
                ...parts.error.attrs,
                id: dom.getErrorId(scope),
                hidden: !handle.invalid,
            });
        },

        getDescriptionProps() {
            return normalize.element({
                ...parts.description.attrs,
                id: dom.getDescriptionId(scope),
            });
        },
    };
}
