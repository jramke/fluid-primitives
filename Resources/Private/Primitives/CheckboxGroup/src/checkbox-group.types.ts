import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';

export interface CheckboxGroupProps {
	id: string;
	ids?: Record<string, string>;
	/** The initial value of the checkbox group (uncontrolled) */
	defaultValue?: string[];
	/** The controlled value of the checkbox group */
	value?: string[];
	/** The name of the input fields in the checkbox group (for form submission) */
	name?: string;
	/** The form id the checkbox group belongs to */
	form?: string;
	/** If true, the checkbox group is disabled */
	disabled?: boolean;
	/** If true, the checkbox group is read-only */
	readOnly?: boolean;
	/** If true, the checkbox group is required */
	required?: boolean;
	/** If true, the checkbox group is invalid */
	invalid?: boolean;
	/** The maximum number of selected values */
	maxSelectedValues?: number;
	/** Called when the value changes */
	onValueChange?: (details: { value: string[] }) => void;
}

export interface CheckboxGroupSchema {
	props: CheckboxGroupProps;
	context: {
		value: string[];
	};
	computed: {
		isAtMax: boolean;
		isInteractive: boolean;
	};
	state: 'ready';
	event: EventObject;
	action: string;
	effect: string;
}

export interface CheckboxGroupItemProps {
	value: string;
}

/** API returned by getItemProps for checkbox items */
export interface CheckboxGroupItemState {
	checked: boolean;
	onCheckedChange: () => void;
	name: string | undefined;
	disabled: boolean;
	readOnly: boolean;
	invalid: boolean;
}

/** Public API for CheckboxGroup - used by Checkbox to get item props */
export interface CheckboxGroupApi {
	/** The current value of the checkbox group */
	value: string[];
	/** The name for form submission */
	name: string | undefined;
	/** Whether the checkbox group is disabled */
	disabled: boolean;
	/** Whether the checkbox group is read-only */
	readOnly: boolean;
	/** Whether the checkbox group is invalid */
	invalid: boolean;
	/** Check if a value is selected */
	isChecked(value: string): boolean;
	/** Set the entire value array */
	setValue(value: string[]): void;
	/** Add a value to the selection */
	addValue(value: string): void;
	/** Remove a value from the selection */
	removeValue(value: string): void;
	/** Toggle a value */
	toggleValue(value: string): void;
	/** Get props to merge into a checkbox item */
	getItemProps(props: CheckboxGroupItemProps): CheckboxGroupItemState;
	/** Get root element props */
	getRootProps(): PropTypes['element'];
	/** Get label element props */
	getLabelProps(): PropTypes['element'];
}
