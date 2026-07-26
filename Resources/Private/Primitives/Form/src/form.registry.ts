import type { Machine } from '../../../Client';
import { normalizeFieldName } from './form.path';

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
        entry.fields.set(normalizeFieldName(fieldMachine.prop('name')), fieldMachine);

        // trigger initial form render when all fields are registered
        if (entry.fields.size === entry.expectedFieldCount) {
            // notify is marked as private but that does not prevent runtime access
            // @ts-expect-error
            entry.machine.notify();
        } else if (entry.fields.size > entry.expectedFieldCount) {
            // Support dynamically added fields (e.g. recurring array rows added client-side)
            // @ts-expect-error
            entry.machine.notify?.();
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

/**
 * Re-keys a registered field machine under a new field name and updates its
 * `name` prop, without touching its value/touched/dirty/error state. This is
 * how recurring-field rows stay contiguously indexed after removing a row in
 * the middle: later rows are renamed down by one instead of copying values
 * between DOM elements, which keeps working regardless of what kind of
 * control (native input, Select, NumberInput, ...) the field wraps.
 */
export function renameFieldMachineForForm(el: Element | null, oldName: string, newName: string) {
    const form = resolveElToForm(el);
    if (!form) return;

    const entry = registry.get(form);
    if (!entry) return;

    const normalizedOldName = normalizeFieldName(oldName);
    const fieldMachine = entry.fields.get(normalizedOldName);
    if (!fieldMachine) return;

    entry.fields.delete(normalizedOldName);
    fieldMachine.updateProps({ name: newName });
    entry.fields.set(normalizeFieldName(newName), fieldMachine);
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
