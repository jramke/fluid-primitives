import { afterEach, expect, test } from 'vitest';
import { RadioGroup } from '../../../Resources/Private/Primitives/RadioGroup/RadioGroup';

let instance: RadioGroup | undefined;

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
});

test('submits an empty value while nothing is checked and the checked value alone otherwise', async () => {
    document.body.innerHTML = `
        <form><div role="radiogroup" data-radio-group-root="r1">
            <input type="hidden" name="color" value="" data-empty-value="color" data-radio-group-empty-value-input="r1">
            <label data-radio-group-item="r1" data-value="red">
                <input type="radio" name="color" value="red" data-radio-group-item-hidden-input="r1" data-value="red">
            </label>
        </div></form>`;
    const entries = () => [...new FormData(document.querySelector('form')!).entries()];

    instance = new RadioGroup({ id: 'r1', name: 'color' });
    instance.init();
    expect(entries()).toEqual([['color', '']]);

    instance.api.setValue('red');
    await expect.poll(entries).toEqual([['color', 'red']]);
});
