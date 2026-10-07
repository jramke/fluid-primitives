import type { Machine } from '../../../Client';
import type { FieldSchema } from './field.types';

export type FieldMachine = Machine<FieldSchema>;

const registry = new WeakMap<HTMLElement, FieldMachine>();

export function registerFieldMachine(field: HTMLElement | null, service: FieldMachine) {
    if (!field) return;
    registry.set(field, service);
    field.dispatchEvent(new CustomEvent('fluid-primitives:field:registered', { bubbles: true }));
}

export function unregisterFieldMachine(field: HTMLElement) {
    registry.delete(field);
}

export function getFieldMachineFor(el: HTMLElement | null): FieldMachine | undefined {
    if (!el) return;
    return registry.get(el);
}
