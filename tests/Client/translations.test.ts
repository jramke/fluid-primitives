import { describe, expect, test } from 'vitest';
import { fillPlaceholders, resolveTranslations } from '../../Resources/Private/Client';

describe('translations', () => {
    test('fills a placeholder with its value as written, and leaves one without a value in place', () => {
        expect(fillPlaceholders('%a%-%a%-%b%-%c%', { a: 1, b: 'x$&', c: undefined })).toBe(
            '1-1-x$&-'
        );
        expect(fillPlaceholders('100% of %missing%', {})).toBe('100% of %missing%');
    });

    test('turns the texts of the listed keys into functions, keeps functions, texts and drops the rest', () => {
        const own = (value: string) => `own ${value}`;
        const fromText = resolveTranslations(
            { valueText: '%value% items', label: 'Plain', off: false },
            { valueText: (value: string) => ({ value }) }
        ) as Record<string, unknown>;
        const fromFunction = resolveTranslations(
            { valueText: own },
            { valueText: (value: string) => ({ value }) }
        ) as Record<string, unknown>;

        expect(Object.keys(fromText)).toEqual(['valueText', 'label']);
        expect((fromText.valueText as (value: string) => string)('3')).toBe('3 items');
        expect(fromText.label).toBe('Plain');
        expect(fromFunction.valueText).toBe(own);
        expect(resolveTranslations(undefined)).toBeUndefined();
    });
});
