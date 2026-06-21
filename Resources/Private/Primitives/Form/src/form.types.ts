import type { EventObject } from '@zag-js/core';
import type { JSX, PropTypes } from '@zag-js/types';
import type { FieldHandle } from '../../Field/src/field.types';
import type { Form } from '../Form';

export interface FieldError {
    messages: string[];
    value?: FormDataEntryValue | FormDataEntryValue[] | null;
}
export type FormErrors = Record<string, FieldError>;
export type FormDirty = Record<string, boolean>;
export type FormTouched = Record<string, boolean>;

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
    | StandardSchemaSuccessResult<Output>
    | StandardSchemaFailureResult;

export interface StandardSchemaV1<Output = unknown> {
    readonly '~standard': {
        readonly validate: (
            value: unknown
        ) => StandardSchemaResult<Output> | Promise<StandardSchemaResult<Output>>;
    };
}

export interface FormValidationContext {
    formData: FormData;
    fieldName?: string;
}

export type FormState = 'invalid' | 'ready' | 'submitting' | 'success' | 'error';

export type FormValidation =
    | StandardSchemaV1
    | ((context: FormValidationContext) => FormErrors | null | void);

export type FormSubmitResult = true | false | FormErrors;

export type AnyFormControlElement =
    | HTMLInputElement
    | HTMLTextAreaElement
    | HTMLSelectElement
    | HTMLButtonElement;

/**
 * Error thrown by post() when server returns 422 validation errors.
 * The machine catches this and transitions to 'invalid' state.
 */
export class ValidationError extends Error {
    constructor(public errors: FormErrors) {
        super('Server validation failed');
        this.name = 'ValidationError';
    }
}

export interface FormProps {
    id: string;
    validation?: FormValidation;
    objectName?: string;
    inputDebounceMs?: number;
    onSubmit?: ({
        formData,
        api,
        event,
        post,
    }: {
        formData: FormData;
        api: FormApi;
        event: JSX.FormEvent<HTMLElement>;
        post: (url: string, data: FormData) => Promise<Response>;
    }) => Promise<FormSubmitResult> | FormSubmitResult;
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
    isSubmitting: boolean;
    isDirty: boolean;
    isInvalid: boolean;
    isSuccessful: boolean;
    isError: boolean;
    getFormProps(): PropTypes['element'];
    getContentProps(): PropTypes['element'];
    getIndicatorProps(state: FormState): PropTypes['element'];
    getErrorTextProps(): PropTypes['element'];
    getSuccessTextProps(): PropTypes['element'];
    getValues(): FormData;
    getErrors(): FormErrors;
    getDirty(): FormDirty;
    getTouched(): FormTouched;
    getErrorText(): string | null;
    setErrorText(text: string | null): void;
    getSuccessText(): string | null;
    setSuccessText(text: string | null): void;
    clearStatusText(): void;
    _userRenderFn: FormProps['render'];
    getAllFields(): Map<string, FieldHandle>;
    getField(name: string): FieldHandle | undefined;
    getFormControl(name: string): AnyFormControlElement | null;
    getFormEl(): HTMLFormElement | null;
    getAction(): string;
    reset(): void;
    formDataToObject(): Record<string, FormDataEntryValue | FormDataEntryValue[]>;
}
