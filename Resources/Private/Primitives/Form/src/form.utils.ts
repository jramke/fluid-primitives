import type {
	AnyFormControlElement,
	FormErrors,
	FormValidation,
	StandardSchemaIssue,
	StandardSchemaResult,
	StandardSchemaV1,
} from './form.types';

export function validateWithValidation(
	validation: FormValidation | undefined,
	formData: FormData,
	fieldName?: string
): FormErrors {
	if (!validation) {
		return {};
	}

	if (typeof validation === 'function') {
		return withCurrentValues(validation({ formData, fieldName }) ?? {}, formData);
	}

	return validateWithStandardSchema(validation, formData);
}

function validateWithStandardSchema(schema: StandardSchemaV1, formData: FormData): FormErrors {
	const dataObject = formDataToObject(formData);
	const validationResult = schema['~standard'].validate(dataObject);

	if (isPromiseLike(validationResult)) {
		throw new TypeError(
			'Form validation must be synchronous. Use onSubmit for async validation.'
		);
	}

	if (!validationResult.issues || validationResult.issues.length === 0) {
		return {};
	}

	return withCurrentValues(errorsFromIssues(validationResult.issues), formData);
}

function errorsFromIssues(issues: readonly StandardSchemaIssue[]): FormErrors {
	const errors: FormErrors = {};

	for (const issue of issues) {
		const fieldName = getIssueFieldName(issue);
		if (!fieldName) continue;

		const existingError = errors[fieldName];
		if (existingError) {
			existingError.messages.push(issue.message);
			continue;
		}

		errors[fieldName] = { messages: [issue.message] };
	}

	return errors;
}

function getIssueFieldName(issue: StandardSchemaIssue): string | undefined {
	const pathSegment = issue.path?.[0];
	if (pathSegment === undefined) {
		return undefined;
	}

	const key =
		typeof pathSegment === 'object' && pathSegment !== null && 'key' in pathSegment
			? pathSegment.key
			: pathSegment;

	if (typeof key === 'symbol') {
		return undefined;
	}

	return normalizeFieldName(String(key));
}

function isPromiseLike(result: StandardSchemaResult | Promise<StandardSchemaResult>) {
	return typeof result === 'object' && result !== null && 'then' in result;
}

export function errorsFromServer(
	responseErrors: Record<string, string[]>,
	objectName: string | undefined,
	formData: FormData
): FormErrors {
	const out: FormErrors = {};
	for (const key in responseErrors) {
		let newKey = key;
		if (objectName) {
			newKey = newKey.replace(objectName + '.', '');
		}
		out[newKey] = {
			messages: responseErrors[key],
			value: getFieldValue(formData, newKey),
		};
	}
	return out;
}

export function normalizeFieldName(fieldName: string) {
	return fieldName.replace(/\[\]$/, '');
}

export function withCurrentValues(errors: FormErrors, formData: FormData): FormErrors {
	return Object.fromEntries(
		Object.entries(errors).map(([fieldName, error]) => [
			normalizeFieldName(fieldName),
			{
				...error,
				value: error.value === undefined ? getFieldValue(formData, fieldName) : error.value,
			},
		])
	);
}

export function getErrorsForCurrentValues(
	serverErrors: FormErrors,
	formData: FormData
): FormErrors {
	const errors: FormErrors = {};

	for (const fieldName in serverErrors) {
		const currentValue = getFieldValue(formData, fieldName);
		const error = getErrorForValue(serverErrors, fieldName, currentValue);
		if (error) {
			errors[fieldName] = error;
		}
	}

	return errors;
}

export function getErrorForValue(
	errors: FormErrors,
	fieldName: string,
	currentValue: FormDataEntryValue | FormDataEntryValue[] | null
) {
	const error = errors[normalizeFieldName(fieldName)];
	if (!error || error.value === undefined) return;
	return serializeFieldValue(currentValue) === serializeFieldValue(error.value)
		? error
		: undefined;
}

export function getFieldValue(formData: FormData, fieldName: string) {
	const normalizedFieldName = normalizeFieldName(fieldName);
	const arrayEntries = formData.getAll(`${normalizedFieldName}[]`);
	const values = formData.getAll(normalizedFieldName);
	const entries = values.length > 0 ? values : arrayEntries;
	const isArrayValue = fieldName.endsWith('[]') || arrayEntries.length > 0 || entries.length > 1;

	if (isArrayValue) {
		return entries;
	}

	return entries[0] ?? null;
}

export function getFieldElement(form: HTMLFormElement, fieldName: string) {
	return form.querySelector(
		`[name="${CSS.escape(fieldName)}"], [name="${CSS.escape(fieldName)}[]"]`
	) as AnyFormControlElement | null;
}

export function serializeFieldValue(value: FormDataEntryValue | FormDataEntryValue[] | null) {
	return JSON.stringify(toSerializableFieldValue(value));
}

function toSerializableFieldValue(
	value: FormDataEntryValue | FormDataEntryValue[] | null
): unknown {
	if (Array.isArray(value)) {
		return value.map(toSerializableFieldValue);
	}

	if (value instanceof File) {
		return {
			name: value.name,
			size: value.size,
			type: value.type,
			lastModified: value.lastModified,
		};
	}

	return value;
}

export function getInputValue(target: EventTarget | null): unknown {
	const el = target as AnyFormControlElement | null;
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

	return (el as AnyFormControlElement).value;
}

export function formDataToObject(
	formData: FormData
): Record<string, FormDataEntryValue | FormDataEntryValue[]> {
	const fieldNames = new Set(Array.from(formData.keys(), normalizeFieldName));

	return Object.fromEntries(
		Array.from(fieldNames, fieldName => [fieldName, getFieldValue(formData, fieldName) ?? ''])
	);
}

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
