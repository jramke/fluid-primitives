import { normalizeFieldName } from '../../Form/src/form.path';
import * as dom from './field.dom';
import type { FieldValue } from './field.types';

type AnyFormControlElement =
    | HTMLInputElement
    | HTMLTextAreaElement
    | HTMLSelectElement
    | HTMLButtonElement;

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

export function getFieldValueFromContainer(container: ParentNode, fieldName: string) {
    const normalizedFieldName = normalizeFieldName(fieldName);
    const selector = [
        `input[name="${CSS.escape(normalizedFieldName)}"]`,
        `input[name="${CSS.escape(normalizedFieldName)}[]"]`,
        `select[name="${CSS.escape(normalizedFieldName)}"]`,
        `select[name="${CSS.escape(normalizedFieldName)}[]"]`,
        `textarea[name="${CSS.escape(normalizedFieldName)}"]`,
        `textarea[name="${CSS.escape(normalizedFieldName)}[]"]`,
    ].join(', ');
    const elements = Array.from(container.querySelectorAll(selector)) as Array<
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
    >;

    if (elements.length === 0) {
        return null;
    }

    if (elements.every(el => el instanceof HTMLInputElement && el.type === 'checkbox')) {
        const checkboxes = elements as HTMLInputElement[];
        const checkedValues = checkboxes
            .filter(input => input.checked)
            .map(input => input.value || 'on');
        const isArrayValue =
            fieldName.endsWith('[]') ||
            elements.some(el => el.name.endsWith('[]')) ||
            checkboxes.length > 1;

        if (isArrayValue) {
            return checkedValues;
        }

        return checkedValues[0] ?? null;
    }

    if (elements.every(el => el instanceof HTMLInputElement && el.type === 'radio')) {
        const checkedInput = (elements as HTMLInputElement[]).find(input => input.checked);
        return checkedInput?.value ?? null;
    }

    if (elements.length === 1) {
        const [element] = elements;

        if (element instanceof HTMLInputElement && element.type === 'file') {
            const files = element.files ? Array.from(element.files) : [];
            return element.multiple ? files : (files[0] ?? null);
        }

        if (element instanceof HTMLSelectElement && element.multiple) {
            return Array.from(element.selectedOptions).map(option => option.value);
        }

        return element.value ?? null;
    }

    return elements
        .map(element => {
            if (element instanceof HTMLInputElement && element.type === 'file') {
                return element.files?.[0];
            }

            return element.value;
        })
        .filter((value): value is FormDataEntryValue => value !== undefined);
}

export function getFieldElement(form: HTMLFormElement, fieldName: string) {
    return form.querySelector(
        [
            `input[name="${CSS.escape(fieldName)}"]`,
            `input[name="${CSS.escape(fieldName)}[]"]`,
            `select[name="${CSS.escape(fieldName)}"]`,
            `select[name="${CSS.escape(fieldName)}[]"]`,
            `textarea[name="${CSS.escape(fieldName)}"]`,
            `textarea[name="${CSS.escape(fieldName)}[]"]`,
        ].join(', ')
    ) as AnyFormControlElement | null;
}

export function serializeFieldValue(value: FormDataEntryValue | FormDataEntryValue[] | null) {
    return JSON.stringify(toSerializableFieldValue(value));
}

function toSerializableFieldValue(
    value: FormDataEntryValue | FormDataEntryValue[] | null
): unknown {
    if (Array.isArray(value)) {
        return value.map(toSerializableFieldValue);
    }

    if (value instanceof File) {
        return {
            name: value.name,
            size: value.size,
            type: value.type,
            lastModified: value.lastModified,
        };
    }

    return value;
}
