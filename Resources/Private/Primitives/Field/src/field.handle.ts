import type { Service } from '@zag-js/core';
import type { FormValues } from '../../Form/src/form.types';
import * as dom from './field.dom';
import type { FieldSchema } from './field.types';
import { isFieldValueEqual, type FieldValue } from './field.value';

export interface FieldMeta {
    isTouched: boolean;
    isDirty: boolean;
    isPristine: boolean;
    isBlurred: boolean;
    isDefaultValue: boolean;
}

export interface FieldDependencyChangeDetail {
    name: string;
    dependencies: Record<string, FieldValue>;
    values: FormValues;
}

export interface FieldHandle {
    getFormMachine(): FieldSchema['context']['formMachine'];
    getRootEl(): HTMLElement | null;
    setDisabled(disabled: boolean): void;
    setRequired(required: boolean): void;
    setReadOnly(readOnly: boolean): void;
    meta: FieldMeta;
    value: FieldValue;
    invalid: boolean;
    errors: string[];
    name: string;
    disabled: boolean;
    required: boolean;
    readOnly: boolean;
    getErrorText(): string | null;
    /**
     * Registers `callback` for `fluid-primitives:field:dependencychange` (dispatched whenever a
     * field named in this field's own `listenTo` prop changes value - see
     * `field.machine.ts`'s `setupDependencyListeners`) and returns a function that removes it
     * again, mirroring the native `addEventListener`/cleanup-function idiom rather than a
     * config-style `onX` prop - this is something you call to start listening, not a value you
     * set once at construction. A no-op subscription (immediately-inert callback, still-callable
     * unsubscribe) for a field with no `listenTo`, since the event never fires for one.
     */
    addDependencyChangeListener(
        callback: (detail: FieldDependencyChangeDetail) => void
    ): () => void;
}

type FieldServiceLike = Pick<Service<FieldSchema>, 'prop' | 'context' | 'scope'>;

export function createFieldHandle(service: FieldServiceLike): FieldHandle {
    const { prop, context, scope } = service;

    const invalid = context.get('invalid');
    const disabled = context.get('disabled');
    const required = context.get('required');
    const readOnly = context.get('readOnly');
    const errors = context.get('errors');
    const value = context.get('value');
    const initialValue = context.get('initialValue');

    const meta: FieldMeta = {
        isTouched: context.get('touched'),
        isDirty: context.get('dirty'),
        isPristine: !context.get('dirty'),
        isBlurred: context.get('blurred'),
        isDefaultValue: isFieldValueEqual(value, initialValue),
    };

    // TODO: is it possible that we can expose the fields child control machine (if any and not a native input) here
    // so the user can acces its machine api to do more custom stuff
    return {
        getFormMachine: () => context.get('formMachine'),
        getRootEl: () => dom.getRootEl(scope),
        name: prop('name'),
        value,
        meta,
        disabled,
        setDisabled: disabled => context.set('disabled', disabled),
        required,
        setRequired: required => context.set('required', required),
        readOnly,
        setReadOnly: readOnly => context.set('readOnly', readOnly),
        invalid,
        errors,
        getErrorText() {
            return errors.length > 0 ? errors.join(' ') : null;
        },
        addDependencyChangeListener(callback: (detail: FieldDependencyChangeDetail) => void) {
            const rootEl = dom.getRootEl(scope);
            if (!rootEl) return () => {};

            const handler = (event: Event) => {
                callback((event as CustomEvent<FieldDependencyChangeDetail>).detail);
            };

            rootEl.addEventListener('fluid-primitives:field:dependencychange', handler);
            return () =>
                rootEl.removeEventListener('fluid-primitives:field:dependencychange', handler);
        },
    };
}
