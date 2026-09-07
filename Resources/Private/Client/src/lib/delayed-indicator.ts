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

export interface DelayedIndicatorOptions<T> {
    /** How long a transient value must persist before it's actually shown. Must be finite and >= 0. */
    showDelayMs?: number;
    /**
     * Once shown, the minimum time a transient value stays current before a later value replaces
     * it. Must be finite and >= 0.
     */
    minVisibleMs?: number;
    /**
     * Marks which values are transient (e.g. a loading state) and so need the show-delay/
     * min-visible guarding below. Every other value is treated as settled and passes through
     * immediately once no transient value is being held.
     */
    isTransient: (value: T) => boolean;
    onChange: (value: T) => void;
}

/**
 * Feed it every new value (typically straight from a subscribe callback) via set() and it
 * forwards them to onChange with flicker protection applied to "transient" values only (per
 * isTransient): a transient value is held back for showDelayMs before onChange ever sees it, so a
 * fast operation never flashes it - and once shown, it's guaranteed to stay current for at least
 * minVisibleMs before a later, settled value is allowed through, so it can't flicker off again
 * the instant it appeared. Settled values that arrive with nothing transient/shown are forwarded
 * immediately. set() does not deduplicate - an unchanged value passed again may still result in
 * another onChange call, since this is a temporal filter on state, not a distinctUntilChanged.
 *
 * onChange is user-provided code that may itself call set() synchronously (e.g. to chain a
 * follow-up state change) - every branch below updates this instance's own state (phase/timer/
 * shownAt) before invoking onChange, so a reentrant set() call always sees consistent state.
 *
 * Call destroy() when the indicator is no longer needed (e.g. on component teardown) to cancel
 * any in-flight timer - otherwise a scheduled show/commit can still fire onChange afterward.
 *
 * Generalizes a plain boolean "is this spinner visible" (`isTransient: value => value === true`)
 * to any value - e.g. a small status union - so multiple pieces of UI (a spinner AND status text)
 * can be driven off one flicker-protected source and never disagree with each other.
 */
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

    /** Cancels any in-flight timer. Safe to call even if nothing is transient/shown. */
    destroy(): void {
        if (this.timer !== null) {
            clearTimeout(this.timer);
            this.timer = null;
        }
        this.phase = 'idle';
    }
}
