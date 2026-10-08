import { beforeEach, describe, expect, test } from 'vitest';
import {
    getComparableFieldValue,
    getCurrentFieldValue,
    toComparableString,
} from '../../../Resources/Private/Primitives/Field/src/field.value';

let root: HTMLElement;

beforeEach(() => {
    root = document.createElement('div');
    document.body.replaceChildren(root);
});

describe('toComparableString', () => {
    test('is empty for nothing, including the placeholder entry of a multiple select', () => {
        expect(toComparableString(null)).toBe('');
        expect(toComparableString([])).toBe('');
        expect(toComparableString([''])).toBe('');
        expect(toComparableString('')).toBe('');
    });

    test('keeps a plain string as is (not trimmed) and serializes lists and files', () => {
        expect(toComparableString(' a ')).toBe(' a ');
        expect(toComparableString(['a', 'b'])).not.toBe(toComparableString(['a']));
        const file = new File(['x'], 'a.txt', { type: 'text/plain', lastModified: 1 });
        expect(toComparableString(file)).toContain('a.txt');
    });
});

describe('field value from the DOM', () => {
    test('reads a checkbox by its checked state, not by its constant value', () => {
        root.innerHTML = '<input type="checkbox" name="terms" value="1">';
        const checkbox = root.querySelector('input')!;

        expect(getComparableFieldValue(root, 'terms', undefined)).toBe('');
        checkbox.checked = true;
        expect(getComparableFieldValue(root, 'terms', undefined)).toBe('1');
    });

    test('reads grouped checkboxes as a list and a multiple select without its placeholder', () => {
        root.innerHTML = `
            <input type="checkbox" name="needs[]" value="a" checked>
            <input type="checkbox" name="needs[]" value="b">
            <select name="tags[]" multiple><option value="" selected>-</option><option value="x">x</option></select>
        `;

        expect(getCurrentFieldValue(root, 'needs[]', undefined)).toEqual(['a']);
        expect(getComparableFieldValue(root, 'tags[]', undefined)).toBe('');
        root.querySelectorAll('option')[1].selected = true;
        expect(getComparableFieldValue(root, 'tags[]', undefined)).not.toBe('');
    });

    test('falls back to the default value only while no control exists for the name', () => {
        expect(getCurrentFieldValue(root, 'title', 'Hello')).toBe('Hello');

        root.innerHTML = '<input name="title" value="">';
        expect(getCurrentFieldValue(root, 'title', 'Hello')).toBe('');
    });
});
