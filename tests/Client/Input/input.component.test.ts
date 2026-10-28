import { afterEach, describe, expect, test, vi } from 'vitest';
import { Field } from '../../../Resources/Private/Primitives/Field/Field';
import { Input } from '../../../Resources/Private/Primitives/Input/Input';
import { Textarea } from '../../../Resources/Private/Primitives/Textarea/Textarea';

const instances: Array<{ destroy(): void }> = [];

const translations = {
    wordCount: '%count% / %max% characters',
    wordCountOver: '%over% characters too many',
};

const controls = {
    input: {
        Component: Input,
        control: '<input id="field:f1:control" data-input-input="i1" name="bio" value="">',
    },
    textarea: {
        Component: Textarea,
        control:
            '<textarea id="field:f1:control" data-textarea-textarea="i1" name="bio"></textarea>',
    },
};

/** A control inside a Field as PHP renders them, hydrated in the given order. */
async function mount(
    kind: keyof typeof controls,
    maxLengthBehavior: string,
    order: 'field first' | 'control first' = 'field first'
) {
    const { Component, control } = controls[kind];
    document.body.innerHTML = `
        <div data-field-root="f1" data-name="bio">
            <div data-${kind}-root="i1">
                ${control}
                <span data-${kind}-word-count="i1"></span>
            </div>
            <div hidden data-field-error-text="f1"></div>
        </div>
        <button id="outside">outside</button>`;

    const hydrateField = () => {
        const field = new Field({ id: 'f1', name: 'bio' });
        field.init();
        instances.push(field);
        return field;
    };
    const hydrateControl = () => {
        const component = new Component({
            id: 'i1',
            // PHP maps the control id of the field onto the part of the primitive
            ids: { [kind]: 'field:f1:control' },
            maxLength: 3,
            maxLengthBehavior,
            translations,
        } as any);
        component.init();
        instances.push(component);
        return component;
    };

    const early = order === 'control first' ? hydrateControl() : undefined;
    const field = hydrateField();
    const component = early ?? hydrateControl();
    // the field measures its starting value once its machine has started
    await vi.advanceTimersByTimeAsync(50);
    return { field, component };
}

const control = () => document.querySelector<HTMLInputElement>('input, textarea')!;
const errorText = () => document.querySelector<HTMLElement>('[data-field-error-text]')!;

function type(value: string) {
    control().value = value;
    control().dispatchEvent(new Event('input', { bubbles: true }));
}

/** Types into the control and leaves it, then lets the field's debounce and the queued events run. */
async function typeAndLeave(value: string) {
    type(value);
    control().dispatchEvent(
        new FocusEvent('focusout', {
            bubbles: true,
            relatedTarget: document.getElementById('outside'),
        })
    );
    await vi.advanceTimersByTimeAsync(150);
}

afterEach(() => {
    instances.splice(0).forEach(instance => instance.destroy());
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe.each(['input', 'textarea'] as const)('a %s with a soft maxLength inside a Field', kind => {
    test.each(['field first', 'control first'] as const)(
        'invalid reports tooLong with the over text once the control is left and clears when fixed (%s)',
        async order => {
            vi.useFakeTimers();
            const { field } = await mount(kind, 'invalid', order);

            await typeAndLeave('abcde');

            expect(field.api.invalid).toBe(true);
            expect(field.api.validity?.tooLong).toBe(true);
            expect(field.api.errors).toEqual(['2 characters too many']);
            expect(errorText().hidden).toBe(false);
            expect(errorText().textContent).toBe('2 characters too many');
            // the control takes the verdict from its field, and the browser agrees
            expect(control().getAttribute('aria-invalid')).toBe('true');
            expect(control().validationMessage).toBe('2 characters too many');

            await typeAndLeave('abc');

            expect(field.api.invalid).toBe(false);
            expect(errorText().hidden).toBe(true);
            expect(control().hasAttribute('aria-invalid')).toBe(false);
        }
    );

    test('invalid says nothing to the field before it validates, only the count shows the excess', async () => {
        vi.useFakeTimers();
        const { field } = await mount(kind, 'invalid');

        type('abcde');
        await vi.advanceTimersByTimeAsync(150);

        expect(field.api.invalid).toBe(false);
        expect(control().hasAttribute('aria-invalid')).toBe(false);
        expect(document.querySelector(`[data-${kind}-word-count]`)!.textContent).toBe(
            '2 characters too many'
        );
    });

    test('warn leaves the field valid for the text that invalid rejects', async () => {
        vi.useFakeTimers();
        const { field } = await mount(kind, 'warn');

        await typeAndLeave('abcde');
        field.api.validate();
        await vi.advanceTimersByTimeAsync(0);

        expect(field.api.validity?.valid).toBe(true);
        expect(field.api.invalid).toBe(false);
        expect(control().hasAttribute('data-over-limit')).toBe(true);
    });

    test('destroying the control takes its constraint back from the field', async () => {
        vi.useFakeTimers();
        const { field, component } = await mount(kind, 'invalid');
        await typeAndLeave('abcde');
        expect(field.api.invalid).toBe(true);

        component.destroy();
        field.api.validate();
        await vi.advanceTimersByTimeAsync(0);

        expect(field.api.invalid).toBe(false);
    });
});
