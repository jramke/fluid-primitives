import { getFieldValueFromContainer, serializeFieldValue } from '../../Form/src/form.utils';
import * as dom from './field.dom';
import type { FieldValue } from './field.types';

export function isFieldValueEqual(a: FieldValue, b: FieldValue) {
    return serializeFieldValue(a) === serializeFieldValue(b);
}

export function getCurrentFieldValue(
    scope: Parameters<typeof dom.getRootEl>[0],
    name: string,
    defaultValue: unknown
): FieldValue {
    const rootEl = dom.getRootEl(scope);
    if (!rootEl) {
        return getDefaultFieldValue(defaultValue);
    }

    return getFieldValueFromContainer(rootEl, name) ?? getDefaultFieldValue(defaultValue);
}

export function getDefaultFieldValue(defaultValue: unknown): FieldValue {
    if (defaultValue === null || defaultValue === undefined || defaultValue === false) {
        return null;
    }

    if (defaultValue instanceof File) {
        return defaultValue;
    }

    if (Array.isArray(defaultValue)) {
        return defaultValue.filter(
            (value): value is string | File => value instanceof File || typeof value === 'string'
        );
    }

    if (typeof defaultValue === 'string') {
        return defaultValue;
    }

    return null;
}
