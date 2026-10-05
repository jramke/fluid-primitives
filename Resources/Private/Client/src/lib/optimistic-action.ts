const DEFAULT_COOLDOWN_MS = 250;

export type OptimisticMode = 'queue' | 'parallel' | 'latest';

export interface OptimisticCommitContext {
    signal: AbortSignal;
    /** Stable per intent - identical on every retry, so the server can dedupe on it. */
    idempotencyKey: string;
    /** 1 for the first attempt, incremented on every retry. */
    attempt: number;
}

/** Read-only snapshot of one tracked intent, as exposed in views and callbacks. */
export interface OptimisticRecord<Intent, Failure = unknown> {
    id: string;
    intent: Intent;
    /** `pending` while queued/in flight, `failed` once kept after an error. */
    status: 'pending' | 'failed';
    attempts: number;
    error?: Failure;
    /** Element that had focus when `run()` was called (may be gone by the time it's used). */
    origin: Element | null;
}

export interface OptimisticView<State, Intent> {
    /** Confirmed state with every pending and kept-failed intent applied on top. */
    state: State;
    pending: readonly OptimisticRecord<Intent>[];
    failed: readonly OptimisticRecord<Intent>[];
}

export type OptimisticOutcome<Result = unknown> =
    | { status: 'confirmed'; result: Result }
    | { status: 'failed'; error: unknown; kept: boolean }
    | { status: 'aborted' }
    /** Dropped by the cooldown; never applied or sent. */
    | { status: 'ignored' };

export interface OptimisticErrorContext<Intent> {
    id: string;
    intent: Intent;
    /** The thrown value or the failed result returned by `commit`. */
    error: unknown;
    /** HTTP status of a failed result when it has one, otherwise `undefined`. */
    status: number | undefined;
    attempts: number;
    origin: Element | null;
    /**
     * Drops the intent so the view recomputes without it (the default when none of the three
     * decisions is made synchronously). `cascade` also drops every later intent that was still
     * queued/in flight behind it.
     */
    rollback(options?: { cascade?: boolean }): void;
    /** Leaves the intent applied but marked `failed`, e.g. to render "failed - retry?". */
    keep(): void;
    /** Re-runs `commit` for this same intent (same idempotency key). */
    retry(): void;
}

export interface OptimisticActionOptions<State, Intent, Result> {
    /** The confirmed (server) state. */
    initial: State;
    /** Pure. Applies an intent to a state; run immediately and again on every recompute. */
    apply: (state: State, intent: Intent) => State;
    /** Does the real work. Reject, or resolve to a failed result (see `isFailure`), to fail. */
    commit: (intent: Intent, context: OptimisticCommitContext) => Promise<Result>;
    /** Merges the server result into the confirmed state; `state` already has the intent applied. */
    reconcile?: (state: State, result: Result, intent: Intent) => State;
    /**
     * Treats a resolved result as a failure. Defaults to `result.ok === false`, which is what
     * `extbase.request` resolves to for HTTP/network failures.
     */
    isFailure?: (result: Result) => boolean;
    /** Decide what happens on failure. Without a synchronous decision the intent is rolled back. */
    onError?: (context: OptimisticErrorContext<Intent>) => void;
    /**
     * What to do when `run()` is called while another intent is unsettled.
     * - `queue` (default): commits one at a time, in order.
     * - `parallel`: commits immediately and independently.
     * - `latest`: aborts the in-flight commit and replaces it with the newest intent.
     */
    mode?: OptimisticMode;
    /** Returning `false` skips the optimistic apply: the intent is only applied once confirmed. */
    guard?: (intent: Intent, state: State) => boolean;
    /**
     * Ignores `run()` calls made within this many ms of the last accepted one - protects against a
     * held Enter key or a double click re-triggering on content that moved under the pointer.
     * `0` disables. Default 250.
     */
    cooldownMs?: number;
    /**
     * Asks the browser to confirm leaving the page while an intent is unsettled, since the user has
     * already seen the optimistic result but would never see an error. `true` (default), `false`,
     * or a function deciding per call from the unsettled intents.
     */
    warnOnUnload?: boolean | ((pending: readonly OptimisticRecord<Intent>[]) => boolean);
    onSubscribeError?: (error: unknown) => void;
}

interface Tracked<Intent, Result> {
    id: string;
    intent: Intent;
    status: 'pending' | 'failed';
    optimistic: boolean;
    committing: boolean;
    attempts: number;
    error?: unknown;
    origin: Element | null;
    key: string;
    controller: AbortController | null;
    waiters: Array<(outcome: OptimisticOutcome<Result>) => void>;
}

function createId(): string {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

function defaultIsFailure(result: unknown): boolean {
    return (
        typeof result === 'object' &&
        result !== null &&
        'ok' in result &&
        (result as { ok: unknown }).ok === false
    );
}

function statusOf(error: unknown): number | undefined {
    if (typeof error === 'object' && error !== null && 'status' in error) {
        const status = (error as { status: unknown }).status;
        if (typeof status === 'number') return status;
    }
    return undefined;
}

/**
 * Generic optimistic-update helper. Holds a confirmed state plus a list of unsettled intents; the
 * visible state is always `intents.reduce(apply, confirmed)`, so rolling one back never disturbs
 * later ones. No DOM or Extbase knowledge - pair it with `extbase.request` as `commit`.
 *
 * Opt-in: nothing happens until you construct one and call `run()`. Call `destroy()` on teardown.
 */
export class OptimisticAction<State, Intent, Result = unknown> {
    private readonly options: OptimisticActionOptions<State, Intent, Result>;
    private readonly mode: OptimisticMode;
    private readonly cooldownMs: number;

    private confirmed: State;
    private tracked: Tracked<Intent, Result>[] = [];
    private listeners = new Set<(view: OptimisticView<State, Intent>) => void>();
    private lastRunAt = Number.NEGATIVE_INFINITY;
    private unloadListening = false;
    private destroyed = false;

    constructor(options: OptimisticActionOptions<State, Intent, Result>) {
        const cooldown = options.cooldownMs ?? DEFAULT_COOLDOWN_MS;
        if (!Number.isFinite(cooldown) || cooldown < 0) {
            throw new RangeError(
                `OptimisticAction: cooldownMs must be a finite number >= 0, got ${cooldown}.`
            );
        }
        this.options = options;
        this.mode = options.mode ?? 'queue';
        this.cooldownMs = cooldown;
        this.confirmed = options.initial;
    }

    /** Current visible state: confirmed state plus every unsettled intent applied. */
    get state(): State {
        return this.computeState();
    }

    get view(): OptimisticView<State, Intent> {
        const records = this.tracked.map(t => this.toRecord(t));
        return {
            state: this.computeState(),
            pending: records.filter(r => r.status === 'pending'),
            failed: records.filter(r => r.status === 'failed'),
        };
    }

    /** Fires after every change with the new view. Does not fire immediately. */
    subscribe(fn: (view: OptimisticView<State, Intent>) => void): () => void {
        this.listeners.add(fn);
        return () => {
            this.listeners.delete(fn);
        };
    }

    /**
     * Applies the intent optimistically and commits it. Resolves once it settles: confirmed, failed
     * (rolled back or kept), aborted, or ignored by the cooldown.
     */
    run(intent: Intent): Promise<OptimisticOutcome<Result>> {
        if (this.destroyed) return Promise.resolve({ status: 'aborted' });

        const now = performance.now();
        if (now - this.lastRunAt < this.cooldownMs) {
            return Promise.resolve({ status: 'ignored' });
        }
        this.lastRunAt = now;

        const optimistic = this.options.guard
            ? this.options.guard(intent, this.computeState())
            : true;

        if (this.mode === 'latest') {
            for (const t of [...this.tracked]) {
                if (t.status === 'pending') this.drop(t, { status: 'aborted' });
            }
        }

        const item: Tracked<Intent, Result> = {
            id: createId(),
            intent,
            status: 'pending',
            optimistic,
            committing: false,
            attempts: 0,
            origin: typeof document === 'undefined' ? null : document.activeElement,
            key: createId(),
            controller: null,
            waiters: [],
        };
        const promise = this.wait(item);
        this.tracked.push(item);
        this.changed();
        this.pump();
        return promise;
    }

    /** Re-runs a kept-failed intent with its original idempotency key. */
    retry(id: string): Promise<OptimisticOutcome<Result>> {
        const item = this.tracked.find(t => t.id === id && t.status === 'failed');
        if (!item || this.destroyed) return Promise.resolve({ status: 'aborted' });
        const promise = this.wait(item);
        this.requeue(item);
        this.changed();
        this.pump();
        return promise;
    }

    /** Drops a kept-failed (or still pending, aborting it) intent without retrying. */
    discard(id: string): void {
        const item = this.tracked.find(t => t.id === id);
        if (!item) return;
        this.drop(item, item.status === 'failed' ? { status: 'failed', error: item.error, kept: false } : { status: 'aborted' });
        this.changed();
        this.pump();
    }

    destroy(): void {
        if (this.destroyed) return;
        this.destroyed = true;
        for (const t of [...this.tracked]) this.drop(t, { status: 'aborted' });
        this.listeners.clear();
        this.syncUnloadGuard();
    }

    // -- internals ---------------------------------------------------------------------------

    private wait(item: Tracked<Intent, Result>): Promise<OptimisticOutcome<Result>> {
        return new Promise(resolve => item.waiters.push(resolve));
    }

    private settle(item: Tracked<Intent, Result>, outcome: OptimisticOutcome<Result>): void {
        const waiters = item.waiters;
        item.waiters = [];
        for (const resolve of waiters) resolve(outcome);
    }

    private requeue(item: Tracked<Intent, Result>): void {
        item.status = 'pending';
        item.error = undefined;
    }

    /** Removes an item entirely: aborts an in-flight commit, settles its waiters. */
    private drop(item: Tracked<Intent, Result>, outcome: OptimisticOutcome<Result>): void {
        this.tracked = this.tracked.filter(t => t !== item);
        item.controller?.abort();
        item.controller = null;
        item.committing = false;
        this.settle(item, outcome);
    }

    private computeState(): State {
        return this.tracked.reduce(
            (state, t) => (t.optimistic ? this.options.apply(state, t.intent) : state),
            this.confirmed
        );
    }

    private toRecord(t: Tracked<Intent, Result>): OptimisticRecord<Intent> {
        return {
            id: t.id,
            intent: t.intent,
            status: t.status,
            attempts: t.attempts,
            error: t.error,
            origin: t.origin,
        };
    }

    private changed(): void {
        this.syncUnloadGuard();
        if (this.listeners.size === 0) return;
        const view = this.view;
        for (const fn of [...this.listeners]) {
            try {
                fn(view);
            } catch (error) {
                if (this.options.onSubscribeError) this.options.onSubscribeError(error);
                else console.error(error);
            }
        }
    }

    /** Starts whatever may start now according to `mode`. */
    private pump(): void {
        if (this.destroyed) return;
        if (this.mode === 'queue') {
            if (this.tracked.some(t => t.committing)) return;
            const next = this.tracked.find(t => t.status === 'pending');
            if (next) this.start(next);
            return;
        }
        for (const t of this.tracked) {
            if (t.status === 'pending' && !t.committing) this.start(t);
        }
    }

    private start(item: Tracked<Intent, Result>): void {
        item.committing = true;
        item.attempts += 1;
        const controller = new AbortController();
        item.controller = controller;

        let promise: Promise<Result>;
        try {
            promise = Promise.resolve(
                this.options.commit(item.intent, {
                    signal: controller.signal,
                    idempotencyKey: item.key,
                    attempt: item.attempts,
                })
            );
        } catch (error) {
            promise = Promise.reject(error);
        }

        promise.then(
            result => {
                if (item.controller !== controller) return; // dropped/superseded meanwhile
                const failed = (this.options.isFailure ?? defaultIsFailure)(result);
                if (failed) this.fail(item, result);
                else this.succeed(item, result);
            },
            error => {
                if (item.controller !== controller) return;
                this.fail(item, error);
            }
        );
    }

    private succeed(item: Tracked<Intent, Result>, result: Result): void {
        this.tracked = this.tracked.filter(t => t !== item);
        item.controller = null;
        item.committing = false;
        let next = this.options.apply(this.confirmed, item.intent);
        if (this.options.reconcile) next = this.options.reconcile(next, result, item.intent);
        this.confirmed = next;
        this.changed();
        this.settle(item, { status: 'confirmed', result });
        this.pump();
    }

    private fail(item: Tracked<Intent, Result>, error: unknown): void {
        item.controller = null;
        item.committing = false;
        item.error = error;

        let decision: 'rollback' | 'keep' | 'retry' | null = null;
        let cascade = false;
        const decide = (d: 'rollback' | 'keep' | 'retry') => {
            if (decision === null) decision = d;
        };

        this.options.onError?.({
            id: item.id,
            intent: item.intent,
            error,
            status: statusOf(error),
            attempts: item.attempts,
            origin: item.origin,
            rollback: opts => {
                if (decision === null) cascade = opts?.cascade === true;
                decide('rollback');
            },
            keep: () => decide('keep'),
            retry: () => decide('retry'),
        });

        const outcome = (kept: boolean): OptimisticOutcome<Result> => ({
            status: 'failed',
            error,
            kept,
        });

        if (decision === 'keep') {
            item.status = 'failed';
            this.changed();
            this.settle(item, outcome(true));
        } else if (decision === 'retry') {
            this.requeue(item);
            this.changed();
        } else {
            if (cascade) {
                const index = this.tracked.indexOf(item);
                for (const later of this.tracked.slice(index + 1)) {
                    if (later.status === 'pending') this.drop(later, { status: 'aborted' });
                }
            }
            this.tracked = this.tracked.filter(t => t !== item);
            this.changed();
            this.settle(item, outcome(false));
        }
        this.pump();
    }

    // -- leave-page guard --------------------------------------------------------------------

    private readonly onBeforeUnload = (event: BeforeUnloadEvent): void => {
        if (!this.shouldWarn()) return;
        event.preventDefault();
        // Legacy browsers only show the prompt when returnValue is set.
        event.returnValue = '';
    };

    private shouldWarn(): boolean {
        const pending = this.tracked.filter(t => t.status === 'pending').map(t => this.toRecord(t));
        if (pending.length === 0) return false;
        const option = this.options.warnOnUnload ?? true;
        return typeof option === 'function' ? option(pending) : option;
    }

    private syncUnloadGuard(): void {
        if (typeof window === 'undefined') return;
        const needed = !this.destroyed && this.tracked.some(t => t.status === 'pending');
        if (needed && !this.unloadListening) {
            window.addEventListener('beforeunload', this.onBeforeUnload);
            this.unloadListening = true;
        } else if (!needed && this.unloadListening) {
            window.removeEventListener('beforeunload', this.onBeforeUnload);
            this.unloadListening = false;
        }
    }
}
