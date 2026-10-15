import { describe, expect, test } from 'vitest';
import { toRegisteredFieldName } from '../../../Resources/Private/Primitives/Form/src/form.path';

describe('field name normalization', () => {
    test('registered names are bracketed, objectName-free and without the array suffix', () => {
        expect(toRegisteredFieldName('person.country')).toBe('person[country]');
        expect(toRegisteredFieldName('event.attendees.0.name', 'event')).toBe('attendees[0][name]');
        expect(toRegisteredFieldName('a11yNeeds[]')).toBe('a11yNeeds');
    });
});
