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
