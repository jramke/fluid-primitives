import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';
import type { FormMachine } from '../../Form/src/form.registry';
import type { FormValues } from '../../Form/src/form.types';

export type FieldValue = FormDataEntryValue | FormDataEntryValue[] | null;

export interface FieldMeta {
    isTouched: boolean;
    isDirty: boolean;
    isPristine: boolean;
    isBlurred: boolean;
    isDefaultValue: boolean;
}

export interface FieldProps {
    id: string;
    name: string;
    invalid?: boolean;
    required?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    defaultValue?: unknown;
    /**
     * Names of sibling fields within the same form whose value changes this field should react
     * to - see field.machine.ts's `setupDependencyListeners`.
     */
    listenTo?: string[];
}

export interface FieldDependencyChangeDetail {
    name: string;
    dependencies: Record<string, FieldValue>;
    values: FormValues;
}

export interface FieldSchema {
    props: FieldProps;
    context: {
        invalid: boolean;
        required: boolean;
        disabled: boolean;
        readOnly: boolean;
        formMachine: FormMachine | null;
        describeIds: string | undefined;
        hasDescription: boolean;
        value: FieldValue;
        initialValue: FieldValue;
        errors: string[];
        touched: boolean;
        dirty: boolean;
        blurred: boolean;
    };
    state: 'ready';
    event: EventObject;
    action: string;
    effect: string;
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

export interface FieldApi extends FieldHandle {
    getRootProps(): PropTypes['element'];
    getLabelProps(): PropTypes['label'];
    getControlProps(): PropTypes['element'];
    getErrorProps(): PropTypes['element'];
    getDescriptionProps(): PropTypes['element'];
}
