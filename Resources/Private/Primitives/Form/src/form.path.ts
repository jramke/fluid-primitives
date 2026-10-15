import {
    parseFieldPath,
    stringifyFieldPathAsBrackets,
    trimArraySuffix,
} from '../../../Client/src/lib/field-path';

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
    // registered field machine is actually keyed by (see form.registry.ts) and what the error
    // lookups (getFieldMessages) match against exactly.
    return trimArraySuffix(stringifyFieldPathAsBrackets(fieldPath));
}
