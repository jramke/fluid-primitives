import { afterEach, expect, test } from 'vitest';
import { NumberInput } from '../../../Resources/Private/Primitives/NumberInput/NumberInput';

let numberInput: NumberInput | undefined;

afterEach(() => {
    numberInput?.destroy();
    numberInput = undefined;
    document.body.innerHTML = '';
});

const type = (input: HTMLInputElement, value: string) => {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
};

test('formats the value on blur, not while the user types', async () => {
    document.body.innerHTML = `
        <div data-number-input-root="n1">
            <input data-number-input-input="n1" type="text" value="9.99" />
        </div>`;
    numberInput = new NumberInput({
        id: 'n1',
        defaultValue: '9.99',
        formatOptions: { style: 'currency', currency: 'USD' },
    } as never);
    numberInput.init();
    const input = document.querySelector<HTMLInputElement>('input')!;

    // the server renders the value unformatted, the first render formats it
    expect(input.value).toBe('$9.99');

    input.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    type(input, '1');
    await new Promise(resolve => setTimeout(resolve, 50));
    expect(input.value).toBe('1');

    type(input, '12.5');
    await new Promise(resolve => setTimeout(resolve, 50));
    expect(input.value).toBe('12.5');

    input.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
    await expect.poll(() => input.value).toBe('$12.50');
});
