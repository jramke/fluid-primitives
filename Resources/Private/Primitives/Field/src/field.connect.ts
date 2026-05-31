import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { getInputValue } from '../../Form/src/form.utils';
import { parts } from './field.anatomy';
import * as dom from './field.dom';
import type { FieldApi, FieldSchema } from './field.types';

export function connect<T extends PropTypes>(
	service: Service<FieldSchema>,
	normalize: NormalizeProps<T>
): FieldApi {
	const { scope, prop, context, computed, refs } = service;

	const invalid = context.get('invalid');
	const disabled = context.get('disabled');
	const required = context.get('required');
	const readOnly = context.get('readOnly');
	const errors = computed('errors');
	const touched = computed('touched');
	const dirty = computed('dirty');

	return {
		field: refs.get('field'),

		invalid,
		disabled,
		required,
		readOnly,
		errors,
		touched,
		dirty,

		name: prop('name'),

		getErrorText() {
			return errors.length > 0 ? errors.join(' ') : null;
		},

		getRootProps() {
			return normalize.element({
				...parts.root.attrs,
				id: dom.getRootId(scope),
				'data-invalid': invalid ? '' : undefined,
				'data-disabled': disabled ? '' : undefined,
				'data-readonly': readOnly ? '' : undefined,
				'data-required': required ? '' : undefined,
				'data-dirty': dirty ? '' : undefined,
				'data-touched': touched ? '' : undefined,
				'data-name': prop('name'),
			});
		},

		getLabelProps() {
			return normalize.label({
				...parts.label.attrs,
				id: dom.getLabelId(scope),
				htmlFor: dom.getControlId(scope),
				'data-invalid': invalid ? '' : undefined,
				'data-disabled': disabled ? '' : undefined,
				'data-required': required ? '' : undefined,
			});
		},

		getControlProps() {
			const field = refs.get('field');
			return normalize.element({
				...parts.control.attrs,
				id: dom.getControlId(scope),
				name: prop('name'),
				disabled: disabled || undefined,
				readOnly: readOnly || undefined,
				required: required || undefined,
				'aria-invalid': invalid ? 'true' : undefined,
				'aria-describedby': context.get('describeIds') || undefined,
				'aria-required': required ? 'true' : undefined,
				'data-invalid': invalid ? '' : undefined,
				'data-disabled': disabled ? '' : undefined,
				'data-readonly': readOnly ? '' : undefined,
				// Drive the bound TanStack field directly from the native control
				// (plain input / textarea / select rendered via `asChild`). Composite
				// primitives report their value separately via `pushFieldValue`.
				onInput(event) {
					field?.handleChange(getInputValue(event.target) as never);
				},
				onChange(event) {
					field?.handleChange(getInputValue(event.target) as never);
				},
				onBlur() {
					field?.handleBlur();
				},
			});
		},

		getErrorProps() {
			return normalize.element({
				...parts.error.attrs,
				id: dom.getErrorId(scope),
				hidden: !invalid,
			});
		},

		getDescriptionProps() {
			return normalize.element({
				...parts.description.attrs,
				id: dom.getDescriptionId(scope),
			});
		},
	};
}
