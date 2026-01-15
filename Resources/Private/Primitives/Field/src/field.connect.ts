import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './field.anatomy';
import * as dom from './field.dom';
import type { FieldApi, FieldSchema } from './field.types';

export function connect<T extends PropTypes>(
	service: Service<FieldSchema>,
	normalize: NormalizeProps<T>
): FieldApi {
	const { scope, prop, context, computed } = service;

	const invalid = context.get('invalid');
	const disabled = context.get('disabled');
	const required = context.get('required');
	const readOnly = context.get('readOnly');
	const errors = computed('errors');

	return {
		getFormMachine: () => context.get('formMachine'),

		invalid,
		disabled,
		required,
		readOnly,
		errors,

		name: prop('name'),

		getErrorText() {
			return errors.length ? errors[0] : null;
		},

		getRootProps() {
			return normalize.element({
				...parts.root.attrs,
				id: dom.getRootId(scope),
				'data-invalid': invalid ? '' : undefined,
				'data-disabled': disabled ? '' : undefined,
				'data-readonly': readOnly ? '' : undefined,
				'data-required': required ? '' : undefined,
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
