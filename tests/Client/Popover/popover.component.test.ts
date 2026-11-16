import { afterEach, expect, test } from 'vitest';
import { Popover } from '../../../Resources/Private/Primitives/Popover/Popover';

let popover: Popover | undefined;

afterEach(() => {
    popover?.destroy();
    popover = undefined;
    document.body.innerHTML = '';
});

test('shows the content of the trigger that opened it inside the viewport, titled by its own title', async () => {
    document.body.innerHTML = `
        <button data-popover-trigger="p1" data-value="a">A</button>
        <button data-popover-trigger="p1" data-value="b">B</button>
        <div data-popover-positioner="p1">
            <div data-popover-viewport="p1" hidden>
                <div data-popover-content="p1" data-value="a" hidden><div data-popover-title="p1">Title A</div></div>
                <div data-popover-content="p1" data-value="b" hidden><div data-popover-title="p1">Title B</div></div>
            </div>
        </div>`;
    popover = new Popover({ id: 'p1' } as never);
    popover.init();
    const content = (value: string) =>
        document.querySelector<HTMLElement>(`[data-popover-content][data-value="${value}"]`)!;

    document.querySelector<HTMLElement>('[data-popover-trigger][data-value="b"]')!.click();

    await expect.poll(() => content('b').dataset.state).toBe('open');
    expect(content('b').hidden).toBe(false);
    expect(content('a').hidden).toBe(true);
    expect(document.querySelector<HTMLElement>('[data-popover-viewport]')!.hidden).toBe(false);
    const titleB = content('b').querySelector('[data-popover-title]')!;
    expect(titleB.id).not.toBe(content('a').querySelector('[data-popover-title]')!.id);
    expect(content('b').getAttribute('aria-labelledby')).toBe(titleB.id);
});
