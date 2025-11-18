import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';
import * as v from 'valibot';

export type FormValues = Record<string, unknown>;
export type FormErrors = Record<string, string[]>;
export type FormDirty = Record<string, boolean>;

export type ValibotFormSchema = v.GenericSchema; // TODO: object schema

export interface FormProps {
	id: string;
	schema: ValibotFormSchema;
	validateOnChange?: boolean;
	onSubmit?: (values: FormValues) => Promise<boolean> | boolean;
}

export interface FormSchema {
	props: FormProps;
	context: {
		values: FormValues;
		initialValues: FormValues;
		errors: FormErrors;
		dirty: FormDirty;
	};
	state: 'validating' | 'ready' | 'submitting' | 'success' | 'error';
	event: EventObject;
	action: string;
	effect: string;
}

export interface FormApi {
	isSubmitting: boolean;
	getFormProps(): PropTypes['element'];
	getValues(): FormValues;
	getErrors(): FormErrors;
	getDirty(): FormDirty;
	getFieldState(name: string): {
		value: unknown;
		errors: string[];
		dirty: boolean;
		invalid: boolean;
	};
}
