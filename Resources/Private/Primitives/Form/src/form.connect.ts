import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { debounce } from '@zag-js/utils';
import { parts } from './form.anatomy';
import * as dom from './form.dom';
import { getFieldMachinesFor } from './form.registry';
import type { FormApi, FormSchema } from './form.types';
import { objectToFormData } from './form.utils';

function getDirtyMap(service: Service<FormSchema>) {
	const formApi = service.context.get('formApi');
	if (!formApi) {
		return service.context.get('dirty');
	}

	return Object.fromEntries(
		Object.entries(formApi.state.fieldMeta)
			.filter(([, meta]) => meta?.isDirty)
			.map(([name]) => [name, true])
	);
}

function getTouchedMap(service: Service<FormSchema>) {
	const formApi = service.context.get('formApi');
	if (!formApi) {
		return service.context.get('touched');
	}

	return Object.fromEntries(
		Object.entries(formApi.state.fieldMeta)
			.filter(([, meta]) => meta?.isTouched)
			.map(([name]) => [name, true])
	);
}

export function connect<T extends PropTypes>(
	service: Service<FormSchema>,
	normalize: NormalizeProps<T>
): FormApi {
	const { context, state, send, scope, prop } = service;
	const formApi = context.get('formApi');

	function getValues() {
		if (!formApi) {
			return context.get('values');
		}

		return objectToFormData(formApi.state.values as Record<string, unknown>);
	}
	function getErrors() {
		return context.get('errors');
	}
	function getDirty() {
		return getDirtyMap(service);
	}
	function getTouched() {
		return getTouchedMap(service);
	}

	function getFormEl() {
		return dom.getFormEl(scope);
	}

	const isSubmitting = formApi?.state.isSubmitting ?? state.matches('submitting');
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

		getFormCoreApi() {
			return formApi;
		},
		getValues,
		getErrors,
		getDirty,
		getTouched,

		_userRenderFn: prop('render'),

		getFormEl,
		getAllFields() {
			return getFieldMachinesFor(dom.getFormEl(scope));
		},
		getField(name: string) {
			return this.getAllFields().get(name);
		},
		getAction() {
			const formEl = getFormEl();
			return formEl?.getAttribute('action') || '';
		},

		reset() {
			send({ type: 'RESET' });
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
				onReset: () => {
					send({ type: 'RESET', detail: { omitManualReset: true } });
				},
				// for things like inputs, others like select elements are handeled via `setupFormListeners`
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
			});
		},
	};
}
