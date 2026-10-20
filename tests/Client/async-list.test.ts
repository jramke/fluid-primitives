import { afterEach, expect, test, vi } from 'vitest';
import { AsyncList } from '../../Resources/Private/Client';

let list: AsyncList<string> | undefined;

afterEach(() => {
    list?.destroy();
    list = undefined;
});

test('init() returns the list and is what starts the first load', async () => {
    const load = vi.fn(async () => ({ items: ['a', 'b'] }));
    list = new AsyncList<string>({ load, autoReload: true });
    expect(load).not.toHaveBeenCalled();

    expect(list.init()).toBe(list);

    await vi.waitFor(() => expect(list!.api.items).toEqual(['a', 'b']));
    expect(load).toHaveBeenCalledTimes(1);
});
