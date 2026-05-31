import { FormApi as TanstackFormApi } from '@tanstack/form-core';
import { createMachine } from '@zag-js/core';
import * as dom from './form.dom';
import { markFormReady } from './form.registry';
import type { FormApi, FormSchema, FormValues, TanstackForm } from './form.types';
import { SubmitError, ValidationError } from './form.types';
import {
	clearServerFieldErrors,
	errorsFromServer,
	prefixFieldName,
	setServerFieldError,
	stripArraySuffix,
	valuesToFormData,
} from './form.utils';

export const machine = createMachine<FormSchema>({
	// debug: true,
	// props(params) {
	// 	return {
	// 		id: uid(),
	// 		validationLogic: revalidateLogic({ mode: 'blur', modeAfterSubmission: 'change' }),
	// 		...params,
	// 	};
	// },
	initialState() {
		return 'ready';
	},

	context({ bindable }) {
		return {
			storeVersion: bindable<number>(() => ({ defaultValue: 0 })),
		};
	},

	refs() {
		return {
			form: null as TanstackForm | null,
			submitContext: null as { api?: FormApi; event?: any } | null,
		};
	},

	states: {
		ready: {},
		invalid: {},
		submitting: {},
		success: {},
		error: {},
	},

	on: {
		SUBMIT: { target: 'submitting', actions: ['runSubmit'] },
		INVALID: { target: 'invalid' },
		RESET: { target: 'ready', actions: ['resetForm'] },
		ERROR: { target: 'error' },
		SUCCESS: { target: 'success' },
	},

	entry: ['createTanstackForm'],

	effects: ['subscribeToStore'],

	implementations: {
		effects: {
			subscribeToStore({ refs, context }) {
				const form = refs.get('form');
				if (!form) return;
				const subscription = form.store.subscribe(() => {
					// mirror TanStack reactivity into the zag render loop
					context.set('storeVersion', context.get('storeVersion') + 1);
				});
				return () => subscription.unsubscribe();
			},
		},

		actions: {
			createTanstackForm({ refs, scope, prop }) {
				if (refs.get('form')) return;

				const formEl = dom.getFormEl(scope);
				// Default values are seeded by each Field/primitive on first render
				// (via `pushFieldValue`). Starting empty avoids string-coercing the
				// server-rendered DOM into JS-typed values too early.
				const form = new TanstackFormApi({
					defaultValues: {},
					asyncDebounceMs: prop('asyncDebounceMs'),
					validationLogic: prop('validationLogic'),
					validators: prop('validators') as any,
					// TanStack drives the whole submit lifecycle. Our user-facing
					// `onSubmit` runs here, after field + form validation has passed.
					onSubmit: async ({ value }) => {
						const onSubmit = prop('onSubmit');
						if (!onSubmit) return;

						const submitContext = refs.get('submitContext');
						const result = await onSubmit({
							value: value as FormValues,
							formData: valuesToFormData(value as FormValues),
							api: submitContext?.api as FormApi,
							event: submitContext?.event as any,
							post: createPost(scope, prop('objectName'), value as FormValues),
						});

						// A `false` return signals a generic (non-validation) error.
						if (result === false) throw new SubmitError();
					},
				});
				form.mount();

				refs.set('form', form as unknown as TanstackForm);

				// The TanStack form now exists; let waiting fields connect to it.
				markFormReady(formEl);
			},

			runSubmit({ refs, scope, send, event }) {
				const form = refs.get('form');
				if (!form) {
					send({ type: 'SUCCESS' });
					return;
				}

				refs.set('submitContext', {
					api: event.detail?.api,
					event: event.detail?.event,
				});

				(async () => {
					try {
						await form.handleSubmit();

						console.log(form.state);

						if (form.state.isSubmitSuccessful) {
							send({ type: 'SUCCESS' });
						} else {
							// Validation failed inside handleSubmit (no error thrown).
							send({ type: 'INVALID' });
							focusFirstInvalid(scope, form);
						}
					} catch (error) {
						if (error instanceof ValidationError) {
							applyServerErrors(form, error.errors);
							send({ type: 'INVALID' });
							focusFirstInvalid(scope, form);
							return;
						}
						send({ type: 'ERROR' });
					}
				})();
			},

			resetForm({ refs, scope, event }) {
				const formEl = dom.getFormEl(scope);
				if (formEl && !event?.detail?.omitManualReset) {
					formEl.reset();
				}

				// Reset to no values; primitives will reseed on the next render via
				// `pushFieldValue` once the DOM has been reset.
				const form = refs.get('form');
				if (form) {
					clearServerFieldErrors(form);
					form.reset();
				}
			},
		},
	},
});

/**
 * Builds the `post()` helper passed to the user `onSubmit`. Adds the Extbase
 * field-name-prefix before sending and throws a {@link ValidationError} on 422.
 */
function createPost(scope: any, objectName: string | undefined, values: FormValues) {
	return async (url: string, data: FormData): Promise<Response> => {
		const prefixedData = new FormData();
		const formElement = dom.getFormEl(scope);
		const prefix = formElement?.getAttribute('data-field-name-prefix') || '';

		for (const [key, value] of data.entries()) {
			if (prefix && key.includes(prefix)) {
				prefixedData.append(key, value);
				continue;
			}
			prefixedData.append(prefixFieldName(key, prefix, objectName), value);
		}

		// Forward any server-injected hidden fields (Extbase `__trustedProperties`,
		// `__referrer`, ...) that don't live in the TanStack form values.
		appendServerHiddenFields(prefixedData, formElement);

		const response = await fetch(url, { method: 'POST', body: prefixedData });

		if (response.status === 422) {
			const serverErrors = await response.json();
			throw new ValidationError(errorsFromServer(serverErrors, objectName, values));
		}

		return response;
	};
}

/**
 * Forwards Extbase's server-injected hidden fields (`__trustedProperties`,
 * `__referrer[...]`, ...) into the FormData. Their `name` attribute is already
 * fully prefixed by the server, so we append them as-is without re-prefixing.
 */
function appendServerHiddenFields(formData: FormData, formEl: HTMLFormElement | null) {
	if (!formEl) return;
	const hiddenInputs = formEl.querySelectorAll<HTMLInputElement>(
		'input[type="hidden"][name^="__"], input[type="hidden"][name*="[__"]'
	);
	for (const input of hiddenInputs) {
		formData.append(input.name, input.value);
	}
}

/** Writes server-side errors into the TanStack form's per-field error maps. */
function applyServerErrors(form: TanstackForm, errors: Record<string, { messages: string[] }>) {
	for (const name in errors) {
		const key = stripArraySuffix(name);
		const error = errors[name];
		setServerFieldError(form, key, error);
		form.setFieldMeta(key as never, prev => ({
			...prev,
			isTouched: true,
			errorMap: { ...prev.errorMap, onServer: error.messages.join(' ') },
			errorSourceMap: { ...prev.errorSourceMap, onServer: 'form' },
		}));
	}
}

function focusFirstInvalid(scope: any, form: TanstackForm) {
	const fieldMeta = form.state.fieldMeta as Record<string, { errors: unknown[] }>;
	const firstKey = Object.keys(fieldMeta).find(key => (fieldMeta[key]?.errors?.length ?? 0) > 0);
	if (!firstKey) return;

	const formEl = dom.getFormEl(scope);
	if (!formEl) return;

	const name = stripArraySuffix(firstKey);
	const invalidEl = (formEl.querySelector(`[name="${CSS.escape(name)}"]`) ||
		formEl.querySelector(`[name="${CSS.escape(`${name}[]`)}"]`)) as HTMLElement | null;
	invalidEl?.focus();
	if (invalidEl instanceof HTMLInputElement || invalidEl instanceof HTMLTextAreaElement) {
		invalidEl.select();
	}
}
