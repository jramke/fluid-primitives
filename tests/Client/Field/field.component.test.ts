import { afterEach, expect, test, vi } from 'vitest';
import { Field } from '../../../Resources/Private/Primitives/Field/Field';

let field: Field | undefined;

afterEach(async () => {
    field?.destroy();
    field = undefined;
    document.dispatchEvent(new Event('pointerup'));
    await vi.advanceTimersByTimeAsync(0);
    vi.useRealTimers();
});

test('error text waits for a pressed pointer to be released, so the click on what moved is not lost', async () => {
    vi.useFakeTimers();
    document.body.innerHTML = `
        <div data-field-root="f1" data-name="email">
            <input id="field:f1:control" name="email" required>
            <div hidden data-field-error-text="f1"></div>
        </div>`;
    field = new Field({ id: 'f1', name: 'email', required: true });
    field.init();
    const errorText = document.querySelector<HTMLElement>('[data-field-error-text]')!;

    // pressing a button elsewhere blurs the field on mousedown
    document.dispatchEvent(new Event('pointerdown'));
    field.api.validate();
    await vi.advanceTimersByTimeAsync(20);

    expect(field.api.invalid).toBe(true);
    expect(errorText.hidden).toBe(true);
    expect(document.querySelector('[data-field-root]')!.hasAttribute('data-invalid')).toBe(true);

    document.dispatchEvent(new Event('pointerup'));
    await vi.advanceTimersByTimeAsync(20);

    expect(errorText.hidden).toBe(false);
    expect(errorText.textContent).not.toBe('');
});
