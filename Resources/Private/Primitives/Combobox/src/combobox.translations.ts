import type * as combobox from '@zag-js/combobox';
import { resolveTranslations as resolve, type TranslationText } from '../../../Client';

/**
 * The translations as a template or a client script writes them: `resultsCount` with a `%count%`
 * placeholder or as a function of the count, `resultsCountOne` the same for exactly one result, the
 * other labels as plain strings.
 */
export type ComboboxTranslationTexts = {
    triggerLabel?: string;
    clearTriggerLabel?: string;
    resultsCount?: TranslationText<number>;
    resultsCountOne?: string;
    noResults?: string;
    loading?: string;
};

/**
 * Zag's `resultsCount` is a function of the number of results, which Fluid can't author: a text
 * with a `%count%` placeholder becomes that function. `resultsCountOne` is the text for a single
 * result, so one language file entry per form is enough; without it, `resultsCount` covers every
 * count, as a function from a client script does.
 */
export function resolveTranslations(
    translations: ComboboxTranslationTexts | undefined
): combobox.Props['translations'] {
    if (!translations) return undefined;

    const { resultsCountOne, ...rest } = translations;
    const resolved = resolve<combobox.Props['translations']>(rest, {
        resultsCount: (count: number) => ({ count }),
    });
    const many = resolved?.resultsCount;
    // an empty `resultsCount` turns off the count, for one result too
    if (typeof resultsCountOne !== 'string' || !resolved || !many || rest.resultsCount === '') {
        return resolved;
    }

    return { ...resolved, resultsCount: count => (count === 1 ? resultsCountOne : many(count)) };
}
