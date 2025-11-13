import type { Machine } from '../../../Client';

export type FormMachine = Machine<any>;

const registry = new WeakMap<HTMLFormElement, FormMachine>();

export function registerFormMachine(form: HTMLFormElement | null, service: FormMachine) {
	if (!form) return;
	registry.set(form, service);
}

export function unregisterFormMachine(form: HTMLFormElement) {
	registry.delete(form);
}

export function getFormMachineFor(el: Element | null): FormMachine | undefined {
	if (!el) return;
	const form =
		el instanceof HTMLFormElement ? el : (el.closest('form') as HTMLFormElement | null);
	if (!form) return;
	return registry.get(form);
}
