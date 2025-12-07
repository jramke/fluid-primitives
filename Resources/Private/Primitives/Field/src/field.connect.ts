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
	const errors = computed('errors');

	return {
		getFormMachine: () => context.get('formMachine'),

		invalid,
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
				'data-name': prop('name'),
			});
		},

		getLabelProps() {
			return normalize.label({
				...parts.label.attrs,
				id: dom.getLabelId(scope),
				htmlFor: dom.getControlId(scope),
			});
		},

		getControlProps() {
			return normalize.element({
				...parts.control.attrs,
				id: dom.getControlId(scope),
				name: prop('name'),
				'aria-invalid': invalid ? 'true' : 'false',
				'aria-describedby': context.get('describeIds'),
				'data-invalid': invalid ? '' : undefined,
			});
		},

		getErrorProps() {
			return normalize.element({
				...parts.error.attrs,
				id: dom.getErrorId(scope),
				hidden: !invalid,
			});
		},
	};
}
