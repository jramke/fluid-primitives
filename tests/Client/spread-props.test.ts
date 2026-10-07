import { describe, expect, test } from 'vitest';
import { spreadTextControlProps } from '../../Resources/Private/Client/src/lib/spread-props';

/** Records what scripts assign; `type` mimics the user typing and is not recorded. */
function watchValue(el: HTMLInputElement | HTMLTextAreaElement) {
    const writes: string[] = [];
    const proto = el instanceof HTMLInputElement ? HTMLInputElement : HTMLTextAreaElement;
    const descriptor = Object.getOwnPropertyDescriptor(proto.prototype, 'value')!;
    Object.defineProperty(el, 'value', {
        configurable: true,
        get() {
            return descriptor.get!.call(this);
        },
        set(next: string) {
            writes.push(next);
            descriptor.set!.call(this, next);
        },
    });
    return { writes, type: (text: string) => descriptor.set!.call(el, text) };
}

describe('spreadTextControlProps', () => {
    test('assigns a changed value only when the control shows something else, and never resets typing', () => {
        for (const el of [document.createElement('input'), document.createElement('textarea')]) {
            el.value = 'abc';
            const { writes, type } = watchValue(el);
            const spread = (value: string | undefined) =>
                spreadTextControlProps(el, { value, 'data-state': 'idle' }, 'scope');

            // the browser has to keep treating what the user typed as typed
            spread('abc');
            type('abcd');
            spread('abcd');
            expect(writes).toEqual([]);
            expect(el.getAttribute('data-state')).toBe('idle');

            // the control is a keystroke ahead of the machine, whose value did not change
            type('abcde');
            spread('abcd');
            expect(el.value).toBe('abcde');

            spread('programmatic');
            spread(undefined);
            expect(writes).toEqual(['programmatic', '']);
        }
    });
});
