import { createMachine } from '@zag-js/core';
import * as v from 'valibot';
import * as dom from './form.dom';
import type { FormDirty, FormSchema } from './form.types';
import { errorsFromValibot, getInputValue } from './form.utils';

export const machine = createMachine<FormSchema>({
	initialState() {
		return 'ready';
	},

	context({ bindable }) {
		return {
			values: bindable(() => ({ defaultValue: {} })),
			initialValues: bindable(() => ({ defaultValue: {} })),
			errors: bindable(() => ({ defaultValue: {} })),
			dirty: bindable(() => ({ defaultValue: {} })),
		};
	},

	states: {
		ready: {},
		validating: {},
		submitting: {},
		success: {},
		error: {},
	},

	on: {
		SUBMIT: { target: 'submitting', actions: ['validateAll'] },
		VALIDATE: { target: 'validating', actions: ['validateAll'] },
		VALIDATE_SINGLE_FIELD: { target: 'validating', actions: ['validateSingleField'] },
		INPUT: { target: 'ready', actions: ['applyInputChange', 'maybeValidateChanged'] },
		RESET: { target: 'ready', actions: ['resetForm'] },
		ERROR: { target: 'error' },
		SUCCESS: { target: 'success' },
	},

	entry: ['handleFieldBlurs'],

	implementations: {
		actions: {
			validateAll({ context, send, prop, state }) {
				const schema = prop('schema');
				const validationResult = v.safeParse(schema, context.get('values'));
				const errs = errorsFromValibot(validationResult);
				context.set('errors', errs);

				if (Object.keys(errs).length > 0) {
					send({ type: 'ERROR' });
					return;
				}

				const submitting = state.matches('submitting');
				if (!submitting) {
					state.set('ready');
					return;
				}

				const onSubmit = prop('onSubmit');
				if (!onSubmit) {
					send({ type: 'SUCCESS' });
					return;
				}

				const result = onSubmit(context.get('values'));
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

			applyInputChange({ context, event }) {
				const e = event as any;
				const target = e?.detail?.target ?? e?.target ?? e?.currentTarget;
				const name: string | undefined = target?.name;
				if (!name) return;

				const value = e?.detail?.value ?? getInputValue(target);
				console.log({ name, value });

				const values = { ...context.get('values') };
				values[name] = value;
				context.set('values', values);

				const initial = context.get('initialValues')[name];
				const dirty = { ...context.get('dirty') };
				dirty[name] = JSON.stringify(values[name]) !== JSON.stringify(initial);
				context.set('dirty', dirty);
			},

			maybeValidateChanged({ context, prop }) {
				const validateOnChange = !!prop('validateOnChange');
				if (!validateOnChange) return;
				const schema = prop('schema');
				const result = v.safeParse(schema, context.get('values'));
				const errs = errorsFromValibot(result);
				context.set('errors', errs);
			},

			resetForm({ context }) {
				const initial = context.get('initialValues');
				context.set('values', { ...initial });
				context.set('errors', {});
				const dirty: FormDirty = {};
				for (const key of Object.keys(initial)) dirty[key] = false;
				context.set('dirty', dirty);
			},

			handleFieldBlurs({ context, event, scope, prop, action }) {
				const form = dom.getFormEl(scope);
				Array.from(form?.elements || []).forEach(el => {
					console.log({ el });
					el.addEventListener('blur', () => {
						const schema = prop('schema');
						console.log('blurred, we validate', el, schema);
						action(['validateSingleField']);
					});
				});
			},
			validateSingleField({ prop, context, event }) {
				const name = (event as any)?.detail?.name as string | undefined;
				if (!name) return;

				const schema = prop('schema');
				if (!schema) return;

				const fieldSchema = (schema as any)?.entries[name];
				if (!fieldSchema) return;

				const results = v.safeParse(fieldSchema, context.get('values')[name]);
				const errs = errorsFromValibot(results);
				const existingErrors = context.get('errors');
				const nextErrors = { ...existingErrors };

				if (errs && Object.keys(errs).length > 0) {
					nextErrors[name] = errs[name];
				} else {
					delete nextErrors[name];
				}

				context.set('errors', nextErrors);
			},
		},
	},
});
