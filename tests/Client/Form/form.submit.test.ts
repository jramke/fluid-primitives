import { afterEach, describe, expect, test, vi } from 'vitest';
import { Field } from '../../../Resources/Private/Primitives/Field/Field';
import type { FieldProps } from '../../../Resources/Private/Primitives/Field/src/field.types';
import { Form } from '../../../Resources/Private/Primitives/Form/Form';
import type { FormProps } from '../../../Resources/Private/Primitives/Form/src/form.types';

const instances: Array<{ destroy(): void }> = [];

/** Fields first, then the form - the order the pages hydrate in is not guaranteed either way. */
function mount(
    html: string,
    options: { form?: Partial<FormProps>; fields?: Record<string, Partial<FieldProps>> } = {}
) {
    document.body.innerHTML = `<form data-form-root="form1" novalidate>${html}</form>`;

    const fields = new Map<string, Field>();
    document.querySelectorAll<HTMLElement>('[data-field-root]').forEach(root => {
        const id = root.dataset.fieldRoot!;
        const name = root.dataset.name!;
        const field = new Field({ id, name, ...options.fields?.[name] });
        field.init();
        fields.set(name, field);
        instances.push(field);
    });

    const form = new Form({ id: 'form1', ...options.form });
    form.init();
    instances.push(form);

    const formEl = document.querySelector('form')!;
    const submit = () =>
        formEl.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    return { form, fields, formEl, submit };
}

const field = (id: string, name: string, control: string) =>
    `<div data-field-root="${id}" data-name="${name}">${control}</div>`;
const input = (id: string, name: string, attributes = '') =>
    `<input id="field:${id}:control" name="${name}" ${attributes}>`;

const settle = (ms = 200) => vi.advanceTimersByTimeAsync(ms);

afterEach(() => {
    instances.splice(0).forEach(instance => instance.destroy());
    vi.useRealTimers();
});

describe('submitting a form of fields', () => {
    test('native constraints block the submit, then the form is ready again once they are fixed', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { form, fields, submit } = mount(
            field('f-email', 'email', input('f-email', 'email', 'type="email" required')),
            { form: { onSubmit }, fields: { email: { required: true } } }
        );

        submit();
        await settle();

        expect(onSubmit).not.toHaveBeenCalled();
        expect(form.machine.state.get()).toBe('invalid');
        expect(fields.get('email')!.api.invalid).toBe(true);

        const emailEl = document.querySelector<HTMLInputElement>('input')!;
        emailEl.value = 'a@b.c';
        emailEl.dispatchEvent(new Event('input', { bubbles: true }));
        await settle();

        expect(fields.get('email')!.api.invalid).toBe(false);
        expect(form.machine.state.get()).toBe('ready');

        submit();
        await settle();
        expect(onSubmit).toHaveBeenCalledOnce();
    });

    test('the form validation reaches the fields through their validate and blocks the submit', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { fields, submit } = mount(
            field('f-name', 'name', input('f-name', 'name', 'value=""')),
            {
                form: {
                    onSubmit,
                    validation: ({ values }) =>
                        values.get('name')
                            ? {}
                            : { name: { messages: ['Please enter your name'] } },
                },
            }
        );

        submit();
        await settle();

        expect(onSubmit).not.toHaveBeenCalled();
        expect(fields.get('name')!.api.errors).toEqual(['Please enter your name']);
    });

    test('an async validator of a field holds the submit back until it settled', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { fields, submit } = mount(
            field('f-user', 'user', input('f-user', 'user', 'value="taken"')),
            { form: { onSubmit } }
        );
        fields.get('user')!.updateProps({
            validate: ({ value }) =>
                new Promise(resolve =>
                    setTimeout(() => resolve(value === 'taken' ? 'taken' : null), 500)
                ),
        });

        submit();
        await settle(100);
        expect(onSubmit).not.toHaveBeenCalled();
        expect(fields.get('user')!.api.validating).toBe(true);

        await settle(600);
        expect(onSubmit).not.toHaveBeenCalled();
        expect(fields.get('user')!.api.errors).toEqual(['taken']);
    });

    test('errors returned from onSubmit mark the field and go away once its value changes', async () => {
        vi.useFakeTimers();
        const { form, fields, submit } = mount(
            field('f-email', 'email', input('f-email', 'email', 'value="a@b.c"')),
            { form: { onSubmit: () => ({ email: { messages: ['already registered'] } }) } }
        );

        submit();
        await settle();
        expect(fields.get('email')!.api.errors).toEqual(['already registered']);
        expect(form.machine.state.get()).toBe('invalid');

        const emailEl = document.querySelector<HTMLInputElement>('input')!;
        emailEl.value = 'other@b.c';
        emailEl.dispatchEvent(new Event('input', { bubbles: true }));
        await settle();

        expect(fields.get('email')!.api.errors).toEqual([]);
        expect(form.machine.state.get()).toBe('ready');
    });

    test('a destroyed field no longer counts, however its last validation ended', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { fields, submit } = mount(
            field('f-a', 'a', input('f-a', 'a', 'required')) +
                field('f-b', 'b', input('f-b', 'b', 'value="ok"')),
            { form: { onSubmit }, fields: { a: { required: true } } }
        );

        submit();
        await settle();
        expect(onSubmit).not.toHaveBeenCalled();

        // its markup stays: only unregistering the machine keeps it from counting, not pruning by DOM
        fields.get('a')!.destroy();
        submit();
        await settle();

        expect(onSubmit).toHaveBeenCalledOnce();
    });

    test('a disabled field is exempt from validation', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { submit } = mount(field('f-a', 'a', input('f-a', 'a', 'required disabled')), {
            form: { onSubmit },
            fields: { a: { required: true, disabled: true } },
        });

        submit();
        await settle();

        expect(onSubmit).toHaveBeenCalledOnce();
    });
});
