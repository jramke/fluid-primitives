import { describe, expect, test } from 'vitest';
import {
    appendFieldPathSegment,
    parseFieldPath,
    prefixFieldName,
    stringifyFieldPathAsBrackets,
    toRegisteredFieldName,
} from '../../../Resources/Private/Primitives/Form/src/form.path';

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

describe('field name normalization', () => {
    test('registered names are bracketed, objectName-free and without the array suffix', () => {
        expect(toRegisteredFieldName('person.country')).toBe('person[country]');
        expect(toRegisteredFieldName('event.attendees.0.name', 'event')).toBe('attendees[0][name]');
        expect(toRegisteredFieldName('a11yNeeds[]')).toBe('a11yNeeds');
    });

    test('submitted names get prefix then objectName unshifted in front', () => {
        expect(prefixFieldName('title', 'tx_plugin', 'event')).toBe('tx_plugin[event][title]');
        expect(prefixFieldName('address.city', '', 'event')).toBe('event[address][city]');
        expect(prefixFieldName('', 'tx_plugin', 'event')).toBe('');
    });
});
