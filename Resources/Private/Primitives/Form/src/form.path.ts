const fieldPathSegmentPattern = /([^.[\]]+)|\[(.*?)\]/g;

export const appendFieldPathSegment = Symbol('append-field-path-segment');

export type FieldPathSegment = string | number | typeof appendFieldPathSegment;

export function normalizeFieldName(fieldName: string) {
    return fieldName.replace(/\[\]$/, '');
}

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

export function toCanonicalFieldName(fieldName: string, objectName?: string) {
    const fieldPath = parseFieldPath(fieldName);
    if (fieldPath.length === 0) {
        return normalizeFieldName(fieldName);
    }

    if (objectName && fieldPath[0] === objectName) {
        fieldPath.shift();
    }

    // Bracket notation throughout, matching Field's own name (people[0][firstName], not
    // people[0].firstName) - not just for the leading array-index segment, since that's what a
    // registered field machine is actually keyed by (see form.registry.ts) and what SET_ERRORS
    // lookups match against exactly.
    return normalizeFieldName(stringifyFieldPathAsBrackets(fieldPath));
}
