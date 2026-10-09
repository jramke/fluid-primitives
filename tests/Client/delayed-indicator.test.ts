import { afterEach, describe, expect, test, vi } from 'vitest';
import { DelayedIndicator } from '../../Resources/Private/Client/src/lib/delayed-indicator';

type Status = 'idle' | 'loading' | 'refreshing' | 'done';

function start(options: { showDelayMs?: number; minVisibleMs?: number } = {}) {
    vi.useFakeTimers();
    const shown: Status[] = [];
    const indicator = new DelayedIndicator<Status>({
        ...options,
        isTransient: status => status === 'loading' || status === 'refreshing',
        onChange: status => shown.push(status),
    });
    return { indicator, shown };
}

afterEach(() => {
    vi.useRealTimers();
});

describe('DelayedIndicator', () => {
    test('a transient value that settles within the show delay never appears', () => {
        const { indicator, shown } = start();

        indicator.set('idle');
        indicator.set('loading');
        vi.advanceTimersByTime(149);
        indicator.set('done');
        vi.advanceTimersByTime(1000);

        expect(shown).toEqual(['idle', 'done']);
    });

    test('a shown transient value stays for the minimum time before the settled one replaces it', () => {
        const { indicator, shown } = start();

        indicator.set('loading');
        vi.advanceTimersByTime(150);
        expect(shown).toEqual(['loading']);

        vi.advanceTimersByTime(100);
        indicator.set('done');
        vi.advanceTimersByTime(199);
        expect(shown).toEqual(['loading']);
        vi.advanceTimersByTime(1);
        expect(shown).toEqual(['loading', 'done']);

        // once it has been visible long enough, the settled value passes at once
        indicator.set('loading');
        vi.advanceTimersByTime(150 + 400);
        indicator.set('done');
        expect(shown).toEqual(['loading', 'done', 'loading', 'done']);
    });

    test('a transient value while one is shown refreshes it at once and cancels the pending hide', () => {
        const { indicator, shown } = start();

        indicator.set('loading');
        vi.advanceTimersByTime(250);
        indicator.set('done');
        indicator.set('refreshing');
        expect(shown).toEqual(['loading', 'refreshing']);

        vi.advanceTimersByTime(1000);
        expect(shown).toEqual(['loading', 'refreshing']);
    });

    test('destroy drops what is still scheduled, and the delays are validated', () => {
        const { indicator, shown } = start();

        indicator.set('loading');
        indicator.destroy();
        vi.advanceTimersByTime(1000);
        expect(shown).toEqual([]);

        for (const bad of [-1, Number.NaN, Number.POSITIVE_INFINITY]) {
            expect(
                () =>
                    new DelayedIndicator({
                        showDelayMs: bad,
                        isTransient: () => true,
                        onChange() {},
                    })
            ).toThrow(RangeError);
        }
    });
});
