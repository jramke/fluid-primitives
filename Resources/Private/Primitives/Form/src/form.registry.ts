import type { Machine } from '../../../Client';

export type FormMachine = Machine<any>;
export type FieldMachine = Machine<any>;

type RegistryEntry = {
	machine: FormMachine;
	fields: Map<string, FieldMachine>;
	expectedFieldCount: number;
};
const registry = new WeakMap<HTMLFormElement, RegistryEntry>();

export function registerFormMachine(form: HTMLFormElement | null, service: FormMachine) {
	if (!form) return;
	registry.set(form, {
		machine: service,
		fields: new Map(),
		expectedFieldCount:
			form.querySelectorAll('[data-scope="field"][data-part="root"]').length || 0,
	});
	form.dispatchEvent(new CustomEvent('fluid-primitives:form:registered', { bubbles: true }));
}

export function registerFieldMachineForForm(el: Element | null, fieldMachine: FieldMachine) {
	if (!el) return;
	const form = resolveElToForm(el);
	if (!form) return;

	const handleEntry = (entry: RegistryEntry) => {
		entry.fields.set(fieldMachine.prop('name'), fieldMachine);

		// trigger initial form render when all fields are registered
		if (entry.fields.size === entry.expectedFieldCount) {
			entry.machine.notify();
		}
	};

	const handler = () => {
		const entry = registry.get(form);
		if (!entry) return;
		handleEntry(entry);
		form.removeEventListener('fluid-primitives:form:registered', handler);
	};

	const entry = registry.get(form);
	if (entry) {
		handleEntry(entry);
	} else {
		form.addEventListener('fluid-primitives:form:registered', handler);
	}
}

export function unregisterFormMachine(form: HTMLFormElement) {
	registry.delete(form);
}

function resolveElToForm(el: Element | null): HTMLFormElement | null {
	if (!el) return null;
	if (el instanceof HTMLFormElement) return el;
	const form = el.closest('form') as HTMLFormElement | null;
	return form;
}

export function getFormMachineFor(el: Element | null): FormMachine | undefined {
	if (!el) return;
	const form = resolveElToForm(el);
	if (!form) return;
	return registry.get(form)?.machine;
}

export function getFieldMachinesFor(el: Element | null): Map<string, FieldMachine> {
	if (!el) return new Map();
	const form = resolveElToForm(el);
	if (!form) return new Map();
	const entry = registry.get(form);
	if (!entry) return new Map();
	return entry.fields;
}
