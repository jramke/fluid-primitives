import { afterEach, describe, expect, test, vi } from 'vitest';
import {
    isPointerPressed,
    whenPointerReleased,
} from '../../../Resources/Private/Primitives/Field/src/field.pointer';

const fire = (type: string) => document.dispatchEvent(new Event(type));

afterEach(async () => {
    fire('pointerup');
    await vi.advanceTimersByTimeAsync(0);
    vi.useRealTimers();
});

describe('waiting for the pointer to be released', () => {
    test('runs at once while no button is down, and after the click of the release otherwise', async () => {
        vi.useFakeTimers();

        const now = vi.fn();
        whenPointerReleased(now);
        expect(now).toHaveBeenCalledOnce();

        fire('pointerdown');
        expect(isPointerPressed()).toBe(true);
        const later = vi.fn();
        const cancelled = vi.fn();
        whenPointerReleased(later);
        whenPointerReleased(cancelled)();

        // pointerup and the click run in one task: the layout must not move before they are done
        fire('pointerup');
        expect(later).not.toHaveBeenCalled();
        expect(isPointerPressed()).toBe(true);

        await vi.advanceTimersByTimeAsync(0);
        expect(later).toHaveBeenCalledOnce();
        expect(cancelled).not.toHaveBeenCalled();
        expect(isPointerPressed()).toBe(false);
    });

    test('does not wait forever for a release that never arrives', async () => {
        vi.useFakeTimers();

        fire('pointerdown');
        const callback = vi.fn();
        whenPointerReleased(callback);

        await vi.advanceTimersByTimeAsync(1400);
        expect(callback).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(200);
        expect(callback).toHaveBeenCalledOnce();
        expect(isPointerPressed()).toBe(false);
    });
});
