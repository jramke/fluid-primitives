const DEFAULT_SHOW_DELAY_MS = 100;
const DEFAULT_MIN_VISIBLE_MS = 200;

export interface DelayedIndicatorOptions<T> {
    /** How long a pending value must persist before it's actually shown. */
    showDelayMs?: number;
    /** Once shown, the minimum time a pending value stays current before a later value replaces it. */
    minVisibleMs?: number;
    /**
     * Marks which values count as transient/"pending" (e.g. a loading state) and so need the
     * show-delay/min-visible guarding below. Every other value is treated as settled and passes
     * through immediately once no pending value is being held.
     */
    isPending: (value: T) => boolean;
    onChange: (value: T) => void;
}

/**
 * Feed it every new value (typically straight from a subscribe callback) via set() and it
 * forwards them to onChange with flicker protection applied to "pending" values only (per
 * isPending): a pending value is held back for showDelayMs before onChange ever sees it, so a
 * fast operation never flashes it - and once shown, it's guaranteed to stay current for at least
 * minVisibleMs before a later, settled value is allowed through, so it can't flicker off again
 * the instant it appeared. Settled values that arrive with nothing pending/shown are forwarded
 * immediately.
 *
 * Generalizes a plain boolean "is this spinner visible" (`DelayedIndicator<boolean>` with
 * `isPending: v => v`) to any value - e.g. a small status union - so multiple pieces of UI (a
 * spinner AND status text) can be driven off one flicker-protected source and never disagree
 * with each other.
 */
export class DelayedIndicator<T> {
    private readonly showDelayMs: number;
    private readonly minVisibleMs: number;
    private readonly isPending: (value: T) => boolean;
    private readonly onChange: (value: T) => void;

    private phase: 'idle' | 'waiting' | 'shown' = 'idle';
    private latestValue: T | undefined;
    private timer: ReturnType<typeof setTimeout> | null = null;
    private shownAt = 0;

    constructor(options: DelayedIndicatorOptions<T>) {
        this.showDelayMs = options.showDelayMs ?? DEFAULT_SHOW_DELAY_MS;
        this.minVisibleMs = options.minVisibleMs ?? DEFAULT_MIN_VISIBLE_MS;
        this.isPending = options.isPending;
        this.onChange = options.onChange;
    }

    set(value: T): void {
        this.latestValue = value;
        const pending = this.isPending(value);

        if (this.phase === 'idle') {
            if (!pending) {
                this.onChange(value);
                return;
            }
            this.phase = 'waiting';
            this.timer = setTimeout(() => {
                this.timer = null;
                this.phase = 'shown';
                this.shownAt = Date.now();
                this.onChange(this.latestValue as T);
            }, this.showDelayMs);
            return;
        }

        if (this.phase === 'waiting') {
            // Still pending - nothing to do, latestValue above is picked up when it shows.
            if (pending) return;

            // Never became visible - skip straight to the settled value, no flash.
            if (this.timer !== null) clearTimeout(this.timer);
            this.timer = null;
            this.phase = 'idle';
            this.onChange(value);
            return;
        }

        // phase === 'shown'
        if (pending) {
            // Already visible - refresh its content immediately (no new delay), and cancel a
            // stale hide that a since-superseded settled value may have scheduled.
            if (this.timer !== null) {
                clearTimeout(this.timer);
                this.timer = null;
            }
            this.onChange(value);
            return;
        }

        // A settled value arrived while a pending one is shown - hold it until minVisibleMs has
        // elapsed since the pending value first appeared, so it can't flicker off early.
        if (this.timer !== null) return; // already waiting to commit; latestValue above will be used when it fires

        const remaining = this.minVisibleMs - (Date.now() - this.shownAt);
        const commit = () => {
            this.timer = null;
            this.phase = 'idle';
            this.onChange(this.latestValue as T);
        };
        if (remaining > 0) {
            this.timer = setTimeout(commit, remaining);
        } else {
            commit();
        }
    }
}
