import { normalizeProps } from '@zag-js/vanilla';
import { getFormMachineFor, type FormMachine } from '../../Form/src/form.registry';
import type { FormValues } from '../../Form/src/form.types';
import { connect } from './field.connect';
import type { FieldMachine } from './field.registry';
import type { FieldApi } from './field.types';
import { getCurrentFieldValue, type FieldValue } from './field.value';

/** Dispatched on a field's root whenever a field named in its own `listenTo` changes value. */
export const FIELD_DEPENDENCY_CHANGE_EVENT = 'fluid-primitives:field:dependencychange';

export interface FieldDependencyChangeDetail {
    name: string;
    dependencies: Record<string, FieldValue>;
    values: FormValues;
}

/** What a field offers on top of Zag's api: its name, its value, its form and its `listenTo` event. */
export interface FieldHandleExtras {
    /** The name of the field. */
    name: string;
    /** The current value of the field. */
    getValue(): FieldValue;
    /** The first error message of the field, `null` if there is none. */
    getErrorText(): string | null;
    /** The root element of the field. */
    getRootEl(): HTMLElement | null;
    /** The machine of the form the field sits in, `undefined` outside of a form. */
    getFormMachine(): FormMachine | undefined;
    /** Disables or enables the field. */
    setDisabled(disabled: boolean): void;
    /** Marks the field as required or optional. */
    setRequired(required: boolean): void;
    /** Makes the field read-only or editable. */
    setReadOnly(readOnly: boolean): void;
    /**
     * Calls `callback` whenever the value of a field named in the `listenTo` prop of this field
     * changes. Returns a function that removes the listener again. For a field without `listenTo` it
     * never fires, but the returned function is still safe to call.
     */
    addDependencyChangeListener(
        callback: (detail: FieldDependencyChangeDetail) => void
    ): () => void;
}

/** Zag's api plus the handle extras: what a `Field` instance exposes as `.api`. */
export type FieldClientApi = FieldApi & FieldHandleExtras;

export function connectField(machine: FieldMachine): FieldClientApi {
    return { ...connect(machine.service, normalizeProps), ...createFieldHandleExtras(machine) };
}

/** What `form.api.getField(name)` returns: the field's state and actions, flat. */
export type FieldHandle = Pick<
    FieldApi,
    | 'disabled'
    | 'required'
    | 'readOnly'
    | 'invalid'
    | 'valid'
    | 'touched'
    | 'dirty'
    | 'filled'
    | 'focused'
    | 'validating'
    | 'errors'
    | 'validate'
    | 'clearErrors'
    | 'reset'
> &
    FieldHandleExtras;

export function createFieldHandleExtras(machine: FieldMachine): FieldHandleExtras {
    const { context, prop, refs } = machine;

    return {
        name: prop('name'),
        getValue: () =>
            getCurrentFieldValue(refs.get('rootEl'), prop('name'), prop('defaultValue')),
        getErrorText() {
            const errors = context.get('errors');
            return errors.length > 0 ? errors.join(' ') : null;
        },
        getRootEl: () => refs.get('rootEl'),
        getFormMachine: () => getFormMachineFor(refs.get('rootEl')),
        setDisabled: disabled => machine.updateProps({ disabled }),
        setRequired: required => machine.updateProps({ required }),
        setReadOnly: readOnly => machine.updateProps({ readOnly }),
        addDependencyChangeListener(callback) {
            const rootEl = refs.get('rootEl');
            if (!rootEl) return () => {};

            const handler = (event: Event) => {
                callback((event as CustomEvent<FieldDependencyChangeDetail>).detail);
            };

            rootEl.addEventListener(FIELD_DEPENDENCY_CHANGE_EVENT, handler);
            return () => rootEl.removeEventListener(FIELD_DEPENDENCY_CHANGE_EVENT, handler);
        },
    };
}

/** Reads the machine directly instead of building the whole api: the Form makes one per field per render. */
export function createFieldHandle(machine: FieldMachine): FieldHandle {
    const { context, computed, prop } = machine;
    const disabled = computed('disabled');

    return {
        disabled,
        required: !!prop('required'),
        readOnly: !!prop('readOnly'),
        invalid: computed('invalid'),
        valid: computed('valid'),
        touched: context.get('touched'),
        dirty: context.get('dirty'),
        filled: context.get('filled'),
        focused: !disabled && context.get('focused'),
        validating: context.get('validating'),
        errors: context.get('errors'),
        validate: () => machine.send({ type: 'VALIDATE' }),
        clearErrors: () => machine.send({ type: 'ERRORS.CLEAR' }),
        reset: () => machine.send({ type: 'RESET' }),
        ...createFieldHandleExtras(machine),
    };
}
