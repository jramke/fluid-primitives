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
            fieldPath.push(match[1]);
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

export function stringifyFieldPath(fieldPath: readonly FieldPathSegment[]) {
    let fieldName = '';

    for (const segment of fieldPath) {
        if (segment === appendFieldPathSegment) {
            fieldName += '[]';
            continue;
        }

        if (typeof segment === 'number') {
            fieldName += `[${segment}]`;
            continue;
        }

        fieldName += fieldName === '' ? segment : `.${segment}`;
    }

    return fieldName;
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

    return normalizeFieldName(stringifyFieldPath(fieldPath));
}
