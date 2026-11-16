import { describe, expect, test } from 'vitest';
import { resolveTranslations } from '../../../Resources/Private/Primitives/Combobox/src/combobox.translations';

describe('combobox translations', () => {
    test('announces the count with the text for one result or for many', () => {
        const t = resolveTranslations({
            triggerLabel: 'Toggle',
            resultsCount: '%count% results',
            resultsCountOne: 'One result',
            noResults: 'None',
            loading: 'Loading',
        });

        expect(t?.triggerLabel).toBe('Toggle');
        expect(t?.noResults).toBe('None');
        expect(t?.loading).toBe('Loading');
        expect(t?.resultsCount?.(1)).toBe('One result');
        expect(t?.resultsCount?.(12)).toBe('12 results');
    });

    test('lets resultsCount cover every count without a text for one, and a function too', () => {
        const text = resolveTranslations({ resultsCount: '%count% found' });
        const own = (count: number) => `own ${count}`;
        const fn = resolveTranslations({ resultsCount: own });

        expect(text?.resultsCount?.(1)).toBe('1 found');
        expect(fn?.resultsCount).toBe(own);
    });

    test('keeps an empty text, which turns that announcement off, and leaves out what is not a text', () => {
        const t = resolveTranslations({ noResults: '', loading: false as unknown as string });

        expect(t?.noResults).toBe('');
        expect(t).not.toHaveProperty('loading');
    });

    test('an empty resultsCount turns off the count for one result too', () => {
        const t = resolveTranslations({ resultsCount: '', resultsCountOne: '1 result' });

        expect(t?.resultsCount?.(1)).toBe('');
        expect(t?.resultsCount?.(5)).toBe('');
    });
});
