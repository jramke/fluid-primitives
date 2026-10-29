import {
    appendFieldPathSegment,
    parseFieldPath,
    trimArraySuffix,
    type FieldPathSegment,
} from './form.path';
import type { FormValueLeaf, FormValues, FormValuesObject, FormValueTree } from './form.types';

/**
 * The values of a form. An enabled empty-value input of a `name[]` field (`data-empty-value`, see
 * HasEmptyValueInputTrait) submits `''` under `name`, which Extbase maps to an empty array: here it is `[]`.
 */
export function readFormValues(formEl: HTMLFormElement | null): FormValues {
    if (!formEl) return createFormValues(new FormData());

    const emptyArrayNames = new Set(
        Array.from(formEl.elements)
            .filter(
                (el): el is HTMLInputElement =>
                    el instanceof HTMLInputElement &&
                    !el.disabled &&
                    !!el.dataset.emptyValue?.endsWith('[]')
            )
            .map(el => el.name)
    );

    return createFormValues(new FormData(formEl), emptyArrayNames);
}

export function createFormValues(
    formData: FormData,
    emptyArrayNames: ReadonlySet<string> = new Set()
): FormValues {
    let cachedObject: FormValuesObject | null = null;

    const getObject = () => {
        if (cachedObject === null) {
            cachedObject = formDataToObject(formData, emptyArrayNames);
        }

        return cachedObject;
    };

    return {
        get(path) {
            return getFieldValues(formData, path, emptyArrayNames)[0] ?? null;
        },

        getAll(path) {
            return [...getFieldValues(formData, path, emptyArrayNames)];
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

function formDataToObject(
    formData: FormData,
    emptyArrayNames: ReadonlySet<string>
): FormValuesObject {
    const fieldNames = new Set(Array.from(formData.keys(), trimArraySuffix));

    const dataObject: FormValuesObject = {};

    for (const fieldName of fieldNames) {
        setValueAtFieldPath(
            dataObject,
            fieldName,
            getFieldFormDataValue(formData, fieldName, emptyArrayNames) ?? ''
        );
    }

    return dataObject;
}

function getFieldFormDataValue(
    formData: FormData,
    fieldName: string,
    emptyArrayNames: ReadonlySet<string>
) {
    const { entries, isArrayValue } = readFieldEntries(formData, fieldName, emptyArrayNames);
    return isArrayValue ? entries : (entries[0] ?? null);
}

function getFieldValues(
    formData: FormData,
    fieldName: string,
    emptyArrayNames: ReadonlySet<string>
): FormValueLeaf[] {
    return readFieldEntries(formData, fieldName, emptyArrayNames).entries;
}

/**
 * Reads the raw FormData entries for a field name, checking both the direct
 * and `[]`-suffixed key so callers don't each re-query FormData themselves.
 */
function readFieldEntries(
    formData: FormData,
    fieldName: string,
    emptyArrayNames: ReadonlySet<string>
): { entries: FormValueLeaf[]; isArrayValue: boolean } {
    const normalizedFieldName = trimArraySuffix(fieldName);
    if (emptyArrayNames.has(normalizedFieldName)) {
        // An enabled empty-value input stands for an empty list, and gives way to a real entry of the field:
        // it is enabled until its control has hydrated.
        const entries = [
            ...formData.getAll(normalizedFieldName).filter(entry => entry !== ''),
            ...formData.getAll(`${normalizedFieldName}[]`),
        ];
        return { entries, isArrayValue: true };
    }

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

// A name like `__proto__[polluted]` would otherwise write onto Object.prototype
const unsafeFieldPathSegments = new Set(['__proto__', 'constructor', 'prototype']);

function setValueAtFieldPath(
    target: FormValuesObject,
    fieldName: string,
    value: FormValueLeaf | FormValueLeaf[]
) {
    const fieldPath = parseFieldPath(fieldName);
    if (
        fieldPath.length === 0 ||
        fieldPath.some(
            segment => typeof segment === 'string' && unsafeFieldPathSegments.has(segment)
        )
    ) {
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
    const fieldPath = parseFieldPath(trimArraySuffix(fieldName));
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
