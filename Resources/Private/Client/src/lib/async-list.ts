import * as asyncList from '@zag-js/async-list';
import { Machine } from './machine';

/** The options of an {@link AsyncList}: the props of the `@zag-js/async-list` machine. */
export type AsyncListOptions<
    T,
    Filter = string,
    Sorting = asyncList.SortDescriptor<T>,
    Cursor = string,
> = asyncList.Props<T, Filter, Sorting, Cursor>;

/**
 * Wraps the machine of `@zag-js/async-list` for use without any DOM or hydration: construct it, call
 * `init()`, `subscribe()` to state changes and `destroy()` when done. Rendering is yours, do it from
 * inside `subscribe()`.
 *
 * Debouncing or throttling `setFilter()` is left to the caller, wrap the call site with the
 * `debounce` or `throttle` of `@zag-js/utils`, which this library already depends on.
 */
export class AsyncList<T, Filter = string, Sorting = asyncList.SortDescriptor<T>, Cursor = string> {
    private machine: Machine<any>;

    constructor(options: AsyncListOptions<T, Filter, Sorting, Cursor>) {
        this.machine = new Machine(asyncList.machine, options);
    }

    /** The api of the current state of the list. It is read fresh on every access, don't hold on to it. */
    get api(): asyncList.Api<T, Filter, Sorting, Cursor> {
        return asyncList.connect<T, Filter, Sorting, Cursor>(this.machine.service);
    }

    /**
     * Calls `fn` with the api on every future state change. It does not fire for the current state,
     * read `api` first if you need it.
     *
     * @param fn - The listener.
     * @returns A function that removes the listener.
     */
    subscribe(fn: (api: asyncList.Api<T, Filter, Sorting, Cursor>) => void): () => void {
        return this.machine.subscribe(service =>
            fn(asyncList.connect<T, Filter, Sorting, Cursor>(service))
        );
    }

    /** Sets the filter and reloads the list with it. */
    setFilter(filter: Filter): void {
        this.api.setFilter(filter);
    }

    /**
     * Changes options at runtime, e.g. `dependencies` to trigger an `autoReload`. `load` is fixed
     * when the list is constructed and can't be swapped here.
     */
    updateProps(
        newProps: Partial<Omit<asyncList.Props<T, Filter, Sorting, Cursor>, 'load'>>
    ): void {
        this.machine.updateProps(newProps);
    }

    /** Starts the machine, which loads the list for the first time. Call it once, after constructing. */
    init(): void {
        this.machine.start();
    }

    /** Stops the machine and cancels a load that is in flight. */
    destroy(): void {
        this.machine.stop();
    }
}
