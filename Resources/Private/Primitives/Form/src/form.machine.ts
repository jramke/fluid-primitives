import { createMachine } from '@zag-js/core';
import * as v from 'valibot';
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
		INPUT: { target: 'ready', actions: ['applyInputChange', 'maybeValidateChanged'] },
		RESET: { target: 'ready', actions: ['resetForm'] },
		ERROR: { target: 'error' },
		SUCCESS: { target: 'success' },
	},

	implementations: {
		actions: {
			validateAll({ context, send, prop, state }) {
				const schema = prop('schema');
				const result = v.safeParse(schema, context.get('values'));
				const errs = errorsFromValibot(result);
				context.set('errors', errs);

				const submitting = state.matches('submitting');

				// if (Object.keys(errs).length === 0) {
				// 	if (submitting) {
				// 		// send({ type: 'SUCCESS' });
				// 		const onSubmit = prop('onSubmit');
				// 		if (onSubmit) {
				// 			const result = onSubmit(context.get('values'));
				// 			if (result instanceof Promise) {
				// 				result
				// 					.then(res => {
				// 						if (res) {
				// 							send({ type: 'SUCCESS' });
				// 						} else {
				// 							send({ type: 'ERROR' });
				// 						}
				// 					})
				// 					.catch(() => {
				// 						send({ type: 'ERROR' });
				// 					});
				// 			} else {
				// 				if (result) {
				// 					send({ type: 'SUCCESS' });
				// 				} else {
				// 					send({ type: 'ERROR' });
				// 				}
				// 			}
				// 		}
				// 	} else {
				// 		state.set('ready');
				// 	}
				// } else {
				// 	send({ type: 'ERROR' });
				// }
			},

			applyInputChange({ context, event }) {
				const e = event as any;
				const target = e?.detail?.target ?? e?.target ?? e?.currentTarget;
				const name: string | undefined = target?.name;
				if (!name) return;

				const value = e?.detail?.value ?? getInputValue(target);

				const values = { ...context.get('values') };
				values[name] = value;
				context.set('values', values);

				const initial = context.get('initialValues')[name];
				const dirty = { ...context.get('dirty') };
				dirty[name] = JSON.stringify(values[name]) !== JSON.stringify(initial);
				context.set('dirty', dirty);
			},

			async maybeValidateChanged({ context, prop }) {
				const validateOnChange = !!prop('validateOnChange');
				if (!validateOnChange) return;
				const schema = prop('schema');
				const result = await v.safeParseAsync(schema, context.get('values'));
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
		},
	},
});
