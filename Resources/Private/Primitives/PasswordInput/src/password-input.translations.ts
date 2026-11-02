import type * as passwordInput from '@zag-js/password-input';

/**
 * The translations as a template or a client script writes them: a label for each state, or
 * Zag's own `visibilityTrigger` function of the visible state, which wins.
 */
export type PasswordInputTranslationTexts = passwordInput.IntlTranslations & {
    visibilityTriggerLabelHidden?: string;
    visibilityTriggerLabelVisible?: string;
};

/**
 * Zag's `visibilityTrigger` is one function of the visible state, which Fluid can't author:
 * `PasswordInputContext::getTranslations()` sends a label per state instead, which become that
 * function here.
 */
export function resolveTranslations({
    visibilityTrigger,
    visibilityTriggerLabelHidden,
    visibilityTriggerLabelVisible,
}: PasswordInputTranslationTexts = {}): passwordInput.IntlTranslations | undefined {
    if (visibilityTrigger) return { visibilityTrigger };
    if (visibilityTriggerLabelHidden === undefined && visibilityTriggerLabelVisible === undefined)
        return undefined;

    return {
        visibilityTrigger: visible =>
            (visible ? visibilityTriggerLabelVisible : visibilityTriggerLabelHidden) ?? '',
    };
}
