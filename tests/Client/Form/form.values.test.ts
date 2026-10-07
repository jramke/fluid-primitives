import { describe, expect, test } from 'vitest';
import {
    createFormValues,
    getFieldErrorValue,
} from '../../../Resources/Private/Primitives/Form/src/form.values';

function formData(entries: Array<[string, string]>) {
    const data = new FormData();
    for (const [name, value] of entries) data.append(name, value);
    return data;
}

describe('createFormValues', () => {
    test('nests bracket and dot names and keeps repeated or [] keys as arrays', () => {
        const values = createFormValues(
            formData([
                ['name', 'Ada'],
                ['address[city]', 'Berlin'],
                ['people[0][firstName]', 'Grace'],
                ['people[1][firstName]', 'Alan'],
                ['tags[]', 'a'],
                ['tags[]', 'b'],
            ])
        );

        expect(values.toObject()).toEqual({
            name: 'Ada',
            address: { city: 'Berlin' },
            people: [{ firstName: 'Grace' }, { firstName: 'Alan' }],
            tags: ['a', 'b'],
        });
        expect(values.get('address[city]')).toBe('Berlin');
        expect(values.pick('address.city')).toBe('Berlin');
        expect(values.getAll('tags')).toEqual(['a', 'b']);
        expect(values.has('people[1][firstName]')).toBe(true);
        expect(values.has('people[2][firstName]')).toBe(false);
    });

    test('error values are only leaves: a string, a list of strings, or null for containers', () => {
        const values = createFormValues(
            formData([
                ['email', 'a@b.c'],
                ['tags[]', 'a'],
                ['tags[]', 'b'],
                ['address[city]', 'Berlin'],
            ])
        );

        expect(getFieldErrorValue(values, 'email')).toBe('a@b.c');
        expect(getFieldErrorValue(values, 'tags')).toEqual(['a', 'b']);
        expect(getFieldErrorValue(values, 'address')).toBeNull();
        expect(getFieldErrorValue(values, 'missing')).toBeNull();
    });
});
