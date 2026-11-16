import type { InputProps, Machine as MachineDefinition, MachineSchema } from '@zag-js/core';
import { VanillaMachine } from '@zag-js/vanilla';

/**
 * The Zag machine runtime that components run on. It takes `Partial` props: the `id` that Zag
 * requires is guaranteed at runtime by `Component.initHydrator()`.
 */
// every caller passes what `splitProps()` returns, typed `Partial`, and `updateProps` is typed the
// same way: it merges, so callers only pass what changed.

export class Machine<T extends MachineSchema> extends VanillaMachine<T> {
    constructor(machine: MachineDefinition<T>, userProps?: Partial<NonNullable<T['props']>>) {
        super(machine, userProps as InputProps<T>);
    }

    /** Changes props at runtime. Only the ones you pass change, the others stay. */
    updateProps(
        newProps: Partial<NonNullable<T['props']>> | InputProps<T> | (() => InputProps<T>)
    ) {
        super.updateProps(newProps as InputProps<T>);
    }

    /** Tells the subscribers to render again, without a prop change behind it. */
    notify() {
        // Zag's VanillaMachine has no public publish, but updateProps() publishes synchronously
        super.updateProps({} as InputProps<T>);
    }
}
