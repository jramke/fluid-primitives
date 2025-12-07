import type { EventObject } from '@zag-js/core';
import type { JSX, PropTypes } from '@zag-js/types';
import * as v from 'valibot';
import type { Form } from '../Form';
import type { FieldMachine } from './form.registry';

// export type FormValues = Record<string, unknown>;
export type FormErrors = Record<string, string[]>;
export type FormDirty = Record<string, boolean>;

export type ValibotFormSchema = v.ObjectSchema<
	v.ObjectEntries,
	v.ErrorMessage<v.ObjectIssue> | undefined
>;

export interface FormProps {
	id: string;
	schema?: ValibotFormSchema;
	reactiveFields?: string[];
	objectName?: string;
	// validateOn?: 'change' | 'blur';
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
	userRenderFn: FormProps['render'];
	getFields(): Map<string, FieldMachine>;
	getFormEl(): HTMLFormElement | null;
	getAction(): string;
	// getFieldState(name: string): {
	// 	value: unknown;
	// 	errors: string[];
	// 	dirty: boolean;
	// 	invalid: boolean;
	// };
}
