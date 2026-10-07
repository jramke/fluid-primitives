import { setup } from '@zag-js/core';
import { observeChildren, raf, trackFormControl } from '@zag-js/dom-query';
import { isEqual } from '@zag-js/utils';
import { trimArraySuffix } from '../../Form/src/form.path';
import { getFormMachineFor } from '../../Form/src/form.registry';
import * as dom from './field.dom';
import type { FieldParams, FieldSchema, ValidateResult, ValiditySnapshot } from './field.types';
import {
    VALID_SNAPSHOT,
    getValiditySnapshot,
    resolveValidation,
    shouldCommit,
    suppressValueMissing,
    toErrorArray,
    withValueMissing,
} from './field.utils';
import { getComparableFieldValue } from './field.value';

const { createMachine } = setup<FieldSchema>();

export const machine = createMachine({
    props({ props }) {
        return {
            dir: 'ltr',
            disabled: false,
            readOnly: false,
            required: false,
            validationMode: 'onBlur',
            ...props,
        };
    },

    initialState() {
        return 'idle';
    },

    context({ prop, bindable }) {
        return {
            touched: bindable<boolean>(() => ({
                defaultValue: false,
                value: prop('touched'),
            })),
            dirty: bindable<boolean>(() => ({
                defaultValue: false,
                value: prop('dirty'),
            })),
            filled: bindable<boolean>(() => ({ defaultValue: false })),
            focused: bindable<boolean>(() => ({ defaultValue: false })),
            validating: bindable<boolean>(() => ({ defaultValue: false })),
            errors: bindable<string[]>(() => ({ defaultValue: [] })),
            validity: bindable<ValiditySnapshot | null>(() => ({ defaultValue: null })),
            errorTextIds: bindable<string[]>(() => ({ defaultValue: [] })),
            hasHelperText: bindable<boolean>(() => ({ defaultValue: false })),
            fieldsetDisabled: bindable<boolean>(() => ({ defaultValue: false })),
            submitAttempted: bindable<boolean>(() => ({ defaultValue: false })),
        };
    },

    refs() {
        return {
            markedDirty: false,
            initialValue: null,
            seq: 0,
            rootEl: null,
        };
    },

    computed: {
        disabled: ({ prop, context }) => !!prop('disabled') || context.get('fieldsetDisabled'),
        valid: ({ prop, context }) => {
            if (prop('invalid')) return false;
            if (prop('disabled') || context.get('fieldsetDisabled')) return null;
            return context.get('validity')?.valid ?? null;
        },
        invalid: ({ prop, context }) => {
            if (prop('invalid')) return true;
            if (prop('disabled') || context.get('fieldsetDisabled')) return false;
            return context.get('validity')?.valid === false;
        },
    },

    watch({ track, prop, context, refs }) {
        track([() => prop('disabled')], () => {
            if (prop('disabled') && context.get('focused')) {
                context.set('focused', false);
            }
        });
        track([() => context.get('dirty')], () => {
            if (context.get('dirty')) refs.set('markedDirty', true);
        });
    },

    effects: ['trackRoot', 'trackControlState', 'trackTextParts', 'trackFieldEvents'],

    on: {
        'CONTROL.FOCUS': {
            actions: ['setFocused'],
        },
        'CONTROL.BLUR': [
            { guard: 'shouldCommit', actions: ['setBlurred', 'commitValidation'] },
            { actions: ['setBlurred'] },
        ],
        'CONTROL.CHANGE': [
            { guard: 'shouldCommit', actions: ['trackValueState', 'commitValidation'] },
            { actions: ['trackValueState', 'silentValidate', 'recoverValueMissing'] },
        ],
        // Native `invalid` event fired at submit time (bubble suppressed in connect)
        'SUBMIT.INVALID': {
            actions: ['markSubmitAttempted', 'commitValidation'],
        },
        VALIDATE: {
            actions: ['forceDirty', 'commitValidation'],
        },
        'VALIDATE.RESOLVE': {
            guard: 'isCurrentValidation',
            actions: ['applyAsyncValidation'],
        },
        'ERRORS.CLEAR': {
            actions: ['clearValidation'],
        },
        RESET: {
            actions: ['resetField'],
        },
        // A primitive inside the field finished hydrating: its state may differ from the server HTML
        // the baseline was measured on (a defaultChecked checkbox, a Select's selected option).
        BASELINE: {
            actions: ['rebaseline'],
        },
    },

    states: {
        idle: {},
    },

    implementations: {
        guards: {
            isCurrentValidation: ({ refs, event }) => event.seq === refs.get('seq'),
            shouldCommit: ({ prop, context, refs, event }) =>
                shouldCommit({
                    mode: prop('validationMode'),
                    submitAttempted: context.get('submitAttempted'),
                    eventType: event.type,
                    edited: refs.get('markedDirty') || context.get('dirty'),
                    showingErrors: context.get('errors').length > 0,
                }),
        },

        effects: {
            trackRoot({ refs, scope }) {
                refs.set('rootEl', dom.queryRootEl(scope));
            },

            trackControlState({ context, refs, send, prop }) {
                const rootEl = refs.get('rootEl');
                if (!rootEl) return;
                if (refs.get('initialValue') == null) {
                    const value = readValue({ refs, prop });
                    refs.set('initialValue', value);
                    context.set('filled', value.length > 0);
                }
                return trackFormControl(rootEl, {
                    onFieldsetDisabledChange(disabled) {
                        context.set('fieldsetDisabled', disabled);
                    },
                    onFormReset() {
                        send({ type: 'RESET' });
                    },
                });
            },

            trackTextParts({ context, refs }) {
                const rootEl = refs.get('rootEl');
                if (!rootEl) return;
                const sync = () => {
                    const errorTextIds = dom.getVisibleErrorTextIds(rootEl);
                    if (!isEqual(context.get('errorTextIds'), errorTextIds)) {
                        context.set('errorTextIds', errorTextIds);
                    }
                    context.set('hasHelperText', dom.hasHelperText(rootEl));
                };
                sync();
                return observeChildren(rootEl, {
                    defer: true,
                    callback: sync,
                    attributes: true,
                    attributeFilter: ['hidden'],
                });
            },

            // Our controls are composite (focus on a Select's trigger, value in a hidden <select>, a
            // portaled listbox, groups of inputs), so instead of handlers on one control the field
            // listens on its root: capture phase also sees the non-bubbling `invalid` event.
            trackFieldEvents({ refs, send, prop }) {
                const rootEl = refs.get('rootEl');
                if (!rootEl) return;

                let timer: ReturnType<typeof setTimeout> | undefined;
                const read = () => readValue({ refs, prop });
                const sendChange = () => {
                    timer = undefined;
                    send({ type: 'CONTROL.CHANGE', value: read() });
                };
                // The value is read when the timer fires, not when the event does: a primitive's
                // synthetic input/change event can precede its DOM update (NumberInput's text, the
                // Combobox's hidden inputs).
                const schedule = () => {
                    clearTimeout(timer);
                    timer = setTimeout(sendChange, getSettleDelay(rootEl));
                };
                // A blur has to see the edit it follows, or the edited-only commit rule would miss it.
                const flush = () => {
                    if (timer === undefined) return;
                    clearTimeout(timer);
                    sendChange();
                };

                const listeners: Record<string, (event: any) => void> = {
                    focusin: () => send({ type: 'CONTROL.FOCUS' }),
                    focusout: (event: FocusEvent) => {
                        if (dom.isInsideField(rootEl, event.relatedTarget)) return;
                        flush();
                        send({ type: 'CONTROL.BLUR', value: read() });
                    },
                    input: schedule,
                    change: schedule,
                    // Zag's checkbox, switch and radio announce programmatic changes with a synthetic click only
                    click: (event: Event) => {
                        if (!event.isTrusted && isChoiceInput(event.target)) schedule();
                    },
                    // suppress the native browser bubble; the field surfaces the error itself
                    invalid: (event: Event) => {
                        event.preventDefault();
                        send({ type: 'SUBMIT.INVALID', value: read() });
                    },
                };
                for (const [type, listener] of Object.entries(listeners)) {
                    rootEl.addEventListener(type, listener, true);
                }

                return () => {
                    clearTimeout(timer);
                    for (const [type, listener] of Object.entries(listeners)) {
                        rootEl.removeEventListener(type, listener, true);
                    }
                };
            },
        },

        actions: {
            setFocused({ context }) {
                context.set('focused', true);
            },

            setBlurred({ context }) {
                context.set('focused', false);
                context.set('touched', true);
            },

            trackValueState({ context, refs, event }) {
                const value: string = event.value;
                const dirty = value !== (refs.get('initialValue') ?? '');
                context.set('dirty', dirty);
                if (dirty) refs.set('markedDirty', true);
                context.set('filled', value.length > 0);
            },

            silentValidate(params) {
                silentValidate(params, params.event.value);
            },

            recoverValueMissing(params) {
                const { context, event } = params;
                const validity = context.get('validity');
                if (!validity || validity.valid || !validity.valueMissing) return;

                const nextValidity = readValidity(params, event.value);
                if (!nextValidity.valid) return;

                applyValidation(params, {
                    customErrors: [],
                    validity: nextValidity,
                    value: event.value,
                    nativeMessage: '',
                });
            },

            markSubmitAttempted({ context, refs }) {
                context.set('submitAttempted', true);
                refs.set('markedDirty', true);
            },

            forceDirty({ refs }) {
                refs.set('markedDirty', true);
            },

            commitValidation(params) {
                commitValidation(params, { value: params.event.value });
            },

            applyAsyncValidation(params) {
                const { event } = params;
                applyValidation(params, {
                    customErrors: toErrorArray(event.result),
                    validity: event.validity,
                    value: event.value,
                    nativeMessage: event.nativeMessage,
                });
            },

            clearValidation(params) {
                clearValidation(params);
            },

            resetField(params) {
                const { context, refs } = params;
                clearValidation(params);
                context.set('touched', false);
                context.set('dirty', false);
                context.set('focused', false);
                context.set('submitAttempted', false);
                refs.set('markedDirty', false);
                // form values are restored after the reset event's default action, so measure later
                raf(() => rebaseline(params));
            },

            rebaseline(params) {
                rebaseline(params);
            },
        },
    },
});

interface CommitOptions {
    value?: string | undefined;
}

function commitValidation(params: FieldParams, options: CommitOptions = {}) {
    const { prop, context, refs, send } = params;

    const hostEl = getHostEl(params);

    // clear the previous custom error so the native snapshot is untainted
    hostEl?.setCustomValidity('');

    const value = options.value ?? readValue(params);
    let validity = readValidity(params, value);
    if (!refs.get('markedDirty') && !context.get('dirty')) {
        validity = suppressValueMissing(validity);
    }

    const nativeMessage = validity.valid ? '' : getNativeMessage(params, hostEl, validity);

    const seq = refs.get('seq') + 1;
    refs.set('seq', seq);

    const result = prop('validate')?.({ value, validity });

    if (isPromise(result)) {
        context.set('validating', true);
        result.then(
            resolved =>
                send({
                    type: 'VALIDATE.RESOLVE',
                    seq,
                    result: resolved,
                    validity,
                    value,
                    nativeMessage,
                }),
            error => {
                send({
                    type: 'VALIDATE.RESOLVE',
                    seq,
                    result: null,
                    validity,
                    value,
                    nativeMessage,
                });
                queueMicrotask(() => {
                    throw error;
                });
            }
        );
        return;
    }

    applyValidation(params, { customErrors: toErrorArray(result), validity, value, nativeMessage });
}

interface ApplyOptions {
    customErrors: string[];
    validity: ValiditySnapshot;
    value: string;
    nativeMessage: string;
}

function applyValidation(params: FieldParams, options: ApplyOptions) {
    const { context, prop } = params;
    const { errors, validity } = resolveValidation(options);

    // mirror custom errors so native `:invalid` and submit gating agree
    if (options.customErrors.length > 0) {
        getHostEl(params)?.setCustomValidity(options.customErrors.join(' '));
    }

    const changed =
        context.get('validity')?.valid !== validity.valid ||
        !isEqual(context.get('errors'), errors);

    context.set('validating', false);
    context.set('validity', validity);
    context.set('errors', errors);

    if (changed) {
        prop('onValidityChange')?.({
            valid: validity.valid,
            errors,
            validity,
            value: options.value,
        });
    }
}

/**
 * Runs custom validation and mirrors the result into `setCustomValidity` without
 * committing it to context. Keeps native `:invalid` and submit gating in sync
 * while the error stays hidden until the mode's commit point.
 */
function silentValidate(params: FieldParams, value: string) {
    const { prop, refs } = params;
    const validate = prop('validate');
    if (!validate) return;

    const hostEl = getHostEl(params);
    if (!hostEl) return;

    hostEl.setCustomValidity('');
    const validity = readValidity(params, value);

    const seq = refs.get('seq') + 1;
    refs.set('seq', seq);

    const apply = (result: ValidateResult) => {
        if (refs.get('seq') !== seq) return;
        hostEl.setCustomValidity(toErrorArray(result).join(' '));
    };

    const result = validate({ value, validity });
    if (isPromise(result)) {
        result.then(apply, () => apply(null));
    } else {
        apply(result);
    }
}

function clearValidation(params: FieldParams) {
    const { context, refs } = params;
    // invalidate any in-flight async validation
    refs.set('seq', refs.get('seq') + 1);
    context.set('validating', false);
    context.set('errors', []);
    context.set('validity', null);
    getHostEl(params)?.setCustomValidity('');
}

/**
 * The native control that hosts constraint validation (`setCustomValidity`, `validity`), when there
 * is one: the element the control id points at, if it is the one carrying the field's name. Looked
 * up at call time, and absent for groups, a Slider, or a Combobox's visible input (no name).
 */
function getHostEl({ scope, prop }: Pick<FieldParams, 'scope' | 'prop'>) {
    const controlEl = dom.getControlEl(scope);
    if (!controlEl || typeof controlEl.setCustomValidity !== 'function') return null;
    return trimArraySuffix(controlEl.name) === trimArraySuffix(prop('name')) ? controlEl : null;
}

/** Makes the current value the baseline for `dirty`, unless the user already changed it. */
function rebaseline(params: FieldParams) {
    const { context, refs } = params;
    if (context.get('dirty') || refs.get('markedDirty')) return;

    const value = readValue(params);
    refs.set('initialValue', value);
    context.set('filled', value.length > 0);
}

function readValue({ refs, prop }: Pick<FieldParams, 'refs' | 'prop'>) {
    return getComparableFieldValue(refs.get('rootEl'), prop('name'), prop('defaultValue'));
}

/** Native flags of the host, with `valueMissing` decided by the field's `required` and its value. */
function readValidity(params: FieldParams, value: string): ValiditySnapshot {
    const { prop } = params;
    const hostEl = getHostEl(params);
    const native = hostEl ? getValiditySnapshot(hostEl) : VALID_SNAPSHOT;
    return withValueMissing(native, !!prop('required') && !prop('readOnly') && value === '');
}

function getNativeMessage(
    params: FieldParams,
    hostEl: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null,
    validity: ValiditySnapshot
) {
    if (hostEl?.validationMessage) return hostEl.validationMessage;
    if (!validity.valueMissing) return '';

    // no host to ask (or one that is not `required` itself): borrow the browser's localized message
    const probe = params.scope.getDoc().createElement('input');
    probe.required = true;
    return probe.validationMessage;
}

function isPromise(value: unknown): value is Promise<ValidateResult> {
    return typeof value === 'object' && value !== null && 'then' in value;
}

/** At least a short settle, whatever the form's `inputDebounceMs`: see `schedule` in `trackFieldEvents`. */
function getSettleDelay(rootEl: HTMLElement): number {
    const debounceMs = getFormMachineFor(rootEl)?.prop('inputDebounceMs') ?? 100;
    return Math.max(debounceMs, 50);
}

function isChoiceInput(target: EventTarget | null) {
    return (
        target instanceof HTMLInputElement &&
        (target.type === 'checkbox' || target.type === 'radio')
    );
}
