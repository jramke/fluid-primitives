import { afterEach, expect, test } from 'vitest';
import { applyClientPropConverters } from '../../../Resources/Private/Client';
import { Menu } from '../../../Resources/Private/Primitives/Menu/Menu';

let instance: Menu | undefined;

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
});

function mountFilterableMenu() {
    document.body.innerHTML = `
        <div data-menu-content="m1" role="dialog">
            <input data-menu-input="m1">
            <div data-menu-list="m1">
                <div data-menu-item-group="m1" data-value="edit">
                    <div data-menu-item="m1" data-value="copy">Copy</div>
                    <div data-menu-item="m1" data-value="cut">Cut</div>
                </div>
                <div data-menu-separator="m1"></div>
                <div data-menu-item="m1" data-value="cafe" data-valuetext="Café au lait">Order a coffee</div>
                <div data-menu-empty="m1"><div hidden>No results</div></div>
            </div>
        </div>`;
    instance = new Menu({ id: 'm1', popupType: 'dialog' }).init();

    const query = (selector: string) => document.querySelector<HTMLElement>(selector)!;
    // the part stays on the page as a live region, its content shows and hides
    const emptyMessage = () => query('[data-menu-empty]').firstElementChild as HTMLElement;
    const values = () =>
        [...document.querySelectorAll<HTMLElement>('[data-menu-item]')].map(el => el.dataset.value);
    const type = (text: string) => {
        const input = query('[data-menu-input]') as HTMLInputElement;
        input.value = text;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    };
    return { query, values, type, emptyMessage };
}

test('filters the items while you type and brings them back in order', () => {
    const { query, values, type, emptyMessage } = mountFilterableMenu();
    expect(values()).toEqual(['copy', 'cut', 'cafe']);
    expect(emptyMessage().hidden).toBe(true);

    type('CU');
    expect(values()).toEqual(['cut']);
    expect(query('[data-menu-item-group]').hidden).toBe(false);
    expect(query('[data-menu-separator]').hidden).toBe(true);

    // the valuetext wins over the text of the item, and accents do not matter
    type('cafe au');
    expect(values()).toEqual(['cafe']);
    expect(query('[data-menu-item-group]').hidden).toBe(true);

    type('zzz');
    expect(values()).toEqual([]);
    expect(emptyMessage().hidden).toBe(false);
    expect(query('[data-menu-empty]').hidden).toBe(false);
    expect(query('[data-menu-list]').hasAttribute('data-empty')).toBe(true);

    type('');
    expect(values()).toEqual(['copy', 'cut', 'cafe']);
    expect(emptyMessage().hidden).toBe(true);
    expect(query('[data-menu-separator]').hidden).toBe(false);
    expect(query('[data-menu-list]').hasAttribute('data-empty')).toBe(false);
});

test('marks the input, the list and the empty part for the machine', () => {
    const { query } = mountFilterableMenu();

    const list = query('[data-menu-list]');
    expect(query('[data-menu-input]').getAttribute('aria-controls')).toBe(list.id);
    expect(list.id).not.toBe('');
    expect(list.getAttribute('role')).toBe('menu');
    expect(query('[data-menu-empty]').getAttribute('aria-live')).toBe('polite');
});

test('destroy brings the filtered items back and stops filtering', () => {
    const { values, type } = mountFilterableMenu();
    type('CU');
    expect(values()).toEqual(['cut']);

    instance!.destroy();
    instance = undefined;
    expect(values()).toEqual(['copy', 'cut', 'cafe']);

    type('zzz');
    expect(values()).toEqual(['copy', 'cut', 'cafe']);
});

test('autoHighlight takes a boolean, the text of one or always', () => {
    const autoHighlight = (value: unknown) =>
        applyClientPropConverters('menu', { autoHighlight: value }).autoHighlight;

    expect([true, 'true', '1'].map(autoHighlight)).toEqual([true, true, true]);
    expect([false, 'false', '0', ''].map(autoHighlight)).toEqual([false, false, false, false]);
    expect(autoHighlight('always')).toBe('always');
    expect(autoHighlight(undefined)).toBeUndefined();
});
