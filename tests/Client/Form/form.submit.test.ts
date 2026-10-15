import { afterEach, describe, expect, test, vi } from 'vitest';
import { extbase } from '../../../Resources/Private/Client/src/lib/extbase';
import { Field } from '../../../Resources/Private/Primitives/Field/Field';
import type { FieldProps } from '../../../Resources/Private/Primitives/Field/src/field.types';
import { Form } from '../../../Resources/Private/Primitives/Form/Form';
import type {
    FormErrors,
    FormProps,
} from '../../../Resources/Private/Primitives/Form/src/form.types';

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
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
    // @ts-expect-error back to a page without globals
    delete window.FluidPrimitives;
});

describe('submitting a form without onSubmit', () => {
    test('treats the submit as a success and says so in debug', async () => {
        vi.useFakeTimers();
        const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        window.FluidPrimitives = { globals: { debug: true } } as Window['FluidPrimitives'];
        const { form, submit } = mount(field('f-a', 'a', input('f-a', 'a')));

        submit();
        await settle();

        expect(form.machine.state.get()).toBe('success');
        expect(warn).toHaveBeenCalledWith(expect.stringContaining('Form has no onSubmit'));

        warn.mockClear();
        window.FluidPrimitives = { globals: { debug: false } } as Window['FluidPrimitives'];
        submit();
        await settle();
        expect(warn).not.toHaveBeenCalled();
    });
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
                    validation: ({ values }): FormErrors =>
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

    test('a field whose markup left the page no longer counts, though nobody destroyed it', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { submit } = mount(
            field('f-a', 'a', input('f-a', 'a', 'required')) +
                field('f-b', 'b', input('f-b', 'b', 'value="ok"')),
            { form: { onSubmit }, fields: { a: { required: true } } }
        );
        submit();
        await settle();
        expect(onSubmit).not.toHaveBeenCalled();

        document.querySelector('[data-field-root="f-a"]')!.remove();
        submit();
        await settle();

        expect(onSubmit).toHaveBeenCalledOnce();
    });

    test('an error the validation reports for a name no field owns still blocks the submit', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { form, submit } = mount(
            field('f-a', 'a', input('f-a', 'a', 'value="ok"')) +
                '<input type="hidden" name="token" value="stale">',
            {
                form: {
                    onSubmit,
                    validation: (): FormErrors => ({ token: { messages: ['The token expired'] } }),
                },
            }
        );

        submit();
        await settle();

        expect(onSubmit).not.toHaveBeenCalled();
        expect(form.machine.state.get()).toBe('invalid');
    });

    test('a required field without any named control, like an empty combobox, still blocks the submit', async () => {
        vi.useFakeTimers();
        const onSubmit = vi.fn(() => true);
        const { fields, submit } = mount(
            field('f-fruit', 'fruit', '<input id="field:f-fruit:control" required>'),
            { form: { onSubmit }, fields: { fruit: { required: true } } }
        );

        submit();
        await settle();

        expect(onSubmit).not.toHaveBeenCalled();
        expect(fields.get('fruit')!.api.invalid).toBe(true);
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

describe('posting a form with extbase', () => {
    function stubPost(status: number, body: object) {
        const posted: FormData[] = [];
        vi.stubGlobal('fetch', async (_url: string, init: RequestInit) => {
            posted.push(init.body as FormData);
            return new Response(JSON.stringify(body), { status });
        });
        return posted;
    }

    const send: FormProps['onSubmit'] = async ({ api, values }) => {
        await extbase.post('/save', values.toObject(), {
            prefix: api.getFieldNamePrefix(),
            objectName: api.getObjectName(),
        });
        return true;
    };

    test('the values go out under the names the Extbase action expects, a 422 lands on the fields', async () => {
        vi.useFakeTimers();
        const posted = stubPost(422, { 'event.title': ['too short'] });
        const { form, fields, submit } = mount(
            field('f-title', 'title', input('f-title', 'title', 'value="ab"')) +
                '<input type="hidden" name="tx_events_list[__trustedProperties]" value="signed">',
            { form: { objectName: 'event', fieldNamePrefix: 'tx_events_list', onSubmit: send } }
        );

        submit();
        await settle();

        // Extbase's own hidden field stays below the plugin prefix, the fields get prefix and object name
        expect([...posted[0].keys()]).toEqual([
            'tx_events_list[__trustedProperties]',
            'tx_events_list[event][title]',
        ]);
        expect(fields.get('title')!.api.errors).toEqual(['too short']);
        expect(form.machine.state.get()).toBe('invalid');
    });

    test('a response that is not a 422 leaves the result to onSubmit', async () => {
        vi.useFakeTimers();
        const posted = stubPost(200, {});
        const { form, submit } = mount(
            field('f-title', 'title', input('f-title', 'title', 'value="ok"')),
            { form: { objectName: 'event', onSubmit: send } }
        );

        submit();
        await settle();

        expect([...posted[0].keys()]).toEqual(['event[title]']);
        expect(form.machine.state.get()).toBe('success');
    });

    test('a 422 body about the object itself becomes the error text of the form, not a field error', async () => {
        vi.useFakeTimers();
        stubPost(422, { event: ['The event is full'] });
        const { form, fields, submit } = mount(
            field('f-title', 'title', input('f-title', 'title', 'value="ok"')),
            { form: { objectName: 'event', onSubmit: send } }
        );

        submit();
        await settle();

        expect(form.api.getErrorText()).toBe('The event is full');
        expect(form.machine.state.get()).toBe('error');
        expect(fields.get('title')!.api.errors).toEqual([]);
    });
});
