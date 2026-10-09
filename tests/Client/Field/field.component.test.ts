import { afterEach, expect, test, vi } from 'vitest';
import { Field } from '../../../Resources/Private/Primitives/Field/Field';

let field: Field | undefined;

afterEach(async () => {
    field?.destroy();
    field = undefined;
    document.dispatchEvent(new Event('pointerup'));
    if (vi.isFakeTimers()) await vi.advanceTimersByTimeAsync(0);
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

test('the control carries the name of its field and follows a rename of it, like a FieldArray row that moved up', () => {
    vi.useFakeTimers();
    document.body.innerHTML = `
        <div data-field-root="f1" data-name="people[2][name]">
            <input id="field:f1:control" data-field-control="f1" name="people[2][name]">
        </div>`;
    field = new Field({ id: 'f1', name: 'people[2][name]' });
    field.init();

    field.updateProps({ name: 'people[1][name]' });

    expect(document.querySelector('input')!.name).toBe('people[1][name]');
    expect(document.querySelector('[data-field-root]')!.getAttribute('data-name')).toBe(
        'people[1][name]'
    );
});

test('the control is described by its helper text and the error texts that show, never a hidden one', async () => {
    document.body.innerHTML = `
        <div data-field-root="f1" data-name="email">
            <input id="field:f1:control" data-field-control="f1" name="email" required>
            <p data-field-helper-text="f1">We never share it</p>
            <div hidden data-field-error-text="f1"></div>
            <div hidden data-field-error-text="f1" data-value="tooShort">Too short</div>
        </div>
        <div data-field-root="f2" data-name="phone">
            <input id="field:f2:control" data-field-control="f2" name="phone">
        </div>`;
    field = new Field({ id: 'f1', name: 'email', required: true });
    field.init();
    const phone = new Field({ id: 'f2', name: 'phone' });
    phone.init();
    const [email, phoneInput] = Array.from(document.querySelectorAll('input'));
    const describedBy = (expected: string) =>
        vi.waitFor(() => expect(email.getAttribute('aria-describedby')).toBe(expected));

    // these run on microtasks and animation frames, so they need real timers
    await describedBy('field:f1:helper-text');
    // no helper text in the markup, nothing to point to
    expect(phoneInput.hasAttribute('aria-describedby')).toBe(false);

    field.api.validate();

    await describedBy('field:f1:helper-text field:f1:error-text');
    phone.destroy();
});
