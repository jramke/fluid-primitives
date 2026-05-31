import type { FieldError, FormErrors, FormValues, TanstackForm } from './form.types';

const serverFieldErrors = new WeakMap<TanstackForm, Map<string, FieldError>>();

/**
 * Maps a server-side (Extbase) 422 error payload onto field-keyed errors.
 *
 * Server keys look like `objectName.field`; we strip the object name prefix so
 * the keys match the field `name` props used on the client.
 */
export function errorsFromServer(
	responseErrors: Record<string, string[]>,
	objectName: string | undefined,
	values: FormValues
): FormErrors {
	const out: FormErrors = {};
	for (const key in responseErrors) {
		let newKey = key;
		if (objectName) {
			newKey = newKey.replace(objectName + '.', '');
		}
		out[newKey] = {
			messages: responseErrors[key],
			value: values[stripArraySuffix(newKey)],
		};
	}
	return out;
}

export function setServerFieldError(form: TanstackForm, name: string, error: FieldError) {
	const key = stripArraySuffix(name);
	let errors = serverFieldErrors.get(form);
	if (!errors) {
		errors = new Map<string, FieldError>();
		serverFieldErrors.set(form, errors);
	}
	errors.set(key, error);
}

export function getServerFieldError(form: TanstackForm, name: string): FieldError | undefined {
	return serverFieldErrors.get(form)?.get(stripArraySuffix(name));
}

export function clearServerFieldErrors(form: TanstackForm) {
	serverFieldErrors.delete(form);
}

/**
 * Reads the current value of a form control as a JS-typed value:
 *
 * - single checkbox       -> boolean (or 'indeterminate' is treated as true here)
 * - multiple checkboxes   -> string[] of checked values
 * - radio                 -> string of the selected value (or '' if none)
 * - multiple select       -> string[] of selected values
 * - file input            -> File | File[] depending on `multiple`
 * - input[type=number]    -> number | '' (empty when not parseable)
 * - input[type=date|...]  -> the input's typed valueAsDate/valueAsNumber when meaningful
 * - everything else       -> string
 *
 * The TanStack form holds these JS-typed values; the FormData serializer at
 * submit time converts them to native form-submission semantics.
 */
export function getInputValue(target: EventTarget | null): unknown {
	const el = target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null;
	if (!el || !('type' in el)) return undefined;

	if (el instanceof HTMLInputElement && el.type === 'checkbox') {
		if (el.name && el.form) {
			const checkboxes = Array.from(
				el.form.querySelectorAll(`input[type="checkbox"][name="${CSS.escape(el.name)}"]`)
			) as HTMLInputElement[];

			// Multiple checkboxes sharing a name -> array of checked values.
			if (checkboxes.length > 1) {
				return checkboxes.filter(c => c.checked).map(c => c.value || 'on');
			}
		}
		// Single checkbox -> boolean, regardless of its value attribute.
		return el.checked;
	}

	if (el instanceof HTMLInputElement && el.type === 'radio') {
		if (el.form && el.name) {
			const selected = el.form.querySelector(
				`input[type="radio"][name="${CSS.escape(el.name)}"]:checked`
			) as HTMLInputElement | null;
			return selected ? selected.value : '';
		}
		return el.checked ? el.value : '';
	}

	if (el instanceof HTMLInputElement && el.type === 'file') {
		const files = Array.from(el.files ?? []);
		return el.multiple ? files : (files[0] ?? null);
	}

	if (el instanceof HTMLInputElement && el.type === 'number') {
		if (el.value === '') return '';
		return el.valueAsNumber;
	}

	if (el instanceof HTMLSelectElement && el.multiple) {
		return Array.from(el.selectedOptions).map(o => o.value);
	}

	return (el as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement).value;
}

export function stripArraySuffix(name: string): string {
	return name.endsWith('[]') ? name.slice(0, -2) : name;
}

/**
 * Serializes the JS-typed TanStack form values into `FormData` using native
 * HTML form-submission semantics:
 *
 * - `true`           -> '1' (Extbase-friendly)
 * - `false`          -> omitted (matches an unchecked checkbox in a real form)
 * - `null`/`undefined` -> omitted
 * - `File`           -> appended as Blob
 * - `File[]`         -> multiple appends with `name[]`
 * - `string[]`       -> multiple appends with `name[]`
 * - `Date`           -> ISO string
 * - `number`         -> `String(n)`
 * - everything else  -> `String(value)`
 */
export function valuesToFormData(values: FormValues): FormData {
	const formData = new FormData();
	for (const key in values) {
		appendValue(formData, key, values[key]);
	}
	return formData;
}

function appendValue(formData: FormData, key: string, value: unknown) {
	if (value === null || value === undefined || value === false) return;

	if (Array.isArray(value)) {
		if (value.length === 0) return;
		const name = key.endsWith('[]') ? key : `${key}[]`;
		for (const item of value) {
			if (item === null || item === undefined) continue;
			formData.append(name, toFormPart(item));
		}
		return;
	}

	formData.append(key, toFormPart(value));
}

function toFormPart(value: unknown): string | Blob {
	if (value instanceof Blob) return value;
	if (value === true) return '1';
	if (value instanceof Date) return value.toISOString();
	return String(value);
}

/** Adds the Extbase field-name-prefix (and optional object name) to a field name. */
export function prefixFieldName(fieldName: string, prefix: string, objectName?: string) {
	if (fieldName === '') {
		return '';
	}

	const isArrayField = fieldName.endsWith('[]');
	if (isArrayField) {
		fieldName = fieldName.slice(0, -2);
	}

	if (objectName) {
		fieldName = `${objectName}[${fieldName}]`;
	}

	const fieldNameSegments = fieldName.split('[', 2);
	let prefixedFieldName = `${prefix}[${fieldNameSegments[0]}]`;

	if (fieldNameSegments.length > 1) {
		prefixedFieldName += `[${fieldNameSegments[1]}`;
	}

	if (isArrayField) {
		prefixedFieldName += '[]';
	}

	return prefixedFieldName;
}
