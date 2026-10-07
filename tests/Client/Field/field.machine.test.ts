import { afterEach, describe, expect, test, vi } from 'vitest';
import { Machine } from '../../../Resources/Private/Client/src/lib/machine';
import { FIELD_VALUE_CHANGE_EVENT } from '../../../Resources/Private/Primitives/Field/src/field.dom';
import { machine } from '../../../Resources/Private/Primitives/Field/src/field.machine';
import type {
    FieldProps,
    FieldSchema,
} from '../../../Resources/Private/Primitives/Field/src/field.types';

let running: Machine<FieldSchema>[] = [];

function mountField(html: string, props: Partial<FieldProps> = {}) {
    document.body.innerHTML = `
        <form><div data-field-root="f1">${html}</div></form>
        <button id="outside">outside</button>
    `;
    const field = new Machine(machine, { id: 'f1', name: 'email', ...props });
    field.start();
    running.push(field);
    return field;
}

const input = () => document.querySelector('input')!;

/** Lets the debounce timer fire and the queued machine events run. */
const settle = (ms = 150) => vi.advanceTimersByTimeAsync(ms);

function type(el: HTMLInputElement, value: string) {
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
}

function focusOut(from: Element, to: Element | null) {
    from.dispatchEvent(new FocusEvent('focusout', { bubbles: true, relatedTarget: to }));
}

afterEach(() => {
    running.forEach(field => field.stop());
    running = [];
    vi.useRealTimers();
});

describe('field events on the root', () => {
    test('a typed value is read once it settles, then marks the field dirty and filled', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" value="">');

        type(input(), 'a@b.c');
        expect(field.context.get('dirty')).toBe(false);

        await settle();
        expect(field.context.get('dirty')).toBe(true);
        expect(field.context.get('filled')).toBe(true);

        type(input(), '');
        await settle();
        expect(field.context.get('dirty')).toBe(false);
        expect(field.context.get('filled')).toBe(false);
    });

    test('a blur flushes a pending edit first, so the blur commit already sees it', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" value="">', {
            validate: ({ value }) => (value === 'bad' ? 'not an email' : null),
        });

        type(input(), 'bad');
        focusOut(input(), document.getElementById('outside'));
        await settle(0);

        expect(field.context.get('errors')).toEqual(['not an email']);
        expect(field.context.get('touched')).toBe(true);
    });

    test('a blur of a pristine field is recorded but commits nothing', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" value="">', {
            validate: () => 'always wrong',
        });

        focusOut(input(), document.getElementById('outside'));
        await settle(0);

        expect(field.context.get('touched')).toBe(true);
        expect(field.context.get('errors')).toEqual([]);
        expect(field.computed('valid')).toBeNull();
    });

    test('focus moving inside the field, or into a portaled part of a component in it, is no blur', async () => {
        vi.useFakeTimers();
        const field = mountField(
            '<div data-select-root="s1"><button id="trigger">t</button></div><input name="email">'
        );
        const portal = document.createElement('div');
        portal.innerHTML =
            '<div data-select-content="s1"><span id="item" tabindex="-1"></span></div>';
        document.body.append(portal);
        const trigger = document.getElementById('trigger')!;

        focusOut(trigger, input());
        focusOut(trigger, document.getElementById('item'));
        await settle(0);
        expect(field.context.get('touched')).toBe(false);

        focusOut(trigger, document.getElementById('outside'));
        await settle(0);
        expect(field.context.get('touched')).toBe(true);
    });

    test('a programmatic checkbox change announced by a synthetic click counts as a change', async () => {
        vi.useFakeTimers();
        const field = mountField('<input type="checkbox" name="email" value="1">');

        input().checked = true;
        input().dispatchEvent(new Event('click', { bubbles: true }));
        await settle();

        expect(field.context.get('dirty')).toBe(true);
    });

    test('the native invalid event is suppressed and commits the field as invalid', async () => {
        vi.useFakeTimers();
        const field = mountField('<input id="field:f1:control" name="email" required>', {
            required: true,
            validationMode: 'onSubmit',
        });

        const invalid = new Event('invalid', { cancelable: true });
        input().dispatchEvent(invalid);
        await settle(0);

        expect(invalid.defaultPrevented).toBe(true);
        expect(field.computed('invalid')).toBe(true);
        expect(field.context.get('validity')?.valueMissing).toBe(true);
    });

    test('stopping the machine removes the listeners', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" value="">');
        field.stop();

        type(input(), 'x');
        await settle();

        expect(field.context.get('dirty')).toBe(false);
    });
});

describe('field validity', () => {
    test('native flags come from the control, valueMissing from the field and its value', async () => {
        vi.useFakeTimers();
        const field = mountField('<input id="field:f1:control" name="email" type="email">', {
            required: true,
        });

        input().value = 'nope';
        field.send({ type: 'VALIDATE' });
        await settle(0);
        expect(field.context.get('validity')).toMatchObject({
            typeMismatch: true,
            valueMissing: false,
        });

        input().value = '';
        field.send({ type: 'VALIDATE' });
        await settle(0);
        expect(field.context.get('validity')).toMatchObject({
            typeMismatch: false,
            valueMissing: true,
        });
    });

    test('a group without a native control is still validated, required meaning at least one', async () => {
        vi.useFakeTimers();
        const field = mountField(
            '<input type="checkbox" name="needs[]" value="a"><input type="checkbox" name="needs[]" value="b">',
            { name: 'needs[]', required: true }
        );
        const [first] = Array.from(document.querySelectorAll('input'));

        field.send({ type: 'VALIDATE' });
        await settle(0);
        expect(field.computed('invalid')).toBe(true);

        first.checked = true;
        field.send({ type: 'VALIDATE' });
        await settle(0);
        expect(field.computed('invalid')).toBe(false);
        expect(field.computed('valid')).toBe(true);
    });

    test('an error that is showing revalidates on every change and clears once fixed', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" value="">', {
            validate: ({ value }) => (value.includes('@') ? null : 'needs an @'),
        });

        field.send({ type: 'VALIDATE' });
        await settle(0);
        expect(field.context.get('errors')).toEqual(['needs an @']);

        type(input(), 'a@b.c');
        await settle();
        expect(field.context.get('errors')).toEqual([]);
        expect(field.computed('valid')).toBe(true);
    });

    test("validate runs at the mode's commit points, not on every settled change", async () => {
        vi.useFakeTimers();
        const validate = vi.fn(() => null);
        mountField('<input id="field:f1:control" name="email" value="">', { validate });

        type(input(), 'a');
        await settle();
        type(input(), 'ab');
        await settle();
        expect(validate).not.toHaveBeenCalled();

        focusOut(input(), document.getElementById('outside'));
        await settle(0);
        expect(validate).toHaveBeenCalledOnce();
    });

    test('an async result is dropped when the value changed while it was pending', async () => {
        vi.useFakeTimers();
        const field = mountField('<input id="field:f1:control" name="email" value="">', {
            validate: ({ value }) =>
                new Promise(resolve => setTimeout(() => resolve(`${value} is taken`), 200)),
        });

        type(input(), 'taken');
        focusOut(input(), document.getElementById('outside'));
        await settle(50);
        expect(field.context.get('validating')).toBe(true);

        // no error is showing yet, so this edit does not commit; the pending result is for old text
        type(input(), 'free');
        await settle(500);

        expect(field.context.get('errors')).toEqual([]);
        expect(field.context.get('validating')).toBe(false);
    });

    test('a read-only required field is not missing a value', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" readonly>', {
            required: true,
            readOnly: true,
        });

        field.send({ type: 'VALIDATE' });
        await settle(0);

        expect(field.computed('invalid')).toBe(false);
    });
});

describe('field baseline', () => {
    test('BASELINE re-measures the starting value until the user changed the field', async () => {
        vi.useFakeTimers();
        const field = mountField('<input type="checkbox" name="email" value="1">');

        // a primitive finishing hydration checks the box after the field already started
        input().checked = true;
        field.send({ type: 'BASELINE' });
        await settle(0);
        expect(field.context.get('filled')).toBe(true);

        input().checked = false;
        input().dispatchEvent(new Event('change', { bubbles: true }));
        await settle();
        expect(field.context.get('dirty')).toBe(true);

        // once edited, the baseline is what the user started from and stays put
        input().checked = true;
        field.send({ type: 'BASELINE' });
        input().dispatchEvent(new Event('change', { bubbles: true }));
        await settle();
        expect(field.context.get('dirty')).toBe(false);
    });
});

describe('field reset', () => {
    test('a form reset is pristine again against the starting value, however late the control restores it', async () => {
        vi.useFakeTimers();
        const field = mountField('<input name="email" value="start">');

        type(input(), 'edited');
        input().setAttribute('value', 'edited');
        await settle();
        expect(field.context.get('dirty')).toBe(true);

        // like NumberInput: the native reset restores the stale attribute, then the primitive
        // announces its reset and only a few frames later writes the real value back
        document.querySelector('form')!.addEventListener('reset', () => {
            setTimeout(() => input().dispatchEvent(new Event('input', { bubbles: true })), 20);
            setTimeout(() => (input().value = 'start'), 40);
        });
        document.querySelector('form')!.reset();
        await settle();

        expect(input().value).toBe('start');
        expect(field.context.get('dirty')).toBe(false);
        expect(field.context.get('touched')).toBe(false);
        expect(field.context.get('filled')).toBe(true);
    });
});

describe('field value announcements', () => {
    test('the root announces a settled value change once, not for events that change nothing', async () => {
        vi.useFakeTimers();
        mountField('<input name="email" value="">');
        const announced: string[] = [];
        document
            .querySelector('[data-field-root]')!
            .addEventListener(FIELD_VALUE_CHANGE_EVENT, event => {
                announced.push((event as CustomEvent<{ value: string }>).detail.value);
            });

        type(input(), 'a');
        type(input(), 'ab');
        await settle();
        input().dispatchEvent(new Event('change', { bubbles: true }));
        await settle();
        type(input(), 'abc');
        await settle();

        expect(announced).toEqual(['ab', 'abc']);
    });
});
