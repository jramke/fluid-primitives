import { afterEach, expect, test } from 'vitest';
import { Toggle } from '../../../Resources/Private/Primitives/Toggle/Toggle';

let toggle: Toggle | undefined;

afterEach(() => {
    toggle?.destroy();
    toggle = undefined;
    document.body.innerHTML = '';
});

test('hydrates the server rendered parts under their root id and follows the clicks', async () => {
    document.body.innerHTML = `
        <button type="button" aria-pressed="false" data-toggle-root="t1">
            <span data-toggle-indicator="t1">Bold</span>
        </button>`;
    toggle = new Toggle({ id: 't1' });
    toggle.init();
    const rootEl = document.querySelector<HTMLElement>('button')!;
    const indicatorEl = document.querySelector<HTMLElement>('span')!;

    // zag stamps the part marker itself: a machine without the id would leave its parts behind
    expect(rootEl.getAttribute('data-toggle-root')).toBe('t1');
    expect(indicatorEl.getAttribute('data-toggle-indicator')).toBe('t1');

    rootEl.click();
    await expect.poll(() => rootEl.getAttribute('aria-pressed')).toBe('true');
    expect(rootEl.hasAttribute('data-pressed')).toBe(true);
    expect(indicatorEl.hasAttribute('data-pressed')).toBe(true);

    rootEl.click();
    await expect.poll(() => rootEl.getAttribute('aria-pressed')).toBe('false');
    expect(indicatorEl.hasAttribute('data-pressed')).toBe(false);
});
