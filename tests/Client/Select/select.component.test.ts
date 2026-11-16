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

function mountFilterableSelect() {
    document.body.innerHTML = `
        <div data-select-root="s1">
            <div data-select-content="s1">
                <input data-select-input="s1">
                <div data-select-list="s1">
                    <div data-select-item-group="s1" data-value="fruit">
                        <div data-select-item="s1" data-value="apple">Apple</div>
                        <div data-select-item="s1" data-value="pear">Pear</div>
                    </div>
                    <div data-select-item="s1" data-value="carrot">Carrot</div>
                </div>
                <div data-select-empty="s1"><div hidden>No results</div></div>
            </div>
        </div>`;
    const collection = select.collection({
        items: [
            { label: 'Apple', value: 'apple' },
            { label: 'Pear', value: 'pear' },
            { label: 'Carrot', value: 'carrot' },
        ],
    });
    instance = new Select({
        id: 's1',
        collection,
        onInputValueChange: ({ inputValue }) => {
            const source = instance!.getSourceCollection();
            instance!.updateProps({
                collection: source.filter(label => label.toLowerCase().includes(inputValue)),
            });
        },
    }).init();

    const item = (value: string) =>
        document.querySelector<HTMLElement>(`[data-select-item][data-value="${value}"]`)!;
    const type = (text: string) => {
        const input = document.querySelector<HTMLInputElement>('[data-select-input]')!;
        input.value = text;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    return { item, type };
}

test('hides the items the filtered collection no longer holds, their group and shows the empty part', async () => {
    const { item, type } = mountFilterableSelect();
    const group = document.querySelector<HTMLElement>('[data-select-item-group]')!;
    const part = document.querySelector<HTMLElement>('[data-select-empty]')!;
    const empty = part.firstElementChild as HTMLElement;
    expect(item('apple').hidden).toBe(false);

    type('car');
    await expect.poll(() => item('apple').hidden).toBe(true);
    expect(item('pear').hidden).toBe(true);
    expect(item('carrot').hidden).toBe(false);
    expect(group.hidden).toBe(true);
    expect(empty.hidden).toBe(true);

    type('zzz');
    await expect.poll(() => empty.hidden).toBe(false);
    // the part stays a live region on the page, so the message that shows is announced
    expect(part.hidden).toBe(false);
    expect(part.getAttribute('role')).toBe('status');
    expect(item('carrot').hidden).toBe(true);
    expect(document.querySelector('[data-select-list]')!.hasAttribute('data-empty')).toBe(true);

    type('');
    await expect.poll(() => item('apple').hidden).toBe(false);
    expect(group.hidden).toBe(false);
    expect(empty.hidden).toBe(true);
    expect(instance!.getSourceCollection().size).toBe(3);
});

test('labels the input for the listbox it filters', () => {
    mountFilterableSelect();

    const input = document.querySelector<HTMLElement>('[data-select-input]')!;
    const list = document.querySelector<HTMLElement>('[data-select-list]')!;
    expect(list.id).not.toBe('');
    expect(input.getAttribute('aria-controls')).toBe(list.id);
    expect(input.getAttribute('role')).toBe('searchbox');
});
