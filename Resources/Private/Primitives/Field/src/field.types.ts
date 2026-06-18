import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';
import type { FormMachine } from '../../Form/src/form.registry';

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
	meta: FieldMeta;
	value: FieldValue;
	invalid: boolean;
	errors: string[];
	name: string;
	disabled: boolean;
	required: boolean;
	readOnly: boolean;
	getErrorText(): string | null;
}

export interface FieldApi extends FieldHandle {
	getRootProps(): PropTypes['element'];
	getLabelProps(): PropTypes['label'];
	getControlProps(): PropTypes['element'];
	getErrorProps(): PropTypes['element'];
	getDescriptionProps(): PropTypes['element'];
}
