import { describe, expect, test } from 'vitest';
import { resolveTranslations } from '../../../Resources/Private/Primitives/Clipboard/src/clipboard.translations';

describe('clipboard translations', () => {
    test('picks the text of the state, and prefers a triggerLabel function of a client script', () => {
        const fromTexts = resolveTranslations({
            triggerLabelIdle: 'Copy',
            triggerLabelCopied: 'Copied',
        });
        const triggerLabel = (copied: boolean) => (copied ? 'yes' : 'no');

        expect(fromTexts?.triggerLabel?.(false)).toBe('Copy');
        expect(fromTexts?.triggerLabel?.(true)).toBe('Copied');
        expect(resolveTranslations({ triggerLabel, triggerLabelIdle: 'Copy' })?.triggerLabel).toBe(
            triggerLabel
        );
    });

    test('leaves the default of Zag without any text', () => {
        expect(resolveTranslations(undefined)).toBeUndefined();
        expect(resolveTranslations({})).toBeUndefined();
    });
});
