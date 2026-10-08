const DEFAULT_SHOW_DELAY_MS = 150;
const DEFAULT_MIN_VISIBLE_MS = 300;

function assertFiniteNonNegative(value: number, name: string): number {
    if (!Number.isFinite(value) || value < 0) {
        throw new RangeError(
            `DelayedIndicator: ${name} must be a finite number >= 0, got ${value}.`
        );
    }
    return value;
}

/** The options of a {@link DelayedIndicator}. */
export interface DelayedIndicatorOptions<T> {
    /**
     * How long a transient value must persist before it is shown at all. Must be finite and >= 0.
     * @default 150
     */
    showDelayMs?: number;
    /**
     * Once shown, how long a transient value stays current before a settled value can replace it.
     * Must be finite and >= 0.
     * @default 300
     */
    minVisibleMs?: number;
    /**
     * Marks which values are transient, e.g. a loading state. Every other value is treated as settled
     * and passes through immediately once no transient value is held.
     */
    isTransient: (value: T) => boolean;
    /** Called with each value once it is ready to be shown. */
    onChange: (value: T) => void;
}

/**
 * Delays showing a transient value long enough to avoid flicker, and keeps it visible for a minimum
 * duration once shown. Feed it every new value through `set()`, typically straight from a subscribe
 * callback, and it forwards them to `onChange`. The values `isTransient` marks are held back for
 * `showDelayMs`, so a fast operation never flashes them. Once shown they stay for at least
 * `minVisibleMs`, so they can't vanish the instant they appeared.
 *
 * It works on any value, not only a boolean: one instance can drive a spinner and a status text
 * from the same source, so they never disagree.
 */
// `onChange` is user-provided code that may itself call `set()` synchronously (e.g. to chain a
// follow-up state change) - every branch below updates this instance's own state (phase/timer/
// shownAt) before invoking `onChange`, so a reentrant `set()` call always sees consistent state.
export class DelayedIndicator<T> {
    private readonly showDelayMs: number;
    private readonly minVisibleMs: number;
    private readonly isTransient: (value: T) => boolean;
    private readonly onChange: (value: T) => void;

    private phase: 'idle' | 'waiting' | 'shown' = 'idle';
    // Always assigned as the very first statement of set(), before any timer that reads it could
    // possibly have been scheduled - so it's never actually read while unset.
    private latestValue!: T;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private shownAt = 0;

    constructor(options: DelayedIndicatorOptions<T>) {
        this.showDelayMs = assertFiniteNonNegative(
            options.showDelayMs ?? DEFAULT_SHOW_DELAY_MS,
            'showDelayMs'
        );
        this.minVisibleMs = assertFiniteNonNegative(
            options.minVisibleMs ?? DEFAULT_MIN_VISIBLE_MS,
            'minVisibleMs'
        );
        this.isTransient = options.isTransient;
        this.onChange = options.onChange;
    }

    /**
     * Passes a new value on, delayed as described above. It does not deduplicate: the same value
     * again can still call `onChange` again, as this filters state over time, it is not a
     * `distinctUntilChanged()`.
     *
     * @param value - The new value.
     */
    set(value: T): void {
        this.latestValue = value;
        const transient = this.isTransient(value);

        if (this.phase === 'idle') {
            if (!transient) {
                this.onChange(value);
                return;
            }
            this.phase = 'waiting';
            this.timer = setTimeout(() => {
                this.timer = null;
                this.phase = 'shown';
                this.shownAt = performance.now();
                this.onChange(this.latestValue);
            }, this.showDelayMs);
            return;
        }

        if (this.phase === 'waiting') {
            // Still transient - nothing to do, latestValue above is picked up when it shows.
            if (transient) return;

            // Never became visible - skip straight to the settled value, no flash.
            if (this.timer !== null) clearTimeout(this.timer);
            this.timer = null;
            this.phase = 'idle';
            this.onChange(value);
            return;
        }

        // phase === 'shown'
        if (transient) {
            // Already visible - refresh its content immediately (no new delay), and cancel a
            // stale hide that a since-superseded settled value may have scheduled.
            if (this.timer !== null) {
                clearTimeout(this.timer);
                this.timer = null;
            }
            this.onChange(value);
            return;
        }

        // A settled value arrived while a transient one is shown - hold it until minVisibleMs
        // has elapsed since the transient value first appeared, so it can't flicker off early.
        if (this.timer !== null) return; // already waiting to commit; latestValue above will be used when it fires

        const remaining = this.minVisibleMs - (performance.now() - this.shownAt);
        const commit = () => {
            this.timer = null;
            this.phase = 'idle';
            this.onChange(this.latestValue);
        };
        if (remaining > 0) {
            this.timer = setTimeout(commit, remaining);
        } else {
            commit();
        }
    }

    /**
     * Cancels any timer that is running. Call it when the indicator is no longer needed, e.g. on
     * teardown: otherwise a scheduled value can still reach `onChange` afterwards. It is safe to call
     * when nothing is transient or shown.
     */
    destroy(): void {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this.phase = 'idle';
    }
}
