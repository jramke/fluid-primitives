import { afterEach, describe, expect, test, vi } from 'vitest';
import { Machine } from '../../../Resources/Private/Client/src/lib/machine';
import { machine as inputMachine } from '../../../Resources/Private/Primitives/Input/src/input.machine';
import { machine as textareaMachine } from '../../../Resources/Private/Primitives/Textarea/src/textarea.machine';

const translations = { wordCount: '%count% / %max% characters' };
const region = () => document.getElementById('__live-region-polite__');

let running: Machine<any>[] = [];

afterEach(() => {
    running.forEach(m => m.stop());
    running = [];
    document.body.innerHTML = '';
    vi.useRealTimers();
});

describe.each([
    ['Input', inputMachine],
    ['Textarea', textareaMachine],
])('%s word count announcements', (_name, definition) => {
    function start(props: Record<string, unknown>) {
        const m = new Machine(definition as any, { id: 'c1', ...props });
        m.start();
        running.push(m);
        return m;
    }

    test('announces the count text on the shared polite region once typing pauses', async () => {
        vi.useFakeTimers();
        const m = start({ maxLength: 250, translations });

        expect(region()?.textContent).toBe('');

        m.send({ type: 'VALUE_CHANGE', value: 'hello' });
        await vi.advanceTimersByTimeAsync(599);
        expect(region()?.textContent).toBe('');

        // Zag's own announce() schedules through the jsdom window's timers, which fake timers leave alone.
        await vi.advanceTimersByTimeAsync(1);
        await vi.waitFor(() => expect(region()?.textContent).toBe('5 / 250 characters'));
    });

    test.each([
        ['announce is false', { maxLength: 250, translations, announce: false }],
        ['there is no maxLength', { translations }],
        [
            'the word count translation is disabled',
            { maxLength: 250, translations: { wordCount: false } },
        ],
    ])('creates no live region when %s', async (_case, props) => {
        vi.useFakeTimers();
        const m = start(props);

        m.send({ type: 'VALUE_CHANGE', value: 'hello' });
        await vi.advanceTimersByTimeAsync(1000);

        expect(region()).toBeNull();
    });
});
