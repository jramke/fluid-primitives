import type { Params, Scope } from '@zag-js/core';
import { getFieldElement } from '../../Field/src/field.value';
import * as dom from './form.dom';
import { trimArraySuffix } from './form.path';
import { getFieldMachinesFor, renameFieldMachineForForm } from './form.registry';
import type { FormErrors, FormSchema } from './form.types';
import { getCurrentErrorForField, validateWithValidation } from './form.validation';
import { createFormValues } from './form.values';

export { getFieldElement };

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

export function syncAllFieldMachines(scope: Scope) {
    for (const [, fieldMachine] of getRegisteredFieldMachines(scope)) {
        fieldMachine.send({ type: 'SYNC_FROM_DOM' });
    }
}

export function pruneStaleFieldMachines(scope: Scope) {
    const form = dom.getFormEl(scope);
    if (!form) return;
    const machines = getRegisteredFieldMachines(scope);
    for (const [name] of machines) {
        const stillThere = getFieldElement(form, name) != null;
        if (!stillThere) {
            machines.delete(name);
        }
    }
}

export function setFieldMachineErrors(scope: Scope, fieldName: string, errors: string[]) {
    getRegisteredFieldMachines(scope)
        .get(fieldName)
        ?.send({ type: 'SET_ERRORS', detail: { errors } });
}

export function distributeFieldErrors(scope: Scope, errors: FormErrors) {
    for (const [name, fieldMachine] of getRegisteredFieldMachines(scope)) {
        fieldMachine.send({
            type: 'SET_ERRORS',
            detail: { errors: errors[name]?.messages ?? [] },
        });
    }
}

export function resetFieldMachines(scope: Scope) {
    for (const [, fieldMachine] of getRegisteredFieldMachines(scope)) {
        fieldMachine.send({ type: 'RESET' });
    }
}

export function hasInvalidFieldMachines(scope: Scope) {
    return Array.from(getRegisteredFieldMachines(scope).values()).some(fieldMachine =>
        fieldMachine.context.get('invalid')
    );
}

export function getFirstInvalidFieldMachine(scope: Scope) {
    return Array.from(getRegisteredFieldMachines(scope)).find(([, fieldMachine]) =>
        fieldMachine.context.get('invalid')
    )?.[0];
}

export function renameFieldMachine(scope: Scope, oldName: string, newName: string) {
    renameFieldMachineForForm(dom.getFormEl(scope), oldName, newName);
}
