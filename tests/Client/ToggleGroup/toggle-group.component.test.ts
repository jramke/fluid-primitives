import { afterEach, expect, test } from 'vitest';
import { ToggleGroup } from '../../../Resources/Private/Primitives/ToggleGroup/ToggleGroup';

let group: ToggleGroup | undefined;
const changes: string[][] = [];

afterEach(() => {
    group?.destroy();
    group = undefined;
    changes.length = 0;
    document.body.innerHTML = '';
});

function mountGroup(props: Partial<ConstructorParameters<typeof ToggleGroup>[0]> = {}) {
    document.body.innerHTML = `
        <div role="group" data-toggle-group-root="g1">
            <button type="button" aria-pressed="true" data-pressed data-toggle-group-item="g1" data-value="left">Left</button>
            <button type="button" aria-pressed="false" data-toggle-group-item="g1" data-value="center">Center</button>
            <button type="button" disabled aria-pressed="false" data-disabled data-toggle-group-item="g1" data-value="right">Right</button>
        </div>`;
    group = new ToggleGroup({
        id: 'g1',
        defaultValue: ['left'],
        onValueChange: ({ value }) => changes.push(value),
        ...props,
    });
    group.init();
    const items = [...document.querySelectorAll<HTMLElement>('[data-toggle-group-item]')];
    return {
        items,
        pressed: () =>
            items.filter(el => el.hasAttribute('data-pressed')).map(el => el.dataset.value),
    };
}

test('presses one item at a time, deselects it again and ignores a disabled item', async () => {
    const [left, center, right] = mountGroup().items;

    center.click();
    await expect.poll(() => group!.api.value).toEqual(['center']);
    expect(center.getAttribute('aria-pressed')).toBe('true');
    expect(left.getAttribute('aria-pressed')).toBe('false');

    center.click();
    await expect.poll(() => group!.api.value).toEqual([]);
    expect(center.hasAttribute('data-pressed')).toBe(false);

    // the disabled item is hydrated as disabled, not just rendered like it
    expect(right.hasAttribute('disabled')).toBe(true);
    right.click();
    left.click();
    await expect.poll(() => changes).toContainEqual(['left']);
    expect(changes).not.toContainEqual(['right']);
});

test('never goes empty when it is not deselectable and presses several items when multiple', async () => {
    const single = mountGroup({ deselectable: false });
    single.items[0].click();
    single.items[1].click();
    await expect.poll(() => changes).toContainEqual(['center']);
    expect(changes).not.toContainEqual([]);
    group!.destroy();

    const multiple = mountGroup({ multiple: true });
    multiple.items[1].click();
    await expect.poll(() => multiple.pressed()).toEqual(['left', 'center']);
});
