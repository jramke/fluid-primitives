import type { InputProps, Machine as MachineDefinition, MachineSchema } from '@zag-js/core';
import { VanillaMachine } from '@zag-js/vanilla';

/**
 * zag v2 requires `id` in a machine's props, but every caller passes what `splitProps()` returns,
 * typed `Partial` - `Component.initHydrator` is what actually guarantees the id at runtime.
 */
export class Machine<T extends MachineSchema> extends VanillaMachine<T> {
    constructor(machine: MachineDefinition<T>, userProps?: Partial<NonNullable<T['props']>>) {
        super(machine, userProps as InputProps<T>);
    }
}
