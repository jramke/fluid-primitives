import * as select from '@zag-js/select';
import { afterEach, expect, test } from 'vitest';
import { Select } from '../../../Resources/Private/Primitives/Select/Select';

let instance: Select | undefined;

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
});

test('shows the selected label and the placeholder as text, never as markup', () => {
    const label = '<img src=x onerror="window.__pwned=1"><b>Team</b>';
    document.body.innerHTML = `
        <div data-select-root="s1">
            <span data-select-value-text="s1" data-placeholder="R&amp;D &lt;i&gt;none&lt;/i&gt;"></span>
        </div>`;
    const valueText = document.querySelector<HTMLElement>('[data-select-value-text]')!;
    const collection = select.collection({ items: [{ label, value: 'team' }] });

    instance = new Select({ id: 's1', collection, defaultValue: ['team'] });
    instance.init();

    expect(valueText.textContent).toBe(label);
    expect(valueText.querySelector('img, b')).toBeNull();

    instance.api.clearValue();
    return expect
        .poll(() => valueText.textContent)
        .toBe('R&D <i>none</i>')
        .then(() => {
            expect(valueText.querySelector('i')).toBeNull();
        });
});

const items = [
    { label: 'A', value: 'a' },
    { label: 'B', value: 'b' },
];

function renderHiddenSelect(name: string, multiple: boolean) {
    document.body.innerHTML = `
        <form><div data-select-root="s1">
            <input type="hidden" name="${name.replace(/\[\]$/, '')}" value="" data-empty-value="${name}" data-select-empty-value-input="s1">
            <select name="${name}" ${multiple ? 'multiple' : ''} data-select-hidden-select="s1">
                <option value="" hidden></option>
                ${items.map(item => `<option value="${item.value}">${item.label}</option>`).join('')}
            </select>
        </div></form>`;
    return () => [...new FormData(document.querySelector('form')!).entries()];
}

test('an empty single select submits its empty value through the empty-value input, and only while empty', async () => {
    const entries = renderHiddenSelect('tag', false);

    instance = new Select({ id: 's1', collection: select.collection({ items }), name: 'tag' });
    instance.init();
    expect(entries()).toEqual([['tag', '']]);

    instance.api.setValue(['a']);
    await expect.poll(entries).toEqual([['tag', 'a']]);

    instance.api.clearValue();
    await expect.poll(entries).toEqual([['tag', '']]);
});

test('a multiple select submits its empty value without the brackets, and only while empty', async () => {
    const entries = renderHiddenSelect('tags[]', true);

    instance = new Select({
        id: 's1',
        collection: select.collection({ items }),
        name: 'tags[]',
        multiple: true,
    });
    instance.init();
    expect(entries()).toEqual([['tags', '']]);

    instance.api.setValue(['a']);
    await expect.poll(entries).toEqual([['tags[]', 'a']]);

    instance.api.clearValue();
    await expect.poll(entries).toEqual([['tags', '']]);
});
