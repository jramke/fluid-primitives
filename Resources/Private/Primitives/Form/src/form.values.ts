import {
    appendFieldPathSegment,
    normalizeFieldName,
    parseFieldPath,
    type FieldPathSegment,
} from './form.path';
import type { FormValueLeaf, FormValues, FormValuesObject, FormValueTree } from './form.types';

export function createFormValues(formData: FormData): FormValues {
    let cachedObject: FormValuesObject | null = null;

    const getObject = () => {
        if (cachedObject === null) {
            cachedObject = formDataToObject(formData);
        }

        return cachedObject;
    };

    return {
        get(path) {
            return getFieldValues(formData, path)[0] ?? null;
        },

        getAll(path) {
            return [...getFieldValues(formData, path)];
        },

        has(path) {
            return getValueAtFieldPath(getObject(), path) !== null;
        },

        pick(path) {
            return getValueAtFieldPath(getObject(), path);
        },

        toObject() {
            return getObject();
        },
    };
}

export function getFieldErrorValue(
    values: FormValues,
    fieldName: string
): FormDataEntryValue | FormDataEntryValue[] | null {
    return toFieldErrorValue(values.pick(fieldName));
}

function formDataToObject(formData: FormData): FormValuesObject {
    const fieldNames = new Set(Array.from(formData.keys(), normalizeFieldName));

    const dataObject: FormValuesObject = {};

    for (const fieldName of fieldNames) {
        setValueAtFieldPath(
            dataObject,
            fieldName,
            getFieldFormDataValue(formData, fieldName) ?? ''
        );
    }

    return dataObject;
}

function getFieldFormDataValue(formData: FormData, fieldName: string) {
    const { entries, isArrayValue } = readFieldEntries(formData, fieldName);
    return isArrayValue ? entries : (entries[0] ?? null);
}

function getFieldValues(formData: FormData, fieldName: string): FormValueLeaf[] {
    return readFieldEntries(formData, fieldName).entries;
}

/**
 * Reads the raw FormData entries for a field name, checking both the direct
 * and `[]`-suffixed key so callers don't each re-query FormData themselves.
 */
function readFieldEntries(
    formData: FormData,
    fieldName: string
): { entries: FormValueLeaf[]; isArrayValue: boolean } {
    const normalizedFieldName = normalizeFieldName(fieldName);
    const directEntries = formData.getAll(normalizedFieldName);
    const bracketEntries = formData.getAll(`${normalizedFieldName}[]`);
    const entries = directEntries.length > 0 ? directEntries : bracketEntries;

    const isArrayValue =
        fieldName.endsWith('[]') || bracketEntries.length > 0 || entries.length > 1;

    return { entries, isArrayValue };
}

function createContainerForFieldPathSegment(
    segment: FieldPathSegment | undefined
): Array<unknown> | Record<string, unknown> {
    return typeof segment === 'number' || segment === appendFieldPathSegment ? [] : {};
}

function setValueAtFieldPath(
    target: FormValuesObject,
    fieldName: string,
    value: FormValueLeaf | FormValueLeaf[]
) {
    const fieldPath = parseFieldPath(fieldName);
    if (fieldPath.length === 0) {
        return;
    }

    let current: Record<string, unknown> | Array<unknown> = target;

    for (let index = 0; index < fieldPath.length; index++) {
        const segment = fieldPath[index];
        const nextSegment = fieldPath[index + 1];
        const isLeaf = index === fieldPath.length - 1;

        if (segment === appendFieldPathSegment) {
            if (!Array.isArray(current)) {
                return;
            }

            if (isLeaf) {
                current.push(value);
                return;
            }

            const nextContainer = createContainerForFieldPathSegment(nextSegment);
            current.push(nextContainer);
            current = nextContainer;
            continue;
        }

        if (typeof segment === 'number') {
            if (!Array.isArray(current)) {
                return;
            }

            if (isLeaf) {
                current[segment] = value;
                return;
            }

            const existingValue = current[segment];
            if (existingValue && typeof existingValue === 'object') {
                current = existingValue as Record<string, unknown> | Array<unknown>;
                continue;
            }

            const nextContainer = createContainerForFieldPathSegment(nextSegment);
            current[segment] = nextContainer;
            current = nextContainer;
            continue;
        }

        if (Array.isArray(current)) {
            return;
        }

        if (isLeaf) {
            current[segment] = value;
            return;
        }

        const existingValue = current[segment];
        if (existingValue && typeof existingValue === 'object') {
            current = existingValue as Record<string, unknown> | Array<unknown>;
            continue;
        }

        const nextContainer = createContainerForFieldPathSegment(nextSegment);
        current[segment] = nextContainer;
        current = nextContainer;
    }
}

function getValueAtFieldPath(values: FormValuesObject, fieldName: string): FormValueTree | null {
    const fieldPath = parseFieldPath(normalizeFieldName(fieldName));
    if (fieldPath.length === 0) {
        return null;
    }

    let current: unknown = values;

    for (const segment of fieldPath) {
        if (segment === appendFieldPathSegment) {
            return Array.isArray(current) ? current : null;
        }

        if (typeof current !== 'object' || current === null) {
            return null;
        }

        current = (current as Record<string | number, unknown>)[segment];

        if (current === undefined) {
            return null;
        }
    }

    return current as FormValueTree;
}

function toFieldErrorValue(
    value: FormValueTree | null
): FormDataEntryValue | FormDataEntryValue[] | null {
    if (
        value === null ||
        (typeof value === 'object' && !Array.isArray(value) && !(value instanceof File))
    ) {
        return null;
    }

    if (typeof value === 'string' || value instanceof File) {
        return value;
    }

    if (Array.isArray(value) && value.every(isFormValueLeaf)) {
        return value;
    }

    return null;
}

function isFormValueLeaf(value: unknown): value is FormValueLeaf {
    return typeof value === 'string' || value instanceof File;
}
