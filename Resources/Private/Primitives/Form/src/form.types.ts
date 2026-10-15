import type { EventObject } from '@zag-js/core';
import type { JSX, PropTypes } from '@zag-js/types';
import type { FieldHandle } from '../../Field/src/field.handle';
import type { Form } from '../Form';

export interface FieldError {
    messages: string[];
    value?: FormDataEntryValue | FormDataEntryValue[] | null;
}
export type FormErrors = Record<string, FieldError>;
export type FormDirty = Record<string, boolean>;
export type FormTouched = Record<string, boolean>;
export type FormValueLeaf = string | File;

export interface FormValuesObject {
    [key: string]: FormValueTree;
}

export interface FormValuesArray extends Array<FormValueTree> {}

export type FormValueTree = FormValueLeaf | FormValuesObject | FormValuesArray;

export interface FormValues {
    get(path: string): FormValueLeaf | null;
    getAll(path: string): FormValueLeaf[];
    has(path: string): boolean;
    pick(path: string): FormValueTree | null;
    toObject(): FormValuesObject;
}

export interface StandardSchemaPathSegment {
    readonly key: PropertyKey;
}

export interface StandardSchemaIssue {
    readonly message: string;
    readonly path?: readonly (PropertyKey | StandardSchemaPathSegment)[];
}

export interface StandardSchemaSuccessResult<Output = unknown> {
    readonly value: Output;
    readonly issues?: undefined;
}

export interface StandardSchemaFailureResult {
    readonly issues: readonly StandardSchemaIssue[];
}

export type StandardSchemaResult<Output = unknown> =
    StandardSchemaSuccessResult<Output> | StandardSchemaFailureResult;

export interface StandardSchemaV1<Output = unknown> {
    readonly '~standard': {
        readonly validate: (
            value: unknown
        ) => StandardSchemaResult<Output> | Promise<StandardSchemaResult<Output>>;
    };
}

export interface FormValidationContext {
    values: FormValues;
    fieldName?: string;
    validateWithStandardSchema: <Output = unknown>(schema: StandardSchemaV1<Output>) => FormErrors;
}

export type FormState = 'invalid' | 'ready' | 'submitting' | 'success' | 'error';

export type FormValidation =
    StandardSchemaV1 | ((context: FormValidationContext) => FormErrors | null | void);

export type FormSubmitResult = true | false | FormErrors;

export type AnyFormControlElement =
    HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | HTMLButtonElement;

export interface FormProps {
    /** The unique identifier of the form. */
    id: string;
    /**
     * Validates the values of the form on the client: a Standard Schema validator such as Zod, or a
     * synchronous callback that gets the `values` and returns the errors by field name. It cannot be
     * set from a Fluid template, only by constructing `Form` in your own entry file.
     */
    validation?: FormValidation;
    /** The object name that prefixes the names of nested form fields. */
    objectName?: string;
    /**
     * The Extbase argument namespace (`tx_events_list`) `post()` prefixes the field names with. Set by
     * the server from the plugin the form submits to.
     */
    fieldNamePrefix?: string;
    /**
     * How long the fields wait after the last change before they read their value, never less than
     * 50. A `validate` that sends a request should not fire on every keystroke.
     * @default 100
     */
    inputDebounceMs?: number;
    /**
     * Called with the values once the form is valid and submitted. Return `true` for success,
     * `false` for an error, or field errors to mark the form invalid. Send the values with
     * `extbase.post(api.getAction(), values.toObject(), { prefix: api.getFieldNamePrefix(), objectName: api.getObjectName() })`:
     * a 422 JSON response of `JsonValidationErrorsTrait` it throws is mapped to field errors, or to the
     * error text of the form for the errors of the object itself.
     */
    onSubmit?: ({
        values,
        api,
        event,
    }: {
        values: FormValues;
        api: FormApi;
        event: JSX.FormEvent<HTMLElement>;
    }) => Promise<FormSubmitResult> | FormSubmitResult;
    /** Called every time the form state changes. Use it to update UI the form does not wire itself. */
    render?: (form: Form) => void;
}

export interface FormSchema {
    props: FormProps;
    context: {
        errorText: string | null;
        successText: string | null;
    };
    refs: {
        serverErrors: FormErrors;
    };
    state: FormState;
    event: EventObject;
    action: string;
    effect: string;
}

export interface FormApi {
    /** Whether the form is being submitted. */
    isSubmitting: boolean;
    /** Whether the value of any field differs from the one it started with. */
    isDirty: boolean;
    /** Whether any field is invalid. */
    isInvalid: boolean;
    /** Whether the last submit succeeded. */
    isSuccessful: boolean;
    /** Whether the last submit ended in an error. */
    isError: boolean;
    getFormProps(): PropTypes['element'];
    getContentProps(): PropTypes['element'];
    getIndicatorProps(state: FormState): PropTypes['element'];
    getErrorTextProps(): PropTypes['element'];
    getSuccessTextProps(): PropTypes['element'];
    /** The current values of the form. */
    getValues(): FormValues;
    /** The errors of the invalid fields, by field name. */
    getErrors(): FormErrors;
    /** The dirty fields, by field name. */
    getDirty(): FormDirty;
    /** The touched fields, by field name. */
    getTouched(): FormTouched;
    /** The text of the error status of the form, `null` if there is none. */
    getErrorText(): string | null;
    /** Sets the text of the error status. Pass `null` to remove it. */
    setErrorText(text: string | null): void;
    /** The text of the success status of the form, `null` if there is none. */
    getSuccessText(): string | null;
    /** Sets the text of the success status. Pass `null` to remove it. */
    setSuccessText(text: string | null): void;
    /** Removes the error and the success text. */
    clearStatusText(): void;
    /** @internal */
    _userRenderFn: FormProps['render'];
    /** Every field of the form, by name. */
    getAllFields(): Map<string, FieldHandle>;
    /** The field with this name, `undefined` if the form has none. */
    getField(name: string): FieldHandle | undefined;
    /** The `<form>` element. */
    getFormEl(): HTMLFormElement | null;
    /** The `action` of the form, an empty string if it has none. */
    getAction(): string;
    /** The Extbase argument namespace (`tx_events_list`) of the action the form submits to. */
    getFieldNamePrefix(): string | undefined;
    /** The `objectName` of the form: the name of the object its fields belong to. */
    getObjectName(): string | undefined;
    /** Resets the form to its initial state. */
    reset(): void;
    /** Picks up fields that were added or removed after the form was hydrated. */
    syncFields(): void;
    /**
     * Renames a field, keeping its value, touched, dirty and error state. Useful to keep the rows of
     * a FieldArray contiguously indexed after one in the middle was removed.
     */
    renameField(oldName: string, newName: string): void;
}
