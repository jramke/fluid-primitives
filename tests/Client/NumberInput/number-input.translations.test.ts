import { describe, expect, test } from 'vitest';
import { resolveTranslations } from '../../../Resources/Private/Primitives/NumberInput/src/number-input.translations';

describe('number input translations', () => {
    test('turns a valueText with a %value% placeholder into the function Zag calls and keeps the labels', () => {
        const t = resolveTranslations({
            valueText: '%value% pieces',
            incrementLabel: 'More',
        });

        expect(t?.valueText?.('4')).toBe('4 pieces');
        expect(t?.incrementLabel).toBe('More');
    });

    test('keeps a valueText function from a client script', () => {
        const valueText = (value: string) => `~${value}`;

        expect(resolveTranslations({ valueText })?.valueText).toBe(valueText);
    });
});
