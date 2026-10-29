import { afterEach, expect, test } from 'vitest';
import { Checkbox } from '../../../Resources/Private/Primitives/Checkbox/Checkbox';

let instance: Checkbox | undefined;

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
});

test('submits an empty value while unchecked and the checkbox value alone while checked', async () => {
    document.body.innerHTML = `
        <form><label data-checkbox-root="c1">
            <input type="hidden" name="agree" value="" data-empty-value="agree" data-checkbox-empty-value-input="c1">
            <input type="checkbox" name="agree" value="1" data-checkbox-hidden-input="c1">
        </label></form>`;
    const entries = () => [...new FormData(document.querySelector('form')!).entries()];

    instance = new Checkbox({ id: 'c1', name: 'agree', value: '1' });
    instance.init();
    expect(entries()).toEqual([['agree', '']]);

    instance.api.setChecked(true);
    await expect.poll(entries).toEqual([['agree', '1']]);
});

test('keeps the name of its empty-value input on the name of the field after a rename', async () => {
    document.body.innerHTML = `
        <form><label data-checkbox-root="c1">
            <input type="hidden" name="rows[0][agree]" value="" data-empty-value="rows[0][agree]" data-checkbox-empty-value-input="c1">
            <input type="checkbox" name="rows[0][agree]" value="1" data-checkbox-hidden-input="c1">
        </label></form>`;
    const emptyValueInput = document.querySelector<HTMLInputElement>('[data-empty-value]')!;

    instance = new Checkbox({ id: 'c1', name: 'rows[0][agree]', value: '1' });
    instance.init();
    instance.updateProps({ name: 'rows[1][agree]' });

    await expect.poll(() => emptyValueInput.name).toBe('rows[1][agree]');
    expect(emptyValueInput.dataset.emptyValue).toBe('rows[1][agree]');
});
