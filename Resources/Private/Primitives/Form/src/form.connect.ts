import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { debounce } from '@zag-js/utils';
import { parts } from './form.anatomy';
import * as dom from './form.dom';
import { getFieldMachinesFor } from './form.registry';
import type { FormApi, FormSchema } from './form.types';

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
	function getTouched() {
		return context.get('touched');
	}

	function getFormEl() {
		return dom.getFormEl(scope);
	}

	const isSubmitting = state.matches('submitting');
	const isDirty = Object.values(getDirty()).length > 0;
	const isInvalid = Object.keys(getErrors()).length > 0;
	const isSuccessful = state.matches('success');
	const isError = state.matches('error');
	const isTouched = Object.values(getTouched()).length > 0;
	const stateValue = state.get();

	const inputDebounceMs = prop('inputDebounceMs') ?? 100;
	const debouncedSendInput =
		inputDebounceMs > 0
			? debounce((target: EventTarget | null) => {
					send({ type: 'INPUT', detail: { target } });
				}, inputDebounceMs)
			: (target: EventTarget | null) => {
					send({ type: 'INPUT', detail: { target } });
				};

	return {
		isSubmitting,
		isDirty,
		isInvalid,
		isSuccessful,
		isError,

		getValues,
		getErrors,
		getDirty,
		getTouched,

		userRenderFn: prop('render'),

		getFormEl,
		getFields() {
			return getFieldMachinesFor(dom.getFormEl(scope));
		},
		getAction() {
			const formEl = getFormEl();
			return formEl?.getAttribute('action') || '';
		},

		getFormProps() {
			return normalize.element({
				...parts.form.attrs,
				noValidate: true,
				id: dom.getFormId(scope),
				'data-state': stateValue,
				'data-submitting': isSubmitting ? '' : undefined,
				'data-invalid': isInvalid ? '' : undefined,
				'data-dirty': isDirty ? '' : undefined,
				'data-touched': isTouched ? '' : undefined,
				onSubmit: async event => {
					event.preventDefault();
					const form = event.currentTarget as HTMLFormElement;
					context.set('values', new FormData(form));
					send({ type: 'SUBMIT', detail: { event, api: this } });
				},
				onReset: _event => {
					send({ type: 'RESET' });
				},
				// for things like inputs
				onInput: event => {
					debouncedSendInput(event.target);
				},
				onBlur: event => {
					const target = event.target as
						| HTMLInputElement
						| HTMLTextAreaElement
						| HTMLSelectElement
						| HTMLButtonElement
						| null;
					// if (!target?.name) return;

					// Ensure target is a form field
					if (
						!(target instanceof HTMLInputElement) &&
						!(target instanceof HTMLTextAreaElement) &&
						!(target instanceof HTMLSelectElement) &&
						!(
							target instanceof HTMLButtonElement &&
							target.getAttribute('data-scope') === 'select'
						)
					) {
						return;
					}

					send({ type: 'BLUR', detail: { target } });
				},
				// TODO ??? for things like selects or checkboxes
				// can we get rid of this
				// onChange_: (event: any) => {
				// 	send({ type: 'INPUT', detail: { target: event.target } });
				// },
			});
		},
	};
}
