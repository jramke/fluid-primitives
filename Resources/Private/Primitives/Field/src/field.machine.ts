import { FieldApi as TanstackFieldApi } from '@tanstack/form-core';
import { createMachine } from '@zag-js/core';
import { FORM_REGISTERED_EVENT, getTanstackFormFor } from '../../Form/src/form.registry';
import { getInputValue, getServerFieldError } from '../../Form/src/form.utils';
import * as dom from './field.dom';
import type { FieldSchema, TanstackField } from './field.types';

export const machine = createMachine<FieldSchema>({
	// debug: true,
	initialState() {
		return 'ready';
	},
	context({ bindable, prop }) {
		return {
			invalid: bindable(() => ({ defaultValue: prop('invalid') ?? false })),
			required: bindable(() => ({ defaultValue: prop('required') ?? false })),
			disabled: bindable(() => ({ defaultValue: prop('disabled') ?? false })),
			readOnly: bindable(() => ({ defaultValue: prop('readOnly') ?? false })),
			describeIds: bindable<string | undefined>(() => ({ defaultValue: undefined })),
			hasDescription: bindable(() => ({ defaultValue: false })),
			fieldVersion: bindable<number>(() => ({ defaultValue: 0 })),
		};
	},
	refs() {
		return {
			field: null as TanstackField | null,
		};
	},
	entry: ['connectToForm', 'checkForDescription', 'determineDescribeIds', 'updateInvalid'],
	states: {
		ready: {},
	},
	computed: {
		errors({ refs }) {
			const field = refs.get('field');
			if (!field) return [] as string[];
			const messages = (field.state.meta.errors ?? [])
				.map((err: unknown) => normalizeError(err))
				.filter((msg: string | undefined): msg is string => !!msg);
			// onChange + onSubmit validators often produce the same message; dedupe.
			return [...new Set(messages)];
		},
		touched({ refs }) {
			return !!refs.get('field')?.state.meta.isTouched;
		},
		dirty({ refs }) {
			return !!refs.get('field')?.state.meta.isDirty;
		},
	},
	watch({ track, context, computed, action }) {
		track([() => context.get('fieldVersion'), () => computed('errors').join('|')], () => {
			action(['updateInvalid']);
		});

		track([() => context.get('invalid'), () => context.get('hasDescription')], () => {
			action(['determineDescribeIds']);
		});
	},
	implementations: {
		actions: {
			connectToForm({ refs, scope, prop, context }) {
				if (refs.get('field')) return;

				const fieldRootEl = dom.getRootEl(scope);
				if (!fieldRootEl) return;

				const mountField = () => {
					const form = getTanstackFormFor(fieldRootEl);
					if (!form) return;

					const field = new TanstackFieldApi({
						form: form as any,
						name: prop('name'),
						validators: prop('validators') as any,
					});
					field.mount();
					refs.set('field', field as unknown as TanstackField);

					// Seed the initial value from the field's control element (a
					// plain input / textarea / select rendered via `asChild`).
					// Composite primitives push their own JS-typed values from
					// `getFieldValue()` on first render.
					const controlEl = dom.getControlEl(scope);
					if (controlEl && (controlEl as HTMLInputElement).name === prop('name')) {
						const initial = getInputValue(controlEl);
						if (initial !== undefined && initial !== '') {
							field.setValue(initial as never, { dontUpdateMeta: true });
						}
					}

					field.store.subscribe(() => {
						context.set('fieldVersion', context.get('fieldVersion') + 1);
					});

					context.set('fieldVersion', context.get('fieldVersion') + 1);
				};

				const form = getTanstackFormFor(fieldRootEl);
				if (form) {
					mountField();
				} else {
					const closestForm = fieldRootEl.closest('form');
					if (!closestForm) return;

					const handler = () => {
						mountField();
						closestForm.removeEventListener(FORM_REGISTERED_EVENT, handler);
					};
					closestForm.addEventListener(FORM_REGISTERED_EVENT, handler);
				}
			},
			checkForDescription({ context, scope }) {
				context.set('hasDescription', !!dom.getDescriptionEl(scope));
			},
			determineDescribeIds({ context, scope }) {
				const ids: string[] = [];
				if (context.get('hasDescription')) {
					ids.push(dom.getDescriptionId(scope));
				}
				if (context.get('invalid')) {
					ids.push(dom.getErrorId(scope));
				}
				context.set('describeIds', ids.join(' ') || undefined);
			},
			updateInvalid({ context, prop, refs, computed }) {
				const field = refs.get('field');
				if (!field) {
					context.set('invalid', prop('invalid') ?? false);
					return;
				}

				// Only surface errors once the user has interacted with the field
				// or the form has been submitted, to avoid premature error display.
				const meta = field.state.meta;
				// const submitted = !!field.form?.state?.isSubmitted;
				// const shouldShow = meta.isTouched || meta.isBlurred || submitted;
				const shouldShow = meta.isTouched;

				context.set('invalid', shouldShow && computed('errors').length > 0);
			},
		},
	},
});

/**
 * Pushes a value into a field's TanStack `FieldApi` so it is reflected in form
 * validation and submission. Used by composite primitives (NumberInput, Select,
 * ...) that report their own value.
 *
 * When `touch` is true (a real user change) the field is marked touched/dirty
 * and change validators run. When false (the initial seed from server-rendered
 * state) the value is set quietly so rendering a primitive doesn't prematurely
 * surface "required" errors.
 *
 * Returns `true` if a value was written (i.e. the field exists and changed).
 */
export function pushFieldValue(
	fieldMachine: FieldMachineLike,
	value: unknown,
	touch: boolean
): boolean {
	const field = fieldMachine?.refs?.get('field') as TanstackField | null | undefined;
	if (!field) return false;

	// Avoid redundant updates that would retrigger validation needlessly.
	if (JSON.stringify(field.state.value) === JSON.stringify(value)) return false;

	if (touch) {
		updateServerError(field, value);
		field.handleChange(value as never);
	} else {
		field.setValue(value as never, { dontUpdateMeta: true });
	}
	return true;
}

/** Marks a field's TanStack `FieldApi` as blurred (fires blur validators). */
export function blurField(fieldMachine: FieldMachineLike) {
	const field = fieldMachine?.refs?.get('field') as TanstackField | null | undefined;
	field?.handleBlur();
}

type FieldMachineLike = { refs?: { get: (key: string) => unknown } } | undefined;

function normalizeError(err: unknown): string | undefined {
	if (err == null) return undefined;
	if (typeof err === 'string') return err;
	if (typeof err === 'object' && 'message' in err) {
		return String((err as { message: unknown }).message);
	}
	return String(err);
}

function updateServerError(field: TanstackField, nextValue: unknown) {
	const serverError = getServerFieldError(field.form, field.name);
	if (!serverError) return;

	const nextServerMessage =
		JSON.stringify(serverError.value) === JSON.stringify(nextValue)
			? serverError.messages.join(' ')
			: undefined;

	if (field.state.meta.errorMap?.onServer === nextServerMessage) return;

	field.setMeta(prev => ({
		...prev,
		errorMap: {
			...prev.errorMap,
			onServer: nextServerMessage,
		},
		errorSourceMap: {
			...prev.errorSourceMap,
			onServer: nextServerMessage ? 'form' : undefined,
		},
	}));
	field.form.setFieldMeta(field.name as never, prev => ({
		...prev,
		errorMap: {
			...prev.errorMap,
			onServer: nextServerMessage,
		},
		errorSourceMap: {
			...prev.errorSourceMap,
			onServer: nextServerMessage ? 'form' : undefined,
		},
	}));
}
