import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './form.anatomy';
import * as dom from './form.dom';
import { getFieldMachinesFor } from './form.registry';
import type { FormApi, FormErrors, FormSchema, FormValues } from './form.types';
import { valuesToFormData } from './form.utils';

export function connect<T extends PropTypes>(
	service: Service<FormSchema>,
	normalize: NormalizeProps<T>
): FormApi {
	const { state, send, scope, prop, refs } = service;

	const form = refs.get('form');
	const formState = form?.state;

	function getFormEl() {
		return dom.getFormEl(scope);
	}

	function getValues(): FormValues {
		return formState?.values ?? {};
	}

	function getErrors(): FormErrors {
		const errors: FormErrors = {};
		const fieldMeta = (formState?.fieldMeta ?? {}) as Record<string, { errors: unknown[] }>;
		const values = getValues();
		for (const name in fieldMeta) {
			const messages = [
				...new Set(
					(fieldMeta[name]?.errors ?? [])
						.map(err => normalizeError(err))
						.filter((msg): msg is string => !!msg)
				),
			];
			if (messages.length > 0) {
				errors[name] = { messages, value: values[name] };
			}
		}
		return errors;
	}

	const isSubmitting = state.matches('submitting') || !!formState?.isSubmitting;
	const isSubmitted = !!formState?.isSubmitted;
	const isDirty = !!formState?.isDirty;
	const isValidating = !!formState?.isValidating;
	const isInvalid = state.matches('invalid') || !(formState?.isValid ?? true);
	const isSuccessful = state.matches('success');
	const isError = state.matches('error');
	const isTouched = !!formState?.isTouched;
	const stateValue = state.get();

	return {
		form: form ?? null,

		isSubmitting,
		isSubmitted,
		isDirty,
		isValidating,
		isInvalid,
		isSuccessful,
		isError,
		isTouched,

		getValues,
		getErrors,
		getFormData(): FormData {
			return valuesToFormData(getValues());
		},

		_userRenderFn: prop('render'),

		getFormEl,
		getAllFields() {
			return getFieldMachinesFor(getFormEl());
		},
		getField(name: string) {
			return this.getAllFields().get(name);
		},
		getAction() {
			return getFormEl()?.getAttribute('action') || '';
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
				onSubmit: event => {
					event.preventDefault();
					send({ type: 'SUBMIT', detail: { event, api: this } });
				},
				onReset: () => {
					send({ type: 'RESET', detail: { omitManualReset: true } });
				},
			});
		},
	};
}

/** TanStack errors can be strings or Standard Schema issues; normalize to a string. */
function normalizeError(err: unknown): string | undefined {
	if (err == null) return undefined;
	if (typeof err === 'string') return err;
	if (typeof err === 'object' && 'message' in err) {
		return String((err as { message: unknown }).message);
	}
	return String(err);
}
