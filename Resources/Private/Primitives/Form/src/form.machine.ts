import { createMachine } from '@zag-js/core';
import * as v from 'valibot';
import * as dom from './form.dom';
import type { FormDirty, FormSchema } from './form.types';
import { errorsFromServer, errorsFromValibot, getInputValue, prefixFieldName } from './form.utils';

export const machine = createMachine<FormSchema>({
	initialState() {
		return 'ready';
	},

	context({ bindable }) {
		return {
			values: bindable(() => ({ defaultValue: new FormData() })),
			initialValues: bindable(() => ({ defaultValue: new FormData() })),
			errors: bindable(() => ({ defaultValue: {} })),
			dirty: bindable(() => ({ defaultValue: {} })),
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
		SUBMIT: { target: 'submitting', actions: ['validateAll'] },
		VALIDATE: { actions: ['validateAll'] },
		INVALID: { target: 'invalid' },
		INPUT: { actions: ['handleInput'] },
		RESET: { target: 'ready', actions: ['resetForm'] },
		ERROR: { target: 'error' },
		SUCCESS: { target: 'success' },
	},

	entry: ['handleFieldBlurs'],

	implementations: {
		actions: {
			validateAll({ context, send, prop, state, action, event, scope }) {
				const submitting = state.matches('submitting');
				const schema = prop('schema');

				if (schema) {
					const validationResult = v.safeParse(
						schema,
						Object.fromEntries(context.get('values').entries())
					);
					const errs = errorsFromValibot(validationResult);
					context.set('errors', errs);

					if (Object.keys(errs).length > 0) {
						send({ type: 'INVALID' });
						if (submitting) {
							action(['focusFirstInvalid']);
						}
						return;
					}
				}

				if (!submitting) {
					state.set('ready');
					return;
				}

				const onSubmit = prop('onSubmit');
				if (!onSubmit) {
					send({ type: 'SUCCESS' });
					return;
				}

				let result: boolean | Promise<boolean>;
				try {
					result = onSubmit({
						formData: context.get('values'),
						api: event.detail.api,
						event: event.detail.event,
						post: async (url: string, data: FormData) => {
							let prefixedData = new FormData();
							const objectName = prop('objectName');
							const prefix =
								dom.getFormEl(scope).getAttribute('data-field-name-prefix') || '';

							for (const [key, value] of data.entries()) {
								if (key.includes(prefix)) {
									prefixedData.append(key, value);
									continue;
								}
								prefixedData.append(
									prefixFieldName(key, prefix, objectName),
									value
								);
							}

							const response = await fetch(url, {
								method: 'POST',
								body: prefixedData,
							});

							if (response.status === 422) {
								const errors = await response.json();
								context.set('errors', errorsFromServer(errors, objectName));
								send({ type: 'INVALID' });
							}

							return response;
						},
					});
				} catch (error) {
					send({ type: 'ERROR' });
					return;
				}

				if (result instanceof Promise) {
					result
						.then(res => {
							if (res) {
								send({ type: 'SUCCESS' });
							} else {
								send({ type: 'ERROR' });
							}
						})
						.catch(() => {
							send({ type: 'ERROR' });
						});
				} else {
					if (result) {
						send({ type: 'SUCCESS' });
					} else {
						send({ type: 'ERROR' });
					}
				}
			},

			handleInput({ context, event, action, prop }) {
				const e = event as any;

				const target = e?.detail?.target ?? e?.target ?? e?.currentTarget;
				const name: string | undefined = target?.name;
				if (!name) return;

				const reactiveFields = prop('reactiveFields') || [];
				if (reactiveFields.length === 0 || !reactiveFields.includes(name)) {
					return;
				}

				const value = e?.detail?.value ?? getInputValue(target);

				const values = context.get('values');
				values.set(name, value);
				context.set('values', values);

				const initial = context.get('initialValues').get(name);
				const dirty = { ...context.get('dirty') };
				dirty[name] = JSON.stringify(values.get(name)) !== JSON.stringify(initial);
				context.set('dirty', dirty);

				// TODO: also keep validating on change when the errors are gone
				// like a min length and then the user updates it so its valid and then removes chars again we would want to show the error again
				// and we maybe want to allow revalidating on change also when the field is not declared as reactive
				if (context.get('errors')[name]) {
					action(['validateAll']);
				}
			},

			// maybeValidateChanged({ context, prop }) {
			// 	const validateOnChange = !!prop('validateOnChange');
			// 	if (!validateOnChange) return;
			// 	const schema = prop('schema');
			// 	const result = v.safeParse(schema, context.get('values'));
			// 	const errs = errorsFromValibot(result);
			// 	context.set('errors', errs);
			// },

			resetForm({ context }) {
				const initial = context.get('initialValues');
				context.set('values', { ...initial });
				context.set('errors', {});
				const dirty: FormDirty = {};
				for (const key of Object.keys(initial)) dirty[key] = false;
				context.set('dirty', dirty);
			},

			focusFirstInvalid({ context, scope }) {
				const errors = context.get('errors');
				const firstKey = Object.keys(errors)[0];
				if (!firstKey) return;

				const form = dom.getFormEl(scope)!;

				const invalidEl = form.querySelector(
					`[name="${CSS.escape(firstKey)}"]`
				) as HTMLElement | null;
				invalidEl?.focus();
			},

			handleFieldBlurs({ scope }) {
				const form = dom.getFormEl(scope);
				if (!form) return;

				const handleBlur = (event: FocusEvent) => {
					const target = event.target as
						| HTMLInputElement
						| HTMLTextAreaElement
						| HTMLSelectElement;
					const name = target?.name;
					if (!name) return;

					// TODO: set error state for the field
					// i think we still need to validate the whole form schema

					// const reactiveFields = prop('reactiveFields') || [];
					// if (reactiveFields.length === 0 || !reactiveFields.includes(name)) {
					// 	return;
					// }

					// const value = getInputValue(target);
					// const values = { ...context.get('values') };
					// values[name] = value;
					// context.set('values', values);

					// const initial = context.get('initialValues')[name];
					// const dirty = { ...context.get('dirty') };
					// dirty[name] = JSON.stringify(values[name]) !== JSON.stringify(initial);
					// context.set('dirty', dirty);

					// action(['validateAll']);
				};

				form.addEventListener('blur', handleBlur, true);
			},
		},
	},
});
