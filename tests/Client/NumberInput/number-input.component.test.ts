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

test('announces the changes the machine makes with a change event, but not the typing', async () => {
    document.body.innerHTML = `
        <div data-number-input-root="n1">
            <input data-number-input-input="n1" type="text" value="5" />
        </div>`;
    numberInput = new NumberInput({
        id: 'n1',
        defaultValue: '5',
        formatOptions: { style: 'currency', currency: 'USD' },
    } as never);
    numberInput.init();
    const input = document.querySelector<HTMLInputElement>('input')!;
    let inputEvents = 0;
    let changeEvents = 0;
    document.body.addEventListener('input', () => inputEvents++);
    document.body.addEventListener('change', () => changeEvents++);
    const settle = () => new Promise(resolve => setTimeout(resolve, 50));

    input.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));

    // the Field hears the user's own input event, an extra one would only re-enter the machine
    type(input, '3');
    await settle();
    expect(inputEvents).toBe(1);
    expect(changeEvents).toBe(0);

    // the machine rewrites the text without an event of its own, and the step must survive it
    input.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true, cancelable: true })
    );
    await expect.poll(() => input.value).toBe('$4.00');
    await settle();
    expect(input.value).toBe('$4.00');
    expect(changeEvents).toBe(1);
    expect(inputEvents).toBe(1);

    input.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
    await settle();
    expect(input.value).toBe('$4.00');
    expect(inputEvents).toBe(1);
});

test('a change listener reads the text the machine wrote', async () => {
    document.body.innerHTML = `
        <div data-number-input-root="n1">
            <input data-number-input-input="n1" type="text" value="5" />
        </div>`;
    numberInput = new NumberInput({
        id: 'n1',
        defaultValue: '5',
        formatOptions: { style: 'currency', currency: 'USD' },
    } as never);
    numberInput.init();
    const input = document.querySelector<HTMLInputElement>('input')!;
    const read: string[] = [];
    document.body.addEventListener('change', () => read.push(input.value));
    const settle = () => new Promise(resolve => setTimeout(resolve, 80));

    input.dispatchEvent(new FocusEvent('focusin', { bubbles: true }));
    input.dispatchEvent(
        new KeyboardEvent('keydown', { key: 'ArrowUp', bubbles: true, cancelable: true })
    );
    await settle();
    expect(read).toEqual(['$6.00']);

    // blur formats what was typed, the listener must not see it half way
    type(input, '3');
    input.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
    await settle();
    expect(read).toEqual(['$6.00', '$3.00']);
});
