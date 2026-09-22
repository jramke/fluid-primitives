import { createMachine } from '@zag-js/core';
import { debounce } from '@zag-js/utils';
import { trimArraySuffix } from '../../Form/src/form.path';
import {
    getFieldMachinesFor,
    getFormMachineFor,
    type FormMachine,
} from '../../Form/src/form.registry';
import { createFormValues } from '../../Form/src/form.values';
import * as dom from './field.dom';
import type { FieldDependencyChangeDetail, FieldSchema, FieldValue } from './field.types';
import { getCurrentFieldValue, getDefaultFieldValue, isFieldValueEqual } from './field.utils';

/**
 * Dispatched by `handleValueChange`/`handleBlur` at the exact point they each decide *this* field
 * needs to revalidate itself (blur while dirty, or a change while already invalid) - the signal
 * `listenTo` on a *different* field subscribes to, so a dependent field's own revalidation fires
 * under the same conditions the field it depends on already validates under, rather than on every
 * raw value change (e.g. `passwordConfirm` only re-checks when `password` itself would - on blur,
 * or on change once `password` already has an error - not on every keystroke).
 */
const FIELD_VALIDATED_EVENT = 'fluid-primitives:field:validated';

function notifyFieldValidated(rootEl: HTMLElement | null) {
    rootEl?.dispatchEvent(new CustomEvent(FIELD_VALIDATED_EVENT, { bubbles: true }));
}

export const machine = createMachine<FieldSchema>({
    initialState() {
        return 'ready';
    },
    context({ bindable, prop }) {
        return {
            invalid: bindable(() => ({ defaultValue: prop('invalid') ?? false })),
            required: bindable(() => ({ defaultValue: prop('required') ?? false })),
            disabled: bindable(() => ({ defaultValue: prop('disabled') ?? false })),
            readOnly: bindable(() => ({ defaultValue: prop('readOnly') ?? false })),
            formMachine: bindable(() => ({
                defaultValue: null as FormMachine | null,
            })),
            describeIds: bindable<string | undefined>(() => ({ defaultValue: undefined })),
            hasDescription: bindable(() => ({ defaultValue: false })),
            value: bindable(() => ({ defaultValue: getDefaultFieldValue(prop('defaultValue')) })),
            initialValue: bindable(() => ({
                defaultValue: getDefaultFieldValue(prop('defaultValue')),
            })),
            errors: bindable(() => ({ defaultValue: [] as string[] })),
            touched: bindable(() => ({ defaultValue: false })),
            dirty: bindable(() => ({ defaultValue: false })),
            blurred: bindable(() => ({ defaultValue: false })),
        };
    },
    entry: [
        'getFormMachine',
        'checkForDescription',
        'syncInitialValueFromDom',
        'determineDescribeIds',
        'updateInvalid',
        'setupFieldListeners',
        'setupDependencyListeners',
    ],
    states: {
        ready: {},
    },
    on: {
        VALUE_CHANGE: { actions: ['handleValueChange'] },
        FIELD_BLUR: { actions: ['handleBlur'] },
        SET_ERRORS: { actions: ['setErrors', 'updateInvalid', 'determineDescribeIds'] },
        CLEAR_ERRORS: { actions: ['clearErrors', 'updateInvalid', 'determineDescribeIds'] },
        RESET: {
            actions: ['resetField', 'updateInvalid', 'determineDescribeIds'],
        },
        SYNC_FROM_DOM: { actions: ['syncValueFromDom'] },
    },
    watch({ track, context, action }) {
        track([() => context.get('invalid'), () => context.get('hasDescription')], () => {
            action(['determineDescribeIds']);
        });
    },
    implementations: {
        actions: {
            getFormMachine({ context, scope }) {
                if (context.get('formMachine')) return;

                const fieldRootEl = dom.getRootEl(scope);
                if (!fieldRootEl) return;

                const formMachine = getFormMachineFor(fieldRootEl) ?? null;

                if (formMachine) {
                    context.set('formMachine', formMachine);
                } else {
                    const closestForm = fieldRootEl.closest('form');
                    if (!closestForm) return;

                    const handler = () => {
                        const fs = getFormMachineFor(fieldRootEl) ?? null;
                        context.set('formMachine', fs);
                        closestForm.removeEventListener(
                            'fluid-primitives:form:registered',
                            handler
                        );
                    };
                    closestForm.addEventListener('fluid-primitives:form:registered', handler);
                }
            },
            checkForDescription({ context, scope }) {
                const descriptionEl = dom.getDescriptionEl(scope);
                context.set('hasDescription', !!descriptionEl);
            },
            syncInitialValueFromDom({ context, scope, prop }) {
                const currentValue = getCurrentFieldValue(
                    scope,
                    prop('name'),
                    prop('defaultValue')
                );
                context.set('value', currentValue);
                context.set('initialValue', currentValue);
            },
            syncValueFromDom({ context, scope, prop }) {
                context.set(
                    'value',
                    getCurrentFieldValue(scope, prop('name'), prop('defaultValue'))
                );
            },
            determineDescribeIds({ context, scope }) {
                const ids: string[] = [];
                if (context.get('hasDescription')) {
                    ids.push(dom.getDescriptionId(scope));
                }
                if (context.get('invalid')) {
                    ids.push(dom.getErrorId(scope));
                }
                const idsStr = ids.join(' ') || undefined;
                context.set('describeIds', idsStr);
            },
            updateInvalid({ context, prop }) {
                context.set(
                    'invalid',
                    context.get('errors').length > 0 || (prop('invalid') ?? false)
                );
            },
            setErrors({ context, event }) {
                context.set('errors', [...(event.detail?.errors ?? [])]);
            },
            clearErrors({ context }) {
                context.set('errors', []);
            },
            handleValueChange({ context, prop, action, scope }) {
                action(['syncValueFromDom']);

                context.set('touched', true);
                context.set('dirty', true);

                if (context.get('errors').length > 0) {
                    context.get('formMachine')?.send({
                        type: 'VALIDATE_FIELD',
                        detail: { fieldName: prop('name') },
                    });
                    notifyFieldValidated(dom.getRootEl(scope));
                }
            },
            handleBlur({ context, prop, event, action, scope }) {
                const target = event.detail?.target as Element | null;
                const relatedTarget = event.detail?.relatedTarget ?? null;

                if (dom.isFocusMovingWithinSameField(target, relatedTarget)) {
                    return;
                }

                context.set('touched', true);
                context.set('blurred', true);
                action(['syncValueFromDom']);

                if (context.get('dirty')) {
                    context.get('formMachine')?.send({
                        type: 'VALIDATE_FIELD',
                        detail: { fieldName: prop('name') },
                    });
                    notifyFieldValidated(dom.getRootEl(scope));
                }
            },
            resetField({ context, scope, prop }) {
                const value = getCurrentFieldValue(scope, prop('name'), prop('defaultValue'));
                context.set('value', value);
                context.set('errors', []);
                context.set('touched', false);
                context.set('dirty', false);
                context.set('blurred', false);
            },
            setupFieldListeners({ scope, context, send }) {
                const rootEl = dom.getRootEl(scope);
                if (!rootEl) return;

                const debounceMs = context.get('formMachine')?.prop('inputDebounceMs') ?? 100;
                const debouncedValueChange =
                    debounceMs > 0
                        ? debounce((target: EventTarget | null) => {
                              send({ type: 'VALUE_CHANGE', detail: { target } });
                          }, debounceMs)
                        : (target: EventTarget | null) => {
                              send({ type: 'VALUE_CHANGE', detail: { target } });
                          };

                rootEl.addEventListener(
                    'input',
                    event => {
                        debouncedValueChange(event.target);
                    },
                    true
                );

                rootEl.addEventListener(
                    'change',
                    event => {
                        send({ type: 'VALUE_CHANGE', detail: { target: event.target } });
                    },
                    true
                );

                rootEl.addEventListener(
                    'focusout',
                    event => {
                        send({
                            type: 'FIELD_BLUR',
                            detail: {
                                target: event.target,
                                relatedTarget: event.relatedTarget,
                            },
                        });
                    },
                    true
                );
            },
            /**
             * `listenTo` support - subscribes this field to each named sibling field within the
             * same form, so this field can react when a *different* field changes (e.g. a
             * `password`/`passwordConfirm` pair, or a field whose visibility depends on another).
             * Two independent things happen, on two independent triggers:
             *
             * - `fluid-primitives:field:dependencychange` (for consumer DOM reactions, e.g.
             *   toggling another field's visibility) dispatches on *every* value change of a
             *   listened-to sibling, via `siblingMachine.subscribe(...)` - immediate feedback is
             *   the point there, so this stays unconditional.
             * - This field's own `VALIDATE_FIELD` only fires when a listened-to sibling dispatches
             *   its own `FIELD_VALIDATED_EVENT` (see above) - i.e. under the exact same conditions
             *   that sibling would revalidate itself (blur while dirty, or a change once it
             *   already has an error) - not on every keystroke. This is what keeps
             *   `passwordConfirm` from re-validating on every character typed into `password`: it
             *   only re-checks when `password` itself would have re-checked.
             *
             * A sibling field's *form-scoped* registration may not exist yet when this field
             * mounts - every field's own `Field` instance constructs in one early, synchronous
             * `mountAll('field', ...)` pass (triggered by whichever `ui:field.root` usage happens
             * to appear first on the page), which can easily run before the enclosing `Form`
             * instance itself has been constructed elsewhere (e.g. a form with its own dedicated
             * entry file, loaded via a `<vite:asset>` tag further down the page) - and
             * `getFieldMachinesFor` only returns anything once the form has registered. Mirrors
             * `getFormMachine`'s own retry-via-bubbling-CustomEvent pattern, listening for
             * `fluid-primitives:form:registered` (not `field:registered` - every field has
             * already registered itself by the time this runs; it's specifically the form's own,
             * later registration this needs to wait for) until every named sibling is found.
             */
            setupDependencyListeners({ context, scope, prop }) {
                const listenTo = prop('listenTo');
                if (!listenTo || listenTo.length === 0) return;

                const rootEl = dom.getRootEl(scope);
                if (!rootEl) return;

                const formEl = rootEl.closest('form');
                if (!formEl) return;

                const normalizedNames = listenTo.map(trimArraySuffix);
                const lastValues = new Map<string, FieldValue>();
                const pending = new Set(normalizedNames);

                const dispatchDependencyChange = () => {
                    const dependencies: Record<string, FieldValue> = {};
                    listenTo.forEach((name, index) => {
                        const siblingMachine = getFieldMachinesFor(rootEl).get(
                            normalizedNames[index]
                        );
                        dependencies[name] = siblingMachine
                            ? siblingMachine.context.get('value')
                            : null;
                    });

                    const detail: FieldDependencyChangeDetail = {
                        name: prop('name'),
                        dependencies,
                        values: createFormValues(new FormData(formEl as HTMLFormElement)),
                    };
                    rootEl.dispatchEvent(
                        new CustomEvent('fluid-primitives:field:dependencychange', {
                            bubbles: true,
                            detail,
                        })
                    );
                };

                const revalidateSelf = () => {
                    context.get('formMachine')?.send({
                        type: 'VALIDATE_FIELD',
                        detail: { fieldName: prop('name') },
                    });
                };

                const trySubscribe = () => {
                    for (const name of Array.from(pending)) {
                        const siblingMachine = getFieldMachinesFor(rootEl).get(name);
                        if (!siblingMachine) continue;

                        lastValues.set(name, siblingMachine.context.get('value'));
                        siblingMachine.subscribe(() => {
                            const newValue = siblingMachine.context.get('value');
                            if (isFieldValueEqual(newValue, lastValues.get(name) ?? null)) return;
                            lastValues.set(name, newValue);
                            dispatchDependencyChange();
                        });

                        dom.getRootEl(siblingMachine.scope)?.addEventListener(
                            FIELD_VALIDATED_EVENT,
                            revalidateSelf
                        );

                        pending.delete(name);
                    }

                    if (pending.size === 0) {
                        formEl.removeEventListener(
                            'fluid-primitives:form:registered',
                            trySubscribe
                        );
                    }
                };

                trySubscribe();
                if (pending.size > 0) {
                    formEl.addEventListener('fluid-primitives:form:registered', trySubscribe);
                }
            },
        },
    },
});
