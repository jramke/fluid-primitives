import type * as clipboard from '@zag-js/clipboard';

/**
 * The translations as a template or a client script writes them: a text for each state, or
 * Zag's own `triggerLabel` function of the state, which wins.
 */
export type ClipboardTranslationTexts = clipboard.IntlTranslations & {
    triggerLabelIdle?: string;
    triggerLabelCopied?: string;
};

/**
 * Zag's `triggerLabel` is one function of the copied state, which Fluid can't author:
 * `ClipboardContext::getTranslations()` sends a plain string per state instead, which become that
 * function here.
 */
export function resolveTranslations({
    triggerLabel,
    triggerLabelIdle,
    triggerLabelCopied,
}: ClipboardTranslationTexts = {}): clipboard.IntlTranslations | undefined {
    if (triggerLabel) return { triggerLabel };
    if (triggerLabelIdle === undefined && triggerLabelCopied === undefined) return undefined;

    return {
        triggerLabel: copied => (copied ? triggerLabelCopied : triggerLabelIdle) ?? '',
    };
}
