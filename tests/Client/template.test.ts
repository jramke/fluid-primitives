import { beforeEach, describe, expect, test } from 'vitest';
import { ComponentHydrator } from '../../Resources/Private/Client/src/lib';
import { Template } from '../../Resources/Private/Client/src/lib/template';

const hydrator = new ComponentHydrator('combobox', 'c1');

function render(template: string) {
    document.body.innerHTML = `<ul data-combobox-item-group="c1"></ul>${template}`;
}

beforeEach(() => {
    render(`
        <template data-combobox-item-template="c1">
            <li data-combobox-item="c1">
                <span data-combobox-item-text="c1">text</span>
                <span data-someone-elses-part="x">other</span>
            </li>
        </template>
    `);
});

describe('Template', () => {
    test('every instance is its own copy, and its root stays the element once the fragment is inserted', () => {
        const first = new Template(hydrator, 'itemTemplate');
        const second = new Template(hydrator, 'itemTemplate');
        const list = document.querySelector('ul')!;
        expect(first.queryAll('itemText')).toEqual([first.root.firstElementChild]);

        list.append(first, second);

        expect(list.children).toHaveLength(2);
        expect(first.root.parentElement).toBe(list);
        expect(first.root).not.toBe(second.root);
        expect(document.querySelector('template')!.content.children).toHaveLength(1);
    });

    test('a value marks the item and its own parts, and an attribute is toggled or set next to it', () => {
        const clone = new Template(hydrator, 'itemTemplate', {
            value: 'apple',
            attributes: { disabled: true, selected: false, group: 'fruit' },
        });

        for (const el of [clone.root, clone.query('itemText')!]) {
            expect(el.dataset.value).toBe('apple');
            expect(el.hasAttribute('data-disabled')).toBe(true);
            expect(el.hasAttribute('data-selected')).toBe(false);
            expect(el.getAttribute('data-group')).toBe('fruit');
        }
        expect(
            clone.root.querySelector('[data-someone-elses-part]')!.hasAttribute('data-value')
        ).toBe(false);
        expect(clone.componentNames).toEqual([]);

        // without a value the clone stays as the template has it
        const plain = new Template(hydrator, 'itemTemplate', { attributes: { disabled: true } });
        expect(plain.root.hasAttribute('data-value')).toBe(false);
        expect(plain.root.hasAttribute('data-disabled')).toBe(false);
    });

    test('refuses a missing template and one without exactly one root element', () => {
        expect(() => new Template(hydrator, 'emptyTemplate')).toThrow(
            'no <template> found for part "emptyTemplate"'
        );

        render('<template data-combobox-item-template="c1"><li></li><li></li></template>');
        expect(() => new Template(hydrator, 'itemTemplate')).toThrow('found 2');
    });
});
