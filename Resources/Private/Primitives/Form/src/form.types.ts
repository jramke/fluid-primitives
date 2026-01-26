import type { EventObject } from '@zag-js/core';
import type { JSX, PropTypes } from '@zag-js/types';
import * as z from 'zod';
import type { Form } from '../Form';
import type { FieldMachine } from './form.registry';

export type FormErrors = Record<string, string[]>;
export type FormDirty = Record<string, boolean>;
export type FormTouched = Record<string, boolean>;

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

export type ZodFormSchema = z.ZodObject | undefined;

export interface FormProps {
	id: string;
	schema?: ZodFormSchema;
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
	}) => Promise<boolean> | boolean;
	render?: (form: Form) => void;
}

export interface FormSchema {
	props: FormProps;
	context: {
		values: FormData;
		initialValues: FormData;
		errors: FormErrors;
		dirty: FormDirty;
		touched: FormTouched;
	};
	state: 'invalid' | 'ready' | 'submitting' | 'success' | 'error';
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
	getValues(): FormData;
	getErrors(): FormErrors;
	getDirty(): FormDirty;
	getTouched(): FormTouched;
	userRenderFn: FormProps['render'];
	getFields(): Map<string, FieldMachine>;
	getFormEl(): HTMLFormElement | null;
	getAction(): string;
}
