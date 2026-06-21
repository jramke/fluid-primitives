import type { Machine } from '../../../Client';

export type CheckboxGroupMachine = Machine<any>;

const registry = new WeakMap<HTMLElement, CheckboxGroupMachine>();

export function registerCheckboxGroup(root: HTMLElement | null, machine: CheckboxGroupMachine) {
    if (!root) return;
    registry.set(root, machine);
    root.dispatchEvent(
        new CustomEvent('fluid-primitives:checkbox-group:registered', { bubbles: true })
    );
}

export function unregisterCheckboxGroup(root: HTMLElement | null) {
    if (!root) return;
    registry.delete(root);
}

export function getCheckboxGroupMachineFor(
    el: HTMLElement | null
): CheckboxGroupMachine | undefined {
    if (!el) return undefined;
    const root = el.closest(
        '[data-scope="checkbox-group"][data-part="root"]'
    ) as HTMLElement | null;
    if (!root) return undefined;
    return registry.get(root);
}
