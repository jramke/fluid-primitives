import type {
	FormAsyncValidateOrFn,
	FormValidateOrFn,
	FormValidators,
	FormApi as TanstackFormApi,
	ValidationLogicFn,
} from '@tanstack/form-core';
import type { EventObject } from '@zag-js/core';
import type { JSX, PropTypes } from '@zag-js/types';
import type { Form } from '../Form';
import type { FieldMachine } from './form.registry';

/**
 * The shape of the data managed by the underlying TanStack form. We don't know
 * the concrete fields ahead of time (they come from the server-rendered DOM),
 * so we use a generic record keyed by field name.
 */
export type FormValues = Record<string, unknown>;

/**
 * A loosely typed alias for the TanStack `FormApi`. The full generic signature
 * is not useful for a runtime-discovered form, so we widen the validator
 * generics to `any`.
 */
export type TanstackForm = TanstackFormApi<
	FormValues,
	any,
	any,
	any,
	any,
	any,
	any,
	any,
	any,
	any,
	any,
	any
>;

/**
 * Per-field error shape exposed to the Field component. `messages` is the list
 * of human readable error strings, `value` is the value the error was produced
 * for (used to avoid clearing server errors until the user actually edits).
 */
export interface FieldError {
	messages: string[];
	value?: unknown;
}
export type FormErrors = Record<string, FieldError>;

/**
 * Error thrown by `post()` (or manually from `onSubmit`) to map server-side
 * validation errors onto individual fields. The machine catches this and writes
 * the errors into the TanStack form's per-field error maps.
 */
export class ValidationError extends Error {
	constructor(public errors: FormErrors) {
		super('Form validation failed');
		this.name = 'ValidationError';
	}
}

/**
 * Internal sentinel used to signal a generic (non-validation) submit failure
 * when the user's `onSubmit` returns `false`. The machine maps this to the
 * `error` state.
 */
export class SubmitError extends Error {
	constructor() {
		super('Form submission failed');
		this.name = 'SubmitError';
	}
}

export interface FormSubmitContext {
	/** The current form values as a plain object. */
	value: FormValues;
	/** The current form values as `FormData` (ready to POST). */
	formData: FormData;
	/** The Form component api. */
	api: FormApi;
	/** The native submit event. */
	event?: JSX.FormEvent<HTMLElement>;
	/**
	 * POST helper that adds the Extbase field-name-prefix before sending and
	 * throws a {@link ValidationError} on a 422 response.
	 */
	post: (url: string, data: FormData) => Promise<Response>;
}

export interface FormProps {
	id: string;
	/** Extbase object name prefix used for nested form field names. */
	objectName?: string;
	/**
	 * Form-level TanStack validators (`onChange`, `onBlur`, `onSubmit`, async
	 * counterparts, ...). Any Standard Schema (e.g. Zod v4) is accepted directly.
	 */
	validators?: FormValidators<
		FormValues,
		FormValidateOrFn<FormValues> | undefined,
		FormValidateOrFn<FormValues> | undefined,
		FormAsyncValidateOrFn<FormValues> | undefined,
		FormValidateOrFn<FormValues> | undefined,
		FormAsyncValidateOrFn<FormValues> | undefined,
		FormValidateOrFn<FormValues> | undefined,
		FormAsyncValidateOrFn<FormValues> | undefined,
		FormValidateOrFn<FormValues> | undefined,
		FormAsyncValidateOrFn<FormValues> | undefined
	>;
	validationLogic?: ValidationLogicFn;
	/** Debounce (ms) for async validators. */
	asyncDebounceMs?: number;
	/**
	 * Called when the form is submitted and client-side validation passes.
	 * Return `true` for success, `false` for a generic error.
	 */
	onSubmit?: (ctx: FormSubmitContext) => Promise<boolean> | boolean;
	/** Runs on every form state change. Use to update DOM outside the machine. */
	render?: (form: Form) => void;
}

export interface FormSchema {
	props: FormProps;
	context: {
		/** A monotonically increasing tick bumped whenever the TanStack store changes. */
		storeVersion: number;
	};
	refs: {
		form: TanstackForm | null;
		submitContext: { api?: FormApi; event?: unknown } | null;
	};
	state: 'invalid' | 'ready' | 'submitting' | 'success' | 'error';
	event: EventObject;
	action: string;
	effect: string;
}

export interface FormApi {
	/** The underlying TanStack form instance. */
	form: TanstackForm | null;

	isSubmitting: boolean;
	isSubmitted: boolean;
	isDirty: boolean;
	isValidating: boolean;
	isInvalid: boolean;
	isSuccessful: boolean;
	isError: boolean;
	isTouched: boolean;

	getValues(): FormValues;
	getFormData(): FormData;
	getErrors(): FormErrors;

	_userRenderFn: FormProps['render'];

	getAllFields(): Map<string, FieldMachine>;
	getField(name: string): FieldMachine | undefined;
	getFormEl(): HTMLFormElement | null;
	getAction(): string;

	reset(): void;

	getFormProps(): PropTypes['element'];
}
