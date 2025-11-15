import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';
import type { FormMachine } from '../../Form/src/form.registry';

export interface FieldProps {
	id: string;
	name: string;
	invalid?: boolean;
	required?: boolean;
	disabled?: boolean;
	readOnly?: boolean;
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
	};
	computed: {
		// invalid: boolean;
		errors: string[];
	};
	state: 'ready';
	event: EventObject;
	action: string;
	effect: string;
}

export interface FieldApi {
	getFormMachine(): FieldSchema['context']['formMachine'];
	invalid: boolean;
	errors: string[];
	name: string;
	getRootProps(): PropTypes['element'];
	getLabelProps(): PropTypes['label'];
	getControlProps(): PropTypes['element'];
	getErrorProps(): PropTypes['element'];
	getErrorText(): string | null;
}
