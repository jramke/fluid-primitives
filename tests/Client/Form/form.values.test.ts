import { describe, expect, test } from 'vitest';
import {
    createFormValues,
    getFieldErrorValue,
    readFormValues,
} from '../../../Resources/Private/Primitives/Form/src/form.values';

function formData(entries: Array<[string, string]>) {
    const data = new FormData();
    for (const [name, value] of entries) data.append(name, value);
    return data;
}

describe('createFormValues', () => {
    test('ignores a field named after a prototype property instead of writing to Object.prototype', () => {
        const values = createFormValues(
            formData([
                ['__proto__[polluted]', 'yes'],
                ['address[constructor][prototype][polluted]', 'yes'],
                ['name', 'Ada'],
            ])
        );

        expect(values.toObject()).toEqual({ name: 'Ada' });
        expect(({} as Record<string, unknown>).polluted).toBeUndefined();
        expect(Object.keys(Object.prototype)).not.toContain('polluted');
    });

    test('nests bracket and dot names and keeps repeated or [] keys as arrays', () => {
        const values = createFormValues(
            formData([
                ['name', 'Ada'],
                ['address[city]', 'Berlin'],
                ['people[0][firstName]', 'Grace'],
                ['people[1][firstName]', 'Alan'],
                ['tags[]', 'a'],
                ['tags[]', 'b'],
                ['needs', 'x'],
                ['needs', 'y'],
            ])
        );

        expect(values.toObject()).toEqual({
            name: 'Ada',
            address: { city: 'Berlin' },
            people: [{ firstName: 'Grace' }, { firstName: 'Alan' }],
            tags: ['a', 'b'],
            needs: ['x', 'y'],
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

describe('readFormValues', () => {
    test('reads the enabled empty-value input of a `name[]` field as an empty list, a disabled one as nothing', () => {
        document.body.innerHTML = `
            <form>
                <input type="hidden" name="tags" value="" data-empty-value="tags[]">
                <input type="hidden" name="agree" value="" data-empty-value="agree">
                <input type="hidden" name="colors" value="" data-empty-value="colors[]" disabled>
                <input type="checkbox" name="colors[]" value="red" checked>
            </form>`;

        const values = readFormValues(document.querySelector('form'));

        expect(values.toObject()).toEqual({ tags: [], agree: '', colors: ['red'] });
        expect(values.getAll('tags[]')).toEqual([]);
        expect(values.pick('tags')).toEqual([]);
    });
});
