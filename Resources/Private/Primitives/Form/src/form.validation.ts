import { serializeFieldValue } from '../../Field/src/field.utils';
import {
    type FieldPathSegment,
    normalizeFieldName,
    stringifyFieldPathAsBrackets,
    toCanonicalFieldName,
} from './form.path';
import {
    type FieldError,
    type FormErrors,
    type FormValidation,
    type FormValues,
    type StandardSchemaIssue,
    type StandardSchemaResult,
    type StandardSchemaV1,
} from './form.types';
import { getFieldErrorValue } from './form.values';

export function validateWithValidation(
    validation: FormValidation | undefined,
    values: FormValues,
    fieldName?: string
): FormErrors {
    if (!validation) {
        return {};
    }

    if (typeof validation === 'function') {
        return attachErrorValues(
            validation({
                values,
                fieldName,
                validateWithStandardSchema: schema => validateWithStandardSchema(schema, values),
            }) ?? {},
            values
        );
    }

    return attachErrorValues(validateWithStandardSchema(validation, values), values);
}

export function validateWithStandardSchema(
    schema: StandardSchemaV1,
    values: FormValues
): FormErrors {
    const validationResult = schema['~standard'].validate(values.toObject());

    if (isPromiseLike(validationResult)) {
        throw new TypeError(
            'Form validation must be synchronous. Use onSubmit for async validation.'
        );
    }

    if (!validationResult.issues || validationResult.issues.length === 0) {
        return {};
    }

    return errorsFromIssues(validationResult.issues);
}

export function attachErrorValues(errors: FormErrors, values: FormValues): FormErrors {
    return Object.fromEntries(
        Object.entries(errors).map(([fieldName, error]) => [
            toCanonicalFieldName(fieldName),
            {
                ...error,
                value:
                    error.value === undefined ? getFieldErrorValue(values, fieldName) : error.value,
            },
        ])
    );
}

export function filterErrorsForCurrentValues(
    serverErrors: FormErrors,
    values: FormValues
): FormErrors {
    const errors: FormErrors = {};

    for (const fieldName in serverErrors) {
        const error = getCurrentErrorForField(serverErrors, fieldName, values);
        if (error) {
            errors[fieldName] = error;
        }
    }

    return errors;
}

export function getCurrentErrorForField(
    errors: FormErrors,
    fieldName: string,
    values: FormValues
): FieldError | undefined {
    const error = errors[normalizeFieldName(fieldName)];
    if (!error || error.value === undefined) return;

    const currentValue = getFieldErrorValue(values, fieldName);
    return serializeFieldValue(currentValue) === serializeFieldValue(error.value)
        ? error
        : undefined;
}

export function getFormErrorMessages(
    responseErrors: Record<string, string[]>,
    objectName: string | undefined
): string[] | null {
    if (!objectName) {
        return null;
    }

    return responseErrors[objectName] ?? null;
}

export function mapServerErrors(
    responseErrors: Record<string, string[]>,
    objectName: string | undefined,
    values: FormValues
): FormErrors {
    const out: FormErrors = {};

    for (const key in responseErrors) {
        const canonicalFieldName = toCanonicalFieldName(key, objectName);
        out[canonicalFieldName] = {
            messages: responseErrors[key],
            value: getFieldErrorValue(values, canonicalFieldName),
        };
    }

    return out;
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
    if (!issue.path || issue.path.length === 0) {
        return undefined;
    }

    const fieldPath: FieldPathSegment[] = [];

    for (const pathSegment of issue.path) {
        const key =
            typeof pathSegment === 'object' && pathSegment !== null && 'key' in pathSegment
                ? pathSegment.key
                : pathSegment;

        if (typeof key === 'symbol') {
            return undefined;
        }

        if (typeof key === 'number') {
            fieldPath.push(key);
            continue;
        }

        fieldPath.push(String(key));
    }

    if (fieldPath.length === 0) {
        return undefined;
    }

    // Bracket notation throughout (people[0][firstName], not people[0].firstName) - see
    // toCanonicalFieldName's own comment in form.path.ts for why: a registered field machine is
    // keyed by its own name exactly as Field renders it, never dot-separated past the first
    // array index, so a schema issue path like ['people', 0, 'firstName'] must stringify to match.
    return normalizeFieldName(stringifyFieldPathAsBrackets(fieldPath));
}

function isPromiseLike(result: StandardSchemaResult | Promise<StandardSchemaResult>) {
    return typeof result === 'object' && result !== null && 'then' in result;
}
