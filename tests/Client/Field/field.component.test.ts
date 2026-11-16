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

test('the control is also described by the error of its fieldset while that shows, not by the one of a nested fieldset', async () => {
    document.body.innerHTML = `
        <fieldset data-fieldset-root="fs1">
            <div data-fieldset-error-text="fs1" id="fs1-error" hidden>Pick one</div>
            <fieldset data-fieldset-root="fs2">
                <div data-fieldset-error-text="fs2" id="fs2-error">Nested</div>
                <div data-field-root="f1" data-name="email">
                    <input id="field:f1:control" data-field-control="f1" name="email">
                </div>
            </fieldset>
        </fieldset>`;
    // the field belongs to the closest fieldset, the error of the outer one is not its own
    const fieldsetError = document.getElementById('fs2-error')!;
    field = new Field({ id: 'f1', name: 'email' });
    field.init();
    const email = document.querySelector('input')!;

    await vi.waitFor(() => expect(email.getAttribute('aria-describedby')).toBe('fs2-error'));

    fieldsetError.hidden = true;
    await vi.waitFor(() => expect(email.hasAttribute('aria-describedby')).toBe(false));
});

test('without a Form the first invalid field takes the focus the suppressed browser bubble would have given', async () => {
    document.body.innerHTML = `
        <form>
            <div data-field-root="f1" data-name="name">
                <input id="field:f1:control" data-field-control="f1" name="name" value="Ada">
            </div>
            <div data-field-root="f2" data-name="email">
                <input id="field:f2:control" data-field-control="f2" name="email" required>
            </div>
            <div data-field-root="f3" data-name="phone">
                <input id="field:f3:control" data-field-control="f3" name="phone" required>
            </div>
        </form>`;
    const fields = ['name', 'email', 'phone'].map((name, i) =>
        new Field({ id: `f${i + 1}`, name, required: name !== 'name' }).init()
    );
    field = fields[0];

    document.querySelector('form')!.checkValidity();

    await vi.waitFor(() =>
        expect(document.activeElement).toBe(document.getElementsByName('email')[0])
    );
    fields.slice(1).forEach(f => f.destroy());
});

test('validate gets the values of the whole form, to compare with another field', async () => {
    document.body.innerHTML = `
        <form>
            <input name="password" value="secret">
            <div data-field-root="f1" data-name="repeat">
                <input id="field:f1:control" data-field-control="f1" name="repeat" value="secrets">
            </div>
        </form>`;
    const validate = vi.fn(({ value, formValues }) =>
        value === formValues.password ? null : 'Does not match'
    );
    field = new Field({ id: 'f1', name: 'repeat', validate } as never);
    field.init();

    field.api.validate();

    await vi.waitFor(() => expect(field!.api.invalid).toBe(true));
    expect(validate.mock.calls[0][0].formValues).toEqual({ password: 'secret', repeat: 'secrets' });
});
