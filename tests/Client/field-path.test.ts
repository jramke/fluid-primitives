import { describe, expect, test } from 'vitest';
import {
    appendFieldPathSegment,
    parseFieldPath,
    stringifyFieldPathAsBrackets,
} from '../../Resources/Private/Client/src/lib/field-path';

describe('field path parsing', () => {
    test('treats dot and bracket notation as the same path, with numeric and append segments', () => {
        expect(parseFieldPath('person.country')).toEqual(['person', 'country']);
        expect(parseFieldPath('person[country]')).toEqual(['person', 'country']);
        expect(parseFieldPath('people[0][firstName]')).toEqual(['people', 0, 'firstName']);
        expect(parseFieldPath('people.0.firstName')).toEqual(['people', 0, 'firstName']);
        expect(parseFieldPath('people[][firstName]')).toEqual([
            'people',
            appendFieldPathSegment,
            'firstName',
        ]);
    });

    test('rebuilds fully bracketed names: first segment bare, append marker as []', () => {
        expect(stringifyFieldPathAsBrackets(['people', 0, 'address', 'city'])).toBe(
            'people[0][address][city]'
        );
        expect(stringifyFieldPathAsBrackets(['people', appendFieldPathSegment, 'name'])).toBe(
            'people[][name]'
        );
    });
});
