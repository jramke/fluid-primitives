import { normalizeProps } from '@zag-js/vanilla';
import { afterEach, describe, expect, test, vi } from 'vitest';
import { Machine } from '../../../Resources/Private/Client/src/lib/machine';
import { connect as connectInput } from '../../../Resources/Private/Primitives/Input/src/input.connect';
import { getLengthViolation } from '../../../Resources/Private/Primitives/Input/src/input.count';
import { machine as inputMachine } from '../../../Resources/Private/Primitives/Input/src/input.machine';
import { connect as connectTextarea } from '../../../Resources/Private/Primitives/Textarea/src/textarea.connect';
import { machine as textareaMachine } from '../../../Resources/Private/Primitives/Textarea/src/textarea.machine';

const translations = {
    wordCount: '%count% / %max% characters',
    wordCountOver: 'Characters over the limit: %over%',
};
const region = () => document.getElementById('__live-region-polite__');
// the machine handles an event on a microtask, not inside `send()`
const flush = () => new Promise<void>(resolve => setTimeout(resolve, 0));
const computed = (m: Machine<any>, key: string) => (m as any).computed(key);

// five code points, six UTF-16 units
const WITH_EMOJI = 'ab😀cd';

let running: Machine<any>[] = [];

afterEach(() => {
    running.forEach(m => m.stop());
    running = [];
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe.each([
    ['Input', inputMachine, connectInput],
    ['Textarea', textareaMachine, connectTextarea],
])('%s maxLengthBehavior', (_name, definition, connect) => {
    function start(props: Record<string, unknown>) {
        const m = new Machine(definition as any, { id: 'c1', ...props });
        m.start();
        running.push(m);
        return m;
    }

    async function change(m: Machine<any>, value: string) {
        m.send({ type: 'VALUE_CHANGE', value });
        await flush();
    }

    const api = (m: Machine<any>) => connect(m.service as any, normalizeProps);
    const controlProps = (m: Machine<any>) => {
        const connected = api(m) as any;
        return (connected.getInputProps?.() ?? connected.getTextareaProps()) as Record<string, any>;
    };

    test('block counts the units the browser enforces and is never over the limit', async () => {
        const m = start({ maxLength: 3, translations });

        await change(m, WITH_EMOJI);

        expect(computed(m, 'count')).toBe(6);
        expect(computed(m, 'over')).toBe(0);
        expect(computed(m, 'countText')).toBe('6 / 3 characters');
    });

    test.each(['warn', 'invalid'])(
        '%s counts code points, reports the excess and swaps the count text',
        async behavior => {
            const m = start({ maxLength: 3, maxLengthBehavior: behavior, translations });

            await change(m, WITH_EMOJI);
            expect(computed(m, 'count')).toBe(5);
            expect(computed(m, 'over')).toBe(2);
            expect(computed(m, 'countText')).toBe('Characters over the limit: 2');

            await change(m, 'ab');
            expect(computed(m, 'over')).toBe(0);
            expect(computed(m, 'countText')).toBe('2 / 3 characters');
        }
    );

    test('without an over text the count text stays what it was', async () => {
        const m = start({
            maxLength: 3,
            maxLengthBehavior: 'warn',
            translations: { wordCount: translations.wordCount },
        });

        await change(m, 'abcde');

        expect(computed(m, 'countText')).toBe('5 / 3 characters');
    });

    test('crossing the limit is announced like any other count change', async () => {
        vi.useFakeTimers();
        const m = start({ maxLength: 3, maxLengthBehavior: 'warn', translations });

        m.send({ type: 'VALUE_CHANGE', value: 'abcd' });
        await vi.advanceTimersByTimeAsync(600);

        await vi.waitFor(() => expect(region()?.textContent).toBe('Characters over the limit: 1'));
    });

    test('only block hands the limit to the native maxlength, so a paste is not cut off', () => {
        const attr = (behavior?: string) => {
            const props = controlProps(start({ maxLength: 3, maxLengthBehavior: behavior }));
            return props.maxlength ?? props.maxLength;
        };

        expect(attr('block')).toBe(3);
        expect(attr(undefined)).toBe(3);
        expect(attr('warn')).toBeUndefined();
        expect(attr('invalid')).toBeUndefined();
    });

    test('the over state is on the root, the control and the count, and only while it lasts', async () => {
        const m = start({ maxLength: 3, maxLengthBehavior: 'warn', translations });
        const parts = () => {
            const connected = api(m);
            return [
                connected.getRootProps(),
                controlProps(m),
                connected.getWordCountProps(),
            ] as Record<string, any>[];
        };

        expect(parts().every(props => !('data-over-limit' in props))).toBe(true);

        await change(m, 'abcd');
        expect(parts().every(props => props['data-over-limit'] === '')).toBe(true);

        await change(m, 'abc');
        expect(parts().every(props => !('data-over-limit' in props))).toBe(true);
    });

    test('every part carries the behavior while there is a maxLength, and says when the value is exactly at it', async () => {
        const partsOf = (m: Machine<any>) => {
            const connected = api(m);
            return [
                connected.getRootProps(),
                controlProps(m),
                connected.getWordCountProps(),
            ] as Record<string, any>[];
        };

        for (const behavior of ['block', 'warn', 'invalid']) {
            const m = start({ maxLength: 3, maxLengthBehavior: behavior });
            expect(partsOf(m).map(props => props['data-max-length-behavior'])).toEqual(
                Array(3).fill(behavior)
            );
            expect(partsOf(m).every(props => !('data-at-limit' in props))).toBe(true);

            await change(m, 'abc');
            expect(partsOf(m).every(props => props['data-at-limit'] === '')).toBe(true);

            await change(m, 'abcd');
            expect(partsOf(m).every(props => !('data-at-limit' in props))).toBe(true);
        }

        const withoutLimit = partsOf(start({ maxLengthBehavior: 'warn' }));
        expect(withoutLimit.every(props => !('data-max-length-behavior' in props))).toBe(true);
    });

    test('a lone control is invalid beyond the limit only for invalid, the prop still has the last word', async () => {
        const invalidAfterTyping = async (props: Record<string, unknown>) => {
            const m = start({ maxLength: 3, ...props });
            await change(m, 'abcd');
            return api(m).invalid;
        };

        expect(await invalidAfterTyping({ maxLengthBehavior: 'invalid' })).toBe(true);
        expect(await invalidAfterTyping({ maxLengthBehavior: 'warn' })).toBe(false);
        expect(await invalidAfterTyping({ maxLengthBehavior: 'block' })).toBe(false);
        // a field passes its own verdict down, which is false until it committed
        expect(await invalidAfterTyping({ maxLengthBehavior: 'invalid', invalid: false })).toBe(
            false
        );
    });
});

describe('the violation a field reports for a control', () => {
    const options = { maxLength: 3, translations };

    test('only invalid reports one, with the tooLong flag and the over text', () => {
        expect(getLengthViolation('abcde', { ...options, behavior: 'invalid' })).toEqual({
            match: 'tooLong',
            message: 'Characters over the limit: 2',
        });
        expect(getLengthViolation('abc', { ...options, behavior: 'invalid' })).toBeNull();
        expect(getLengthViolation('abcde', { ...options, behavior: 'warn' })).toBeNull();
        expect(getLengthViolation('abcde', { ...options, behavior: 'block' })).toBeNull();
    });
});
