import type { Machine } from '../../../Client';
import type { TanstackForm } from './form.types';

export type FormMachine = Machine<any>;
export type FieldMachine = Machine<any>;

type RegistryEntry = {
	machine: FormMachine;
	/** The TanStack form instance backing this form. */
	getForm: () => TanstackForm | null;
	fields: Map<string, FieldMachine>;
	expectedFieldCount: number;
};
const registry = new WeakMap<HTMLFormElement, RegistryEntry>();

export const FORM_REGISTERED_EVENT = 'fluid-primitives:form:registered';

export function registerFormMachine(
	form: HTMLFormElement | null,
	machine: FormMachine,
	getForm: () => TanstackForm | null
) {
	if (!form) return;
	registry.set(form, {
		machine,
		getForm,
		fields: new Map(),
		expectedFieldCount:
			form.querySelectorAll('[data-scope="field"][data-part="root"]').length || 0,
	});
}

/**
 * Signals that the form's TanStack instance is created and ready. Fields wait
 * for this before mounting their own `FieldApi`. Dispatched separately from
 * registration because the machine creates the TanStack form lazily on start.
 */
export function markFormReady(form: HTMLFormElement | null) {
	if (!form) return;
	form.dispatchEvent(new CustomEvent(FORM_REGISTERED_EVENT, { bubbles: true }));
}

export function registerFieldMachineForForm(el: Element | null, fieldMachine: FieldMachine) {
	if (!el) return;
	const form = resolveElToForm(el);
	if (!form) return;

	const handleEntry = (entry: RegistryEntry) => {
		entry.fields.set(fieldMachine.prop('name'), fieldMachine);

		// trigger initial form render when all fields are registered
		if (entry.fields.size === entry.expectedFieldCount) {
			// notify is marked as private but that does not prevent runtime access
			// @ts-expect-error
			entry.machine.notify();
		}
	};

	const handler = () => {
		const entry = registry.get(form);
		if (!entry) return;
		handleEntry(entry);
		form.removeEventListener(FORM_REGISTERED_EVENT, handler);
	};

	const entry = registry.get(form);
	if (entry) {
		handleEntry(entry);
	} else {
		form.addEventListener(FORM_REGISTERED_EVENT, handler);
	}
}

export function unregisterFormMachine(form: HTMLFormElement) {
	registry.delete(form);
}

function resolveElToForm(el: Element | null): HTMLFormElement | null {
	if (!el) return null;
	if (el instanceof HTMLFormElement) return el;
	return el.closest('form') as HTMLFormElement | null;
}

export function getFormMachineFor(el: Element | null): FormMachine | undefined {
	if (!el) return;
	const form = resolveElToForm(el);
	if (!form) return;
	return registry.get(form)?.machine;
}

/** Returns the TanStack form instance for the form containing `el`. */
export function getTanstackFormFor(el: Element | null): TanstackForm | null {
	if (!el) return null;
	const form = resolveElToForm(el);
	if (!form) return null;
	return registry.get(form)?.getForm() ?? null;
}

export function getFieldMachinesFor(el: Element | null): Map<string, FieldMachine> {
	if (!el) return new Map();
	const form = resolveElToForm(el);
	if (!form) return new Map();
	return registry.get(form)?.fields ?? new Map();
}
