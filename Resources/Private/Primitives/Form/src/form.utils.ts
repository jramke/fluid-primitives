import * as v from 'valibot';
import type { FormErrors } from './form.types';

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

export function errorsFromValibot(result: v.SafeParseResult<any>, key?: string): FormErrors {
	if (result.success) return {};
	const out: FormErrors = {};
	for (const issue of result.issues) {
		const errorKey: string =
			key || toKeyPath(issue.path) || (typeof issue.input === 'string' ? issue.input : '');
		const message = issue.message ?? 'Invalid value';
		if (!out[errorKey]) out[errorKey] = [];
		out[errorKey].push(message);
	}
	return out;
}

export function errorsFromServer(
	responseErrors: Record<string, string[]>,
	objectName?: string
): FormErrors {
	const out: FormErrors = {};
	for (const key in responseErrors) {
		let newKey = key;
		if (objectName) {
			newKey = newKey.replace(objectName + '.', '');
		}
		out[newKey] = responseErrors[key];
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

export function prefixFieldName(fieldName: string, prefix: string, objectName?: string) {
	if (fieldName === '') {
		return '';
	}

	if (objectName) {
		fieldName = `${objectName}[${fieldName}]`;
	}

	const fieldNameSegments = fieldName.split('[', 2);
	let prefixedFieldName = `${prefix}[${fieldNameSegments[0]}]`;

	if (fieldNameSegments.length > 1) {
		prefixedFieldName += `[${fieldNameSegments[1]}`;
	}

	return prefixedFieldName;
}
