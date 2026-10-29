import * as combobox from '@zag-js/combobox';
import { afterEach, expect, test } from 'vitest';
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
