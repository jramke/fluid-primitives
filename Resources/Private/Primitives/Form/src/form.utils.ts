import * as v from 'valibot';
import type { FormErrors, FormSchema, FormValues } from './form.types';

export function toKeyPath(path?: Array<{ key?: string | number; index?: number }>) {
	if (!path || path.length === 0) return '';
	const parts = path
		.map(p => {
			if (typeof p.key === 'number') return String(p.key);
			if (typeof p.key === 'string') return p.key;
			if (typeof p.index === 'number') return String(p.index);
			return '';
		})
		.filter(Boolean);
	return parts.join('.');
}

export function errorsFromValibot(result: v.SafeParseResult<any>): FormErrors {
	if (result.success) return {};
	const out: FormErrors = {};
	for (const issue of result.issues) {
		const key = toKeyPath(issue.path) || (typeof issue.input === 'string' ? issue.input : '');
		const message = issue.message ?? 'Invalid value';
		if (!out[key]) out[key] = [];
		out[key].push(message);
	}
	return out;
}

export function getInputValue(target: EventTarget | null): unknown {
	const el = target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null;
	if (!el || !('type' in el)) return undefined;

	if ((el as HTMLInputElement).type === 'checkbox') {
		const input = el as HTMLInputElement;
		if (input.name) {
			const form = input.form;
			if (form) {
				const checkboxes = Array.from(
					form.querySelectorAll(
						`input[type="checkbox"][name="${CSS.escape(input.name)}"]`
					)
				) as HTMLInputElement[];
				const values = checkboxes.filter(c => c.checked).map(c => c.value || 'on');
				if (checkboxes.length === 1 && !checkboxes[0].value) {
					return checkboxes[0].checked;
				}
				return values;
			}
		}
		return input.checked;
	}

	if ((el as HTMLInputElement).type === 'radio') {
		const input = el as HTMLInputElement;
		if (input.form && input.name) {
			const selected = input.form.querySelector(
				`input[type="radio"][name="${CSS.escape(input.name)}"]:checked`
			) as HTMLInputElement | null;
			return selected ? selected.value : '';
		}
		return input.checked ? input.value : '';
	}

	if (el instanceof HTMLSelectElement && el.multiple) {
		return Array.from(el.selectedOptions).map(o => o.value);
	}

	return (el as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement).value;
}

export function snapshotFormValues(form: HTMLFormElement): FormValues {
	const data = new FormData(form);
	const values: FormValues = {};
	for (const [key, raw] of data.entries()) {
		const prev = values[key];
		if (prev === undefined) {
			values[key] = raw;
		} else if (Array.isArray(prev)) {
			values[key] = [...prev, raw];
		} else {
			values[key] = [prev, raw];
		}
	}
	const inputs = Array.from(form.querySelectorAll('input[name]')) as HTMLInputElement[];
	for (const input of inputs) {
		if (input.type === 'checkbox') {
			if (inputs.filter(i => i.name === input.name).length === 1) {
				if (!(input.name in values)) values[input.name] = false;
			}
		}
	}
	return values;
}

// Helper: emit update event (called after any state-changing action)
export function emitUpdate(formId: string, context: FormSchema['context']) {
	const formEl = document.getElementById(formId) as HTMLFormElement | null;
	if (!formEl) return;
	formEl.dispatchEvent(
		new CustomEvent('fluid-primitives:form:update', {
			bubbles: true,
			detail: {
				values: context.values,
				errors: context.errors,
				dirty: context.dirty,
			},
		})
	);
}
