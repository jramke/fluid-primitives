import { describe, expect, test } from 'vitest';
import { formatCountText } from '../../../Resources/Private/Primitives/Input/src/input.count';

describe('word count text', () => {
    test('fills the placeholders of a text and picks the over text only while over the limit', () => {
        const translations = {
            wordCount: '%count% / %max%',
            wordCountOver: '%over% too many (%count% / %max%)',
        };

        expect(formatCountText(translations, 5, 10, 0)).toBe('5 / 10');
        expect(formatCountText(translations, 13, 10, 3)).toBe('3 too many (13 / 10)');
    });

    test('builds the text with a function of the count, max and over', () => {
        const translations = {
            wordCount: ({ count, max }: { count: number; max: number }) => `${max - count} left`,
            wordCountOver: ({ over }: { over: number }) => `${over} over`,
        };

        expect(formatCountText(translations, 4, 10, 0)).toBe('6 left');
        expect(formatCountText(translations, 12, 10, 2)).toBe('2 over');
    });
});
