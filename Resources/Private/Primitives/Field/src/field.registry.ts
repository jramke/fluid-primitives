import type { Machine } from '../../../Client';

export type FieldMachine = Machine<any>;

const registry = new WeakMap<HTMLElement, FieldMachine>();

export function registerFieldMachine(field: HTMLElement | null, service: FieldMachine) {
	if (!field) return;
	registry.set(field, service);
	console.log('field registered', field);
	field.dispatchEvent(new CustomEvent('fluid-primitives:field:registered', { bubbles: true }));
}

export function unregisterFieldMachine(field: HTMLElement) {
	registry.delete(field);
}

export function getFieldMachineFor(el: HTMLElement | null): FieldMachine | undefined {
	if (!el) return;
	return registry.get(el);
}
