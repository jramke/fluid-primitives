import type * as numberInput from '@zag-js/number-input';
import { resolveTranslations as resolve, type TranslationText } from '../../../Client';

/**
 * The translations as a template or a client script writes them: `valueText` with a `%value%`
 * placeholder or as a function of the value, the labels as plain strings.
 */
export type NumberInputTranslationTexts = {
    valueText?: TranslationText<string>;
    incrementLabel?: string;
    decrementLabel?: string;
};

/**
 * Zag's `valueText` is a function of the value (`aria-valuetext` of the input), which Fluid can't
 * author: a text with a `%value%` placeholder becomes that function. A function from a client
 * script is kept.
 */
export function resolveTranslations(
    translations: NumberInputTranslationTexts | undefined
): numberInput.Props['translations'] {
    return resolve<numberInput.Props['translations']>(translations, {
        valueText: value => ({ value }),
    });
}
