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
    name: string;
    getValue(): FieldValue;
    getErrorText(): string | null;
    getRootEl(): HTMLElement | null;
    getFormMachine(): FormMachine | undefined;
    setDisabled(disabled: boolean): void;
    setRequired(required: boolean): void;
    setReadOnly(readOnly: boolean): void;
    /**
     * Registers `callback` for `fluid-primitives:field:dependencychange` (dispatched whenever a
     * field named in this field's own `listenTo` prop changes value) and returns a function that
     * removes it again, mirroring the native `addEventListener`/cleanup-function idiom rather than
     * a config-style `onX` prop - this is something you call to start listening, not a value you
     * set once at construction. A no-op subscription (immediately-inert callback, still-callable
     * unsubscribe) for a field with no `listenTo`, since the event never fires for one.
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
