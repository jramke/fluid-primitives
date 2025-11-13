import type { Service } from '@zag-js/core';
import { dataAttr } from '@zag-js/dom-query';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './form.anatomy';
import * as dom from './form.dom';
import type { FormApi, FormDirty, FormErrors, FormSchema, FormValues } from './form.types';
import { snapshotFormValues } from './form.utils';

export function connect<T extends PropTypes>(
	service: Service<FormSchema>,
	normalize: NormalizeProps<T>
): FormApi {
	const { context, state, send, scope } = service;

	function getValues(): FormValues {
		return context.get('values');
	}
	function getErrors(): FormErrors {
		return context.get('errors');
	}
	function getDirty(): FormDirty {
		return context.get('dirty');
	}

	return {
		isSubmitting: state.matches('submitting'),

		getValues,
		getErrors,
		getDirty,

		getFieldState(name: string) {
			const values = getValues();
			const errors = getErrors();
			const dirty = getDirty();
			const errList = errors[name] ?? [];
			return {
				value: values[name],
				errors: errList,
				dirty: !!dirty[name],
				invalid: errList.length > 0,
			};
		},

		getFormProps() {
			return normalize.element({
				...parts.form.attrs,
				noValidate: dataAttr(true),
				id: dom.getFormId(scope),
				'data-dirty': dataAttr(Object.values(getDirty()).some(v => v)),
				'data-invalid': dataAttr(Object.values(getErrors()).some(v => v)),
				'data-submitting': dataAttr(state.matches('submitting')),
				'data-success': dataAttr(state.matches('success')),
				onSubmit: async event => {
					event.preventDefault();
					const form = event.currentTarget as HTMLFormElement;
					const nextValues = snapshotFormValues(form);
					context.set('values', nextValues);
					send({ type: 'SUBMIT' });
				},
				onReset: event => {
					const form = event.currentTarget as HTMLFormElement;
					queueMicrotask(() => {
						const nextValues = snapshotFormValues(form);
						context.set('initialValues', nextValues);
						context.set('values', nextValues);
						context.set('errors', {});
						const dirty: FormDirty = {};
						for (const key of Object.keys(nextValues)) dirty[key] = false;
						context.set('dirty', dirty);
					});
					send({ type: 'RESET' });
				},
				// onInput: event => {
				// 	send({ type: 'INPUT', detail: { target: event.target } } as any);
				// },
			});
		},
	};
}
