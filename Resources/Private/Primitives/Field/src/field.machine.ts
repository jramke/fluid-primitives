import { createMachine } from '@zag-js/core';
import { getFormMachineFor, type FormMachine } from '../../Form/src/form.registry';
import * as dom from './field.dom';
import type { FieldSchema } from './field.types';

export const machine = createMachine<FieldSchema>({
	initialState() {
		return 'ready';
	},
	context({ bindable, prop }) {
		return {
			invalid: bindable(() => ({ defaultValue: prop('invalid') ?? false })),
			required: bindable(() => ({ defaultValue: prop('required') ?? false })),
			disabled: bindable(() => ({ defaultValue: prop('disabled') ?? false })),
			readOnly: bindable(() => ({ defaultValue: prop('readOnly') ?? false })),
			formMachine: bindable(() => ({ defaultValue: null as FormMachine | null })),
			describeIds: bindable<string | undefined>(() => ({ defaultValue: undefined })),
		};
	},
	entry: ['getFormMachine', 'determineDescribeIds'],
	states: {
		ready: {},
	},
	computed: {
		// invalid({ context, prop }) {
		// 	if (prop('invalid') !== undefined) {
		// 		return prop('invalid')!;
		// 	}
		// 	const formMachineCtx = context.get('formMachine')?.ctx;
		// 	if (!formMachineCtx) return false;
		// 	const errs = formMachineCtx.get('errors')?.[prop('name')] ?? [];
		// 	return errs.length > 0;
		// },
		errors({ context, prop }) {
			const formMachineCtx = context.get('formMachine')?.ctx;
			if (!formMachineCtx) return [] as string[];
			const errs = formMachineCtx.get('errors')?.[prop('name')] ?? [];
			return errs;
		},
	},
	watch({ track, context, computed, prop, action }) {
		// const formMachineCtx = context.get('formMachine')?.ctx;
		track([() => JSON.stringify(context.get('formMachine')?.ctx?.get('errors'))], () => {
			context.set(
				'invalid',
				(context.get('formMachine')?.ctx?.get('errors')?.[prop('name')]?.length ?? 0) > 0
			);
			// TODO: check other props?
		});

		// TODO: also check descriptions when implemented
		track([() => context.get('invalid')], () => {
			action(['determineDescribeIds']);
		});
	},
	implementations: {
		actions: {
			getFormMachine({ context, scope }) {
				if (context.get('formMachine')) return;

				const fieldRootEl = dom.getRootEl(scope);
				if (!fieldRootEl) return;

				const formMachine = getFormMachineFor(fieldRootEl) ?? null;

				if (formMachine) {
					context.set('formMachine', formMachine);
				} else {
					const handler = () => {
						const fs = getFormMachineFor(fieldRootEl) ?? null;
						context.set('formMachine', fs);
						fieldRootEl.removeEventListener('fluid-primitives:form:ready', handler);
					};
					fieldRootEl.addEventListener('fluid-primitives:form:ready', handler);
				}
			},
			determineDescribeIds({ context, scope, computed }) {
				const ids: string[] = [];
				if (context.get('invalid')) ids.push(dom.getErrorId(scope));
				// TODO: add description ids when implemented
				const idsStr = ids.join(' ') || undefined;
				context.set('describeIds', idsStr);
			},
		},
	},
});
