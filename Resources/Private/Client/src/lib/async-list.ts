import * as asyncList from '@zag-js/async-list';
import { Machine } from './machine';

export type AsyncListOptions<T, C = unknown> = asyncList.Props<T, C>;

/**
 * Wraps @zag-js/async-list's machine lifecycle (construct -> init -> subscribe -> destroy),
 * matching this library's "class initialization" pattern (e.g. `new Combobox(props); combobox.init();`)
 * - but with no DOM/hydration ties, since async-list has no rendering concerns of its own.
 * Consumers own their own DOM updates from inside subscribe().
 *
 * Debouncing/throttling setFilterText() is left to the caller - wrap the call site with
 * @zag-js/utils's own debounce/throttle (already a dependency of this library), the same way
 * you'd debounce any other callback. Keeping that out of this class means it's not tied to one
 * fixed shape or default, and callers who don't need it (e.g. dependencies-driven autoReload
 * only) don't pay for it either.
 */
export class AsyncList<T, C = unknown> {
    private machine: Machine<any>;

    constructor(options: AsyncListOptions<T, C>) {
        this.machine = new Machine(asyncList.machine, options);
    }

    /** The connected API for the machine's current snapshot - recomputed fresh on every access. */
    get api(): asyncList.Api<T, C> {
        return asyncList.connect<T, C>(this.machine.service);
    }

    /**
     * Subscribes to every future state change, handing the listener a freshly-connected `Api`
     * (not the raw zag `Service`). Does not fire immediately with the current snapshot - read
     * `.api` directly beforehand if you need it.
     */
    subscribe(fn: (api: asyncList.Api<T, C>) => void): () => void {
        return this.machine.subscribe(service => fn(asyncList.connect<T, C>(service)));
    }

    setFilterText(filterText: string): void {
        this.api.setFilterText(filterText);
    }

    /**
     * Passthrough to the underlying machine's `updateProps`, for changing e.g. `dependencies` at
     * runtime to trigger an `autoReload`. `load` is fixed at construction time and can't be
     * swapped here.
     */
    updateProps(newProps: Partial<Omit<asyncList.Props<T, C>, 'load'>>): void {
        this.machine.updateProps(newProps);
    }

    init(): void {
        this.machine.start();
    }

    destroy(): void {
        this.machine.stop();
    }
}
