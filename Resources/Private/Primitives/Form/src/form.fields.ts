import { MachineStatus, type Params, type Scope } from '@zag-js/core';
import { isInsideField } from '../../Field/src/field.dom';
import { getFieldElement } from '../../Field/src/field.value';
import * as dom from './form.dom';
import { trimArraySuffix } from './form.path';
import { getFieldMachinesFor, renameFieldMachineForForm } from './form.registry';
import type { FormErrors, FormSchema } from './form.types';
import { getCurrentErrorForField, validateWithValidation } from './form.validation';
import { createFormValues } from './form.values';

export function getFormData(scope: Scope) {
    const form = dom.getFormEl(scope);
    return form ? new FormData(form) : new FormData();
}

/**
 * The messages the form has for one field right now: a server error whose captured value still
 * matches the field's current value, else what `validation` (schema or callback) reports for it.
 */
export function getFieldMessages(
    form: Pick<Params<FormSchema>, 'prop' | 'refs' | 'scope'>,
    fieldName: string
): string[] {
    const normalizedFieldName = trimArraySuffix(fieldName);
    const values = createFormValues(getFormData(form.scope));

    const serverError = getCurrentErrorForField(
        form.refs.get('serverErrors'),
        normalizedFieldName,
        values
    );
    if (serverError) return serverError.messages;

    const validation = form.prop('validation');
    if (!validation) return [];

    return (
        validateWithValidation(validation, values, normalizedFieldName)[normalizedFieldName]
            ?.messages ?? []
    );
}

export function getRegisteredFieldMachines(scope: Scope) {
    return getFieldMachinesFor(dom.getFormEl(scope));
}

/** A field counts while its own root is in the DOM; it may have no named control (an empty combobox). */
export function pruneStaleFieldMachines(scope: Scope) {
    const machines = getRegisteredFieldMachines(scope);
    for (const [name, fieldMachine] of machines) {
        if (!fieldMachine.refs.get('rootEl')?.isConnected) machines.delete(name);
    }
}

export function sendToFieldMachines(scope: Scope, type: string) {
    for (const fieldMachine of getRegisteredFieldMachines(scope).values()) {
        fieldMachine.send({ type });
    }
}

/**
 * Resolves once the events just sent to the fields were handled (a machine handles `send` in a
 * microtask) and no field is validating anymore, so an async validator can still block a submit.
 */
export async function settleFieldMachines(scope: Scope) {
    await Promise.resolve();

    const validating = () =>
        Array.from(getRegisteredFieldMachines(scope).values()).filter(
            fieldMachine =>
                fieldMachine.service.getStatus() === MachineStatus.Started &&
                fieldMachine.context.get('validating')
        );

    for (let pending = validating(); pending.length > 0; pending = validating()) {
        await new Promise<void>(resolve => {
            const unsubscribe = pending.map(fieldMachine =>
                fieldMachine.subscribe(() => {
                    if (fieldMachine.context.get('validating')) return;
                    unsubscribe.forEach(stop => stop());
                    resolve();
                })
            );
        });
    }
}

export function hasInvalidFieldMachines(scope: Scope) {
    return Array.from(getRegisteredFieldMachines(scope).values()).some(fieldMachine =>
        fieldMachine.computed('invalid')
    );
}

export function getFirstInvalidField(scope: Scope) {
    return Array.from(getRegisteredFieldMachines(scope)).find(([, fieldMachine]) =>
        fieldMachine.computed('invalid')
    );
}

const FOCUSABLE = [
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"]):not([aria-hidden="true"])',
    'select:not([disabled]):not([aria-hidden="true"])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

/**
 * Moves the focus into an invalid field: to the control carrying its name (the hidden select of a
 * Select and the hidden input of a Combobox hand it on to their visible control) and, when that
 * cannot take focus (a Slider's input is `display: none`), to the first thing inside the field that can.
 */
export function focusField(form: HTMLFormElement, name: string, fieldMachine: FieldMachine) {
    const rootEl = fieldMachine.refs.get('rootEl');
    const target = getFieldElement(form, name);

    target?.focus();
    if (target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement) {
        target.select();
    }

    if (rootEl && !isInsideField(rootEl, rootEl.ownerDocument.activeElement)) {
        rootEl.querySelector<HTMLElement>(FOCUSABLE)?.focus();
    }
}

/** Errors for names no registered field owns (a hidden input, say) still make the form invalid. */
export function hasUnownedErrors(scope: Scope, errors: FormErrors) {
    const fieldMachines = getRegisteredFieldMachines(scope);
    return Object.keys(errors).some(name => !fieldMachines.has(name));
}

export function renameFieldMachine(scope: Scope, oldName: string, newName: string) {
    renameFieldMachineForForm(dom.getFormEl(scope), oldName, newName);
}
