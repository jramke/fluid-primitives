import type { Service } from '@zag-js/core';
import { dataAttr } from '@zag-js/dom-query';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './form.anatomy';
import * as dom from './form.dom';
import { getFieldMachinesFor } from './form.registry';
import type { FormApi, FormDirty, FormSchema } from './form.types';

export function connect<T extends PropTypes>(
	service: Service<FormSchema>,
	normalize: NormalizeProps<T>
): FormApi {
	const { context, state, send, scope, prop } = service;

	function getValues() {
		return context.get('values');
	}
	function getErrors() {
		return context.get('errors');
	}
	function getDirty() {
		return context.get('dirty');
	}

	function getFormEl() {
		return dom.getFormEl(scope);
	}

	const isSubmitting = state.matches('submitting');
	const isDirty = Object.values(getDirty()).some(v => v);
	const isInvalid = Object.values(getErrors()).some(v => v);
	const isSuccessful = state.matches('success');
	const isError = state.matches('error');
	const stateValue = state.get();

	return {
		isSubmitting,
		isDirty,
		isInvalid,
		isSuccessful,
		isError,

		getValues,
		getErrors,
		getDirty,

		userRenderFn: prop('render'),

		getFormEl,
		getFields() {
			return getFieldMachinesFor(dom.getFormEl(scope));
		},
		getAction() {
			const formEl = getFormEl();
			return formEl.getAttribute('action') || '';
		},

		getFormProps() {
			return normalize.element({
				...parts.form.attrs,
				noValidate: dataAttr(true),
				id: dom.getFormId(scope),
				'data-state': stateValue,
				onSubmit: async event => {
					event.preventDefault();
					const form = event.currentTarget as HTMLFormElement;
					context.set('values', new FormData(form));
					send({ type: 'SUBMIT', detail: { event, api: this } });
				},
				onReset: event => {
					const form = event.currentTarget as HTMLFormElement;
					queueMicrotask(() => {
						const nextValues = new FormData(form);
						context.set('initialValues', nextValues);
						context.set('values', nextValues);
						context.set('errors', {});
						const dirty: FormDirty = {};
						for (const key of Object.keys(nextValues)) dirty[key] = false;
						context.set('dirty', dirty);
					});
					send({ type: 'RESET' });
				},
				// for things like inputs
				onInput: event => {
					send({ type: 'INPUT', detail: { target: event.target } });
				},
				// for things like selects or checkboxes
				onChange_: (event: any) => {
					send({ type: 'INPUT', detail: { target: event.target } });
				},
			});
		},
	};
}
