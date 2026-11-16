import * as combobox from '@zag-js/combobox';
import { afterEach, expect, test, vi } from 'vitest';
import { Combobox } from '../../../Resources/Private/Primitives/Combobox/Combobox';

let instance: Combobox | undefined;

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
});

const collection = () =>
    combobox.collection({
        items: [
            { label: 'A', value: 'a' },
            { label: 'B', value: 'b' },
        ],
    });

function render(name: string) {
    document.body.innerHTML = `
        <form><div data-combobox-root="c1">
            <input type="hidden" name="${name.replace(/\[\]$/, '')}" value="" data-empty-value="${name}" data-combobox-empty-value-input="c1">
        </div></form>`;
    return () => [...new FormData(document.querySelector('form')!).entries()];
}

test('an empty multiple combobox submits its name without the brackets, and only while empty', async () => {
    const entries = render('tags[]');

    instance = new Combobox({ id: 'c1', collection: collection(), name: 'tags[]', multiple: true });
    instance.init();
    expect(entries()).toEqual([['tags', '']]);

    instance.api.setValue(['a', 'b']);
    await expect.poll(entries).toEqual([
        ['tags[]', 'a'],
        ['tags[]', 'b'],
    ]);

    instance.api.clearValue();
    await expect.poll(entries).toEqual([['tags', '']]);
});

test('a disabled combobox submits nothing', () => {
    const entries = render('tag');

    instance = new Combobox({ id: 'c1', collection: collection(), name: 'tag', disabled: true });
    instance.init();

    expect(entries()).toEqual([]);
});

test('keeps the empty part on the page as a live region and shows its message only while no item matches', async () => {
    // the open popup is positioned, which observes its size
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        }
    );
    document.body.innerHTML = `
        <div data-combobox-root="c1">
            <div data-combobox-control="c1"><input data-combobox-input="c1"></div>
            <div data-combobox-positioner="c1"><div data-combobox-content="c1">
                <div data-combobox-list="c1">
                    <div data-combobox-item="c1" data-value="a">A</div>
                    <div data-combobox-item="c1" data-value="b">B</div>
                </div>
                <div data-combobox-empty="c1"><div hidden>No results found</div></div>
            </div></div>
        </div>`;
    instance = new Combobox({
        id: 'c1',
        collection: collection(),
        onInputValueChange: ({ inputValue }) => {
            const source = instance!.getSourceCollection();
            instance!.updateProps({ collection: source.filter(label => label === inputValue) });
        },
    });
    instance.init();
    instance.api.setOpen(true);
    const part = document.querySelector<HTMLElement>('[data-combobox-empty]')!;
    const message = part.firstElementChild as HTMLElement;

    instance.api.setInputValue('zzz');
    await expect.poll(() => message.hidden).toBe(false);
    expect(part.getAttribute('role')).toBe('status');
    expect(part.getAttribute('aria-live')).toBe('polite');

    instance.api.setInputValue('A');
    await expect.poll(() => message.hidden).toBe(true);
    expect(part.hidden).toBe(false);
});
