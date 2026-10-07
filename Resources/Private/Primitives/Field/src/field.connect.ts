import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './field.anatomy';
import * as dom from './field.dom';
import { createFieldHandle } from './field.handle';
import type { FieldApi, FieldSchema } from './field.types';

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
                ...parts.root.attrs(scope.id),
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
                ...parts.label.attrs(scope.id),
                id: dom.getLabelId(scope),
                htmlFor: dom.getControlId(scope),
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-required': handle.required ? '' : undefined,
            });
        },

        getControlProps() {
            return normalize.element({
                ...parts.control.attrs(scope.id),
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
                ...parts.error.attrs(scope.id),
                id: dom.getErrorId(scope),
                hidden: !handle.invalid,
            });
        },

        getDescriptionProps() {
            return normalize.element({
                ...parts.description.attrs(scope.id),
                id: dom.getDescriptionId(scope),
            });
        },
    };
}
