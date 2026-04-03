import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import * as dom from './checkbox-group.dom';
import type {
	CheckboxGroupApi,
	CheckboxGroupItemProps,
	CheckboxGroupSchema,
} from './checkbox-group.types';

export function connect<T extends PropTypes>(
	service: Service<CheckboxGroupSchema>,
	normalize: NormalizeProps<T>
): CheckboxGroupApi {
	const { scope, context, computed, send, prop } = service;

	const disabled = !!prop('disabled');
	const readOnly = !!prop('readOnly');
	const invalid = !!prop('invalid');
	const required = !!prop('required');
	const name = prop('name');

	return {
		get value() {
			return context.get('value');
		},

		name,
		disabled,
		readOnly,
		invalid,

		isChecked(val: string) {
			return context.get('value').includes(val);
		},

		setValue(val: string[]) {
			send({ type: 'VALUE.SET', value: val });
		},

		addValue(val: string) {
			send({ type: 'VALUE.ADD', value: val });
		},

		removeValue(val: string) {
			send({ type: 'VALUE.REMOVE', value: val });
		},

		toggleValue(val: string) {
			send({ type: 'VALUE.TOGGLE', value: val });
		},

		getItemProps(props: CheckboxGroupItemProps) {
			const checked = context.get('value').includes(props.value);
			// Must compute isAtMax fresh each call to get current state
			const isAtMax = computed('isAtMax');
			return {
				checked,
				onCheckedChange: () => send({ type: 'VALUE.TOGGLE', value: props.value }),
				name,
				disabled: disabled || (isAtMax && !checked),
				readOnly,
				invalid: invalid,
			};
		},

		getRootProps() {
			return normalize.element({
				id: dom.getRootId(scope),
				role: 'group',
				'data-scope': 'checkbox-group',
				'data-part': 'root',
				'data-disabled': disabled ? '' : undefined,
				'data-readonly': readOnly ? '' : undefined,
				'data-invalid': invalid ? '' : undefined,
				'aria-labelledby': dom.getLabelId(scope),
				'aria-disabled': disabled || undefined,
				'aria-invalid': invalid || undefined,
				'aria-required': required || undefined,
			});
		},

		getLabelProps() {
			return normalize.element({
				id: dom.getLabelId(scope),
				'data-scope': 'checkbox-group',
				'data-part': 'label',
				'data-disabled': disabled ? '' : undefined,
				'data-readonly': readOnly ? '' : undefined,
				'data-invalid': invalid ? '' : undefined,
			});
		},
	};
}
