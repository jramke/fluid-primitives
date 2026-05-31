import type {
	FieldAsyncValidateOrFn,
	FieldValidateOrFn,
	FieldValidators,
	FieldApi as TanstackFieldApi,
} from '@tanstack/form-core';
import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';
import type { FormValues } from '../../Form/src/form.types';

/**
 * Loosely typed alias for the TanStack `FieldApi`. The field is discovered at
 * runtime from the DOM, so the full generic signature isn't useful here.
 */
export type TanstackField = TanstackFieldApi<
	FormValues,
	string,
	unknown,
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

export interface FieldProps {
	id: string;
	name: string;
	invalid?: boolean;
	required?: boolean;
	disabled?: boolean;
	readOnly?: boolean;
	/** Per-field TanStack validators (`onChange`, `onBlur`, `onSubmit`, ...). */
	validators?: FieldValidators<
		FormValues,
		string,
		unknown,
		FieldValidateOrFn<FormValues, string, unknown> | undefined,
		FieldValidateOrFn<FormValues, string, unknown> | undefined,
		FieldAsyncValidateOrFn<FormValues, string, unknown> | undefined,
		FieldValidateOrFn<FormValues, string, unknown> | undefined,
		FieldAsyncValidateOrFn<FormValues, string, unknown> | undefined,
		FieldValidateOrFn<FormValues, string, unknown> | undefined,
		FieldAsyncValidateOrFn<FormValues, string, unknown> | undefined,
		FieldValidateOrFn<FormValues, string, unknown> | undefined,
		FieldAsyncValidateOrFn<FormValues, string, unknown> | undefined
	>;
}

export interface FieldSchema {
	props: FieldProps;
	context: {
		invalid: boolean;
		required: boolean;
		disabled: boolean;
		readOnly: boolean;
		describeIds: string | undefined;
		hasDescription: boolean;
		/** Bumped whenever the bound TanStack field store changes. */
		fieldVersion: number;
	};
	refs: {
		field: TanstackField | null;
	};
	computed: {
		errors: string[];
		touched: boolean;
		dirty: boolean;
	};
	state: 'ready';
	event: EventObject;
	action: string;
	effect: string;
}

export interface FieldApi {
	/** The underlying TanStack field instance, when inside a form. */
	field: TanstackField | null;
	invalid: boolean;
	errors: string[];
	touched: boolean;
	dirty: boolean;
	name: string;
	disabled: boolean;
	required: boolean;
	readOnly: boolean;
	getRootProps(): PropTypes['element'];
	getLabelProps(): PropTypes['label'];
	getControlProps(): PropTypes['element'];
	getErrorProps(): PropTypes['element'];
	getDescriptionProps(): PropTypes['element'];
	getErrorText(): string | null;
}
