/** Matches one path segment: either a bare run of non-delimiter chars, or bracket contents. */
const fieldPathSegmentPattern = /([^.[\]]+)|\[(.*?)\]/g;

/** Sentinel for an empty `[]` segment - "append to this array" - distinct from any real field name. */
export const appendFieldPathSegment = Symbol('append-field-path-segment');

/** One parsed path segment: an object key, an array index, or the append marker. */
export type FieldPathSegment = string | number | typeof appendFieldPathSegment;

/** Strips a trailing `[]` (the manual-bracket array-submission marker, e.g. `a11yNeeds[]`) if present. */
export function trimArraySuffix(fieldName: string) {
    return fieldName.replace(/\[\]$/, '');
}

/**
 * Splits a field name into its path segments, treating `.` and `[...]` as equivalent
 * delimiters - `person.country` and `person[country]` both parse to `['person', 'country']`.
 * An all-digit segment becomes a number, and an empty `[]` becomes {@link appendFieldPathSegment}.
 */
export function parseFieldPath(fieldName: string): FieldPathSegment[] {
    const fieldPath: FieldPathSegment[] = [];

    for (const match of fieldName.matchAll(fieldPathSegmentPattern)) {
        if (match[1]) {
            const segment = match[1];
            fieldPath.push(/^\d+$/.test(segment) ? Number(segment) : segment);
            continue;
        }

        const bracketSegment = match[2] ?? '';
        if (bracketSegment === '') {
            fieldPath.push(appendFieldPathSegment);
            continue;
        }

        fieldPath.push(/^\d+$/.test(bracketSegment) ? Number(bracketSegment) : bracketSegment);
    }

    return fieldPath;
}

/**
 * Joins path segments back into one bracketed name: the first segment stays bare, every
 * segment after it gets wrapped in `[...]`, and {@link appendFieldPathSegment} becomes `[]`.
 */
export function stringifyFieldPathAsBrackets(fieldPath: readonly FieldPathSegment[]) {
    let fieldName = '';

    for (const segment of fieldPath) {
        if (segment === appendFieldPathSegment) {
            fieldName += '[]';
            continue;
        }

        const segmentValue = String(segment);
        fieldName += fieldName === '' ? segmentValue : `[${segmentValue}]`;
    }

    return fieldName;
}

/**
 * Parses `fieldName` (dot or bracket notation) and rebuilds it fully bracketed, with
 * `prefix` and `objectName` unshifted onto the front in that order. Used to rewrite submitted
 * `FormData` keys into the form's real Extbase argument name just before `fetch()`.
 */
export function prefixFieldName(fieldName: string, prefix: string, objectName?: string) {
    if (fieldName === '') {
        return '';
    }

    const fieldPath = parseFieldPath(fieldName);
    if (objectName) {
        fieldPath.unshift(objectName);
    }

    if (prefix) {
        fieldPath.unshift(prefix);
    }

    return stringifyFieldPathAsBrackets(fieldPath);
}

/**
 * Resolves any field-name string (dot or bracket, optionally objectName-prefixed) to the exact
 * bracket-notation key the field registry uses.
 */
export function toRegisteredFieldName(fieldName: string, objectName?: string) {
    const fieldPath = parseFieldPath(fieldName);
    if (fieldPath.length === 0) {
        return trimArraySuffix(fieldName);
    }

    if (objectName && fieldPath[0] === objectName) {
        fieldPath.shift();
    }

    // Bracket notation throughout, matching Field's own name (people[0][firstName], not
    // people[0].firstName) - not just for the leading array-index segment, since that's what a
    // registered field machine is actually keyed by (see form.registry.ts) and what SET_ERRORS
    // lookups match against exactly.
    return trimArraySuffix(stringifyFieldPathAsBrackets(fieldPath));
}
