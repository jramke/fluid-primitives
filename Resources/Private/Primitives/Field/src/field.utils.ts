/**
 * Derived from @zag-js/field of Zag.js (https://github.com/chakra-ui/zag, packages/machines/field,
 * commit 0ce8e3de63b571d37e438bcf28e64759466e56e9), MIT License, Copyright (c) 2021 Chakra UI.
 * Adapted for Fluid Primitives, the git history shows what changed from the original.
 */

import type {
    ValidateResult,
    ValidationMode,
    ValidityMatch,
    ValiditySnapshot,
} from './field.types';

const VALIDITY_KEYS = [
    'badInput',
    'customError',
    'patternMismatch',
    'rangeOverflow',
    'rangeUnderflow',
    'stepMismatch',
    'tooLong',
    'tooShort',
    'typeMismatch',
    'valueMissing',
] as const satisfies readonly ValidityMatch[];

export const VALID_SNAPSHOT: ValiditySnapshot = Object.freeze({
    badInput: false,
    customError: false,
    patternMismatch: false,
    rangeOverflow: false,
    rangeUnderflow: false,
    stepMismatch: false,
    tooLong: false,
    tooShort: false,
    typeMismatch: false,
    valueMissing: false,
    valid: true,
});

/** Copy the live `ValidityState` — its getters are recomputed on every read. */
export function getValiditySnapshot(el: { validity: ValidityState }): ValiditySnapshot {
    const snapshot = {} as ValiditySnapshot;
    for (const key of VALIDITY_KEYS) snapshot[key] = el.validity[key];
    snapshot.valid = el.validity.valid;
    return snapshot;
}

/**
 * A pristine empty required field is not an error yet. When `valueMissing` is the
 * only failure, report the field as valid until the user interacts or a submit forces it.
 */
export function suppressValueMissing(validity: ValiditySnapshot): ValiditySnapshot {
    if (!validity.valueMissing) return validity;
    const onlyValueMissing = VALIDITY_KEYS.every(key => key === 'valueMissing' || !validity[key]);
    if (!onlyValueMissing) return validity;
    return { ...validity, valueMissing: false, valid: true };
}

/**
 * The native flags with `valueMissing` replaced. The field's own `required` and its value decide it,
 * not the control's `required` attribute: a Combobox's visible input holds label text, a group has no
 * control at all, and a placeholder option is always selected.
 */
export function withValueMissing(
    validity: ValiditySnapshot,
    valueMissing: boolean
): ValiditySnapshot {
    const next = { ...validity, valueMissing };
    next.valid = VALIDITY_KEYS.every(key => !next[key]);
    return next;
}

export function toErrorArray(result: ValidateResult): string[] {
    if (result == null) return [];
    const errors = Array.isArray(result) ? result : [result];
    return errors.filter(error => typeof error === 'string' && error.length > 0);
}

export interface ResolveErrorsOptions {
    customErrors: string[];
    validity: ValiditySnapshot;
    nativeMessage: string;
}

export interface ResolvedValidation {
    errors: string[];
    validity: ValiditySnapshot;
}

/**
 * Priority: custom `validate` errors outrank native constraint errors.
 * (The controlled `invalid` prop outranks both, at the computed level.)
 */
export function resolveValidation(options: ResolveErrorsOptions): ResolvedValidation {
    const { customErrors, validity, nativeMessage } = options;
    if (customErrors.length > 0) {
        return {
            errors: customErrors,
            validity: { ...validity, customError: true, valid: false },
        };
    }
    if (!validity.valid) {
        return { errors: nativeMessage ? [nativeMessage] : [], validity };
    }
    return { errors: [], validity };
}

export interface DescribedByOptions {
    helperTextId: string;
    hasHelperText: boolean;
    errorTextIds: string[];
}

/**
 * Description first, then visible error ids in DOM order.
 * Hidden error texts must not be referenced — screen readers still announce them.
 */
export function composeDescribedBy(options: DescribedByOptions): string | undefined {
    const ids: string[] = [];
    if (options.hasHelperText) ids.push(options.helperTextId);
    ids.push(...options.errorTextIds);
    return ids.length > 0 ? ids.join(' ') : undefined;
}

export interface ErrorMatchOptions {
    validity: ValiditySnapshot | null;
    invalid: boolean;
    disabled: boolean;
}

export interface ShouldCommitOptions {
    mode: ValidationMode;
    submitAttempted: boolean;
    eventType: string;
    /** The value was changed at least once; sticky, reverting it does not undo it. */
    edited: boolean;
    /** The field currently shows committed errors. */
    showingErrors: boolean;
}

/**
 * Whether this event should commit validation (make it visible).
 * `VALIDATE` / `SUBMIT.INVALID` always commit and do not go through this.
 *
 * Two rules go beyond the modes: an error that is showing revalidates on every change in every
 * mode, so it clears the moment it is fixed; and a blur only commits once the value was edited or a
 * submit was attempted, so tabbing through a pristine field, or a popup taking focus from its
 * trigger, shows nothing.
 */
export function shouldCommit(options: ShouldCommitOptions): boolean {
    const { mode, submitAttempted, eventType, edited, showingErrors } = options;
    if (eventType === 'CONTROL.BLUR') return mode === 'onBlur' && (edited || submitAttempted);
    if (eventType === 'CONTROL.CHANGE')
        return mode === 'onChange' || (mode === 'onSubmit' && submitAttempted) || showingErrors;
    return false;
}

/** Whether the error text should show for the given `match`. */
export function isErrorMatch(
    match: ValidityMatch | boolean | undefined,
    options: ErrorMatchOptions
): boolean {
    const { validity, invalid, disabled } = options;
    if (match === true) return true;
    if (match === false || disabled) return false;
    if (typeof match === 'string') return validity?.[match] === true;
    return invalid;
}
