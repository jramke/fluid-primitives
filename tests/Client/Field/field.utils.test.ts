import { describe, expect, test } from 'vitest';
import type { ValiditySnapshot } from '../../../Resources/Private/Primitives/Field/src/field.types';
import {
    composeDescribedBy,
    getValiditySnapshot,
    isErrorMatch,
    resolveValidation,
    shouldCommit,
    suppressValueMissing,
    toErrorArray,
    VALID_SNAPSHOT,
    withValueMissing,
} from '../../../Resources/Private/Primitives/Field/src/field.utils';

const snapshot = (overrides: Partial<ValiditySnapshot> = {}): ValiditySnapshot => ({
    ...VALID_SNAPSHOT,
    ...overrides,
});

describe('getValiditySnapshot', () => {
    test('copies the live ValidityState into a plain object', () => {
        const input = document.createElement('input');
        input.required = true;

        const result = getValiditySnapshot(input);
        expect(result.valueMissing).toBe(true);
        expect(result.valid).toBe(false);

        // the snapshot must not track later changes
        input.value = 'hello';
        expect(result.valueMissing).toBe(true);
        expect(input.validity.valueMissing).toBe(false);
    });

    test('reflects custom errors', () => {
        const input = document.createElement('input');
        input.setCustomValidity('nope');
        const result = getValiditySnapshot(input);
        expect(result.customError).toBe(true);
        expect(result.valid).toBe(false);
    });
});

describe('suppressValueMissing', () => {
    test('clears valueMissing when it is the only failure', () => {
        const result = suppressValueMissing(snapshot({ valueMissing: true, valid: false }));
        expect(result.valueMissing).toBe(false);
        expect(result.valid).toBe(true);
    });

    test('keeps valueMissing when another constraint also fails', () => {
        const result = suppressValueMissing(
            snapshot({ valueMissing: true, tooShort: true, valid: false })
        );
        expect(result.valueMissing).toBe(true);
        expect(result.valid).toBe(false);
    });

    test('passes an already-valid snapshot through', () => {
        const valid = snapshot();
        expect(suppressValueMissing(valid)).toBe(valid);
    });
});

describe('toErrorArray', () => {
    test('normalizes strings, arrays, and empty results', () => {
        expect(toErrorArray('error')).toEqual(['error']);
        expect(toErrorArray(['a', 'b'])).toEqual(['a', 'b']);
        expect(toErrorArray(null)).toEqual([]);
        expect(toErrorArray(undefined)).toEqual([]);
        expect(toErrorArray(['a', '', 'b'])).toEqual(['a', 'b']);
    });
});

describe('resolveValidation (priority chain)', () => {
    test('custom errors outrank native validity', () => {
        const result = resolveValidation({
            customErrors: ['custom error'],
            validity: snapshot({ tooShort: true, valid: false }),
            nativeMessage: 'native message',
        });
        expect(result.errors).toEqual(['custom error']);
        expect(result.validity.customError).toBe(true);
        expect(result.validity.valid).toBe(false);
        // the native failure stays visible in the snapshot for `match`
        expect(result.validity.tooShort).toBe(true);
    });

    test('native validity applies when no custom errors', () => {
        const result = resolveValidation({
            customErrors: [],
            validity: snapshot({ valueMissing: true, valid: false }),
            nativeMessage: 'Please fill out this field.',
        });
        expect(result.errors).toEqual(['Please fill out this field.']);
        expect(result.validity.valid).toBe(false);
    });

    test('valid when neither fails', () => {
        const result = resolveValidation({
            customErrors: [],
            validity: snapshot(),
            nativeMessage: '',
        });
        expect(result.errors).toEqual([]);
        expect(result.validity.valid).toBe(true);
    });
});

describe('isErrorMatch', () => {
    const base = {
        validity: snapshot({ valueMissing: true, valid: false }),
        invalid: true,
        disabled: false,
    };

    test('no match shows whenever the field is invalid', () => {
        expect(isErrorMatch(undefined, base)).toBe(true);
        expect(isErrorMatch(undefined, { ...base, invalid: false })).toBe(false);
    });

    test('validity key matches only that native failure', () => {
        expect(isErrorMatch('valueMissing', base)).toBe(true);
        expect(isErrorMatch('tooShort', base)).toBe(false);
        expect(isErrorMatch('valueMissing', { ...base, validity: null })).toBe(false);
    });

    test('boolean forces or suppresses', () => {
        expect(isErrorMatch(true, { ...base, invalid: false })).toBe(true);
        expect(isErrorMatch(false, base)).toBe(false);
    });

    test('disabled hides the error unless forced', () => {
        expect(isErrorMatch(undefined, { ...base, disabled: true })).toBe(false);
        expect(isErrorMatch('valueMissing', { ...base, disabled: true })).toBe(false);
        expect(isErrorMatch(true, { ...base, disabled: true })).toBe(true);
    });
});

describe('shouldCommit', () => {
    const base = { submitAttempted: false, edited: false, showingErrors: false };

    test('onChange commits on every change, not on blur', () => {
        expect(shouldCommit({ ...base, mode: 'onChange', eventType: 'CONTROL.CHANGE' })).toBe(true);
        expect(
            shouldCommit({ ...base, mode: 'onChange', edited: true, eventType: 'CONTROL.BLUR' })
        ).toBe(false);
    });

    test('onBlur commits on blur once the value was edited or a submit was attempted', () => {
        expect(shouldCommit({ ...base, mode: 'onBlur', eventType: 'CONTROL.BLUR' })).toBe(false);
        expect(
            shouldCommit({ ...base, mode: 'onBlur', edited: true, eventType: 'CONTROL.BLUR' })
        ).toBe(true);
        expect(
            shouldCommit({
                ...base,
                mode: 'onBlur',
                submitAttempted: true,
                eventType: 'CONTROL.BLUR',
            })
        ).toBe(true);
    });

    test('onBlur does not commit on change until an error is showing', () => {
        expect(
            shouldCommit({ ...base, mode: 'onBlur', edited: true, eventType: 'CONTROL.CHANGE' })
        ).toBe(false);
        expect(
            shouldCommit({
                ...base,
                mode: 'onBlur',
                edited: true,
                showingErrors: true,
                eventType: 'CONTROL.CHANGE',
            })
        ).toBe(true);
    });

    test('onSubmit commits on change only after a submit attempt or while an error shows', () => {
        expect(shouldCommit({ ...base, mode: 'onSubmit', eventType: 'CONTROL.CHANGE' })).toBe(
            false
        );
        expect(
            shouldCommit({
                ...base,
                mode: 'onSubmit',
                submitAttempted: true,
                eventType: 'CONTROL.CHANGE',
            })
        ).toBe(true);
        expect(
            shouldCommit({
                ...base,
                mode: 'onSubmit',
                showingErrors: true,
                eventType: 'CONTROL.CHANGE',
            })
        ).toBe(true);
        expect(
            shouldCommit({
                ...base,
                mode: 'onSubmit',
                submitAttempted: true,
                edited: true,
                eventType: 'CONTROL.BLUR',
            })
        ).toBe(false);
    });
});

describe('composeDescribedBy', () => {
    test('description first, visible errors in DOM order', () => {
        expect(
            composeDescribedBy({
                helperTextId: 'helper',
                hasHelperText: true,
                errorTextIds: ['err-a', 'err-b'],
            })
        ).toBe('helper err-a err-b');
    });

    test('hidden or unrendered errors are excluded', () => {
        expect(
            composeDescribedBy({ helperTextId: 'helper', hasHelperText: true, errorTextIds: [] })
        ).toBe('helper');
        expect(
            composeDescribedBy({
                helperTextId: 'helper',
                hasHelperText: false,
                errorTextIds: ['err'],
            })
        ).toBe('err');
        expect(
            composeDescribedBy({ helperTextId: 'helper', hasHelperText: false, errorTextIds: [] })
        ).toBeUndefined();
    });
});

describe('withValueMissing', () => {
    test('replaces the native valueMissing and recomputes valid', () => {
        const required = withValueMissing(snapshot(), true);
        expect(required.valueMissing).toBe(true);
        expect(required.valid).toBe(false);

        // the control's own `required` no longer decides: a filled field is valid again
        const filled = withValueMissing(snapshot({ valueMissing: true, valid: false }), false);
        expect(filled.valueMissing).toBe(false);
        expect(filled.valid).toBe(true);
    });

    test('keeps the other native failures', () => {
        const result = withValueMissing(snapshot({ patternMismatch: true, valid: false }), false);
        expect(result.patternMismatch).toBe(true);
        expect(result.valid).toBe(false);
    });
});
