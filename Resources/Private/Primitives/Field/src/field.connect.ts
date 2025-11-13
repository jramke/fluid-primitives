import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './field.anatomy';
import * as dom from './field.dom';
import type { FieldApi, FieldSchema } from './field.types';

export function connect<T extends PropTypes>(
	service: Service<FieldSchema>,
	normalize: NormalizeProps<T>
): FieldApi {
	const { scope, prop, context } = service;

	function getFieldState() {
		const formMachine = context.get('formMachine');
		if (!formMachine) return { errors: [] as string[], invalid: false };

		const errs = formMachine.ctx.get('errors')?.[prop('name')] ?? [];
		return { errors: errs, invalid: errs.length > 0 };
	}

	const fieldState = getFieldState();

	return {
		getFormMachine: () => context.get('formMachine'),

		get invalid() {
			const { invalid } = fieldState;
			return invalid;
		},
		get errors() {
			const { errors } = fieldState;
			return errors;
		},

		name: prop('name'),

		getErrorText() {
			const errs = this.errors;
			return errs.length ? errs[0] : null;
		},

		getRootProps() {
			return normalize.element({
				...parts.root.attrs,
				id: dom.getRootId(scope),
				'data-invalid': this.invalid ? '' : undefined,
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
			// const errorId = this.invalid ? dom.getErrorId(scope) : undefined;
			const invalid = this.invalid;
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
				hidden: !this.invalid,
			});
		},
	};
}
