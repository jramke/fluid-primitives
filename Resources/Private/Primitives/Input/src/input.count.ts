import { fillPlaceholders, type TranslationText } from '../../../Client';
import type { FieldConstraintViolation } from '../../Field/src/field.types';

/**
 * What happens when the value is longer than `maxLength`:
 * - `block`: the browser stops the typing and cuts a paste off at the limit
 * - `warn`: typing and pasting past the limit is allowed, the count and `data-over-limit` show it
 * - `invalid`: like `warn`, and a surrounding field becomes invalid (`tooLong`) so a form does not submit
 */
export type MaxLengthBehavior = 'block' | 'warn' | 'invalid';

/** What the word count part is built from. */
export interface CountDetails {
    /** The number of characters. */
    count: number;
    /** The `maxLength`. */
    max: number;
    /** The number of characters over `maxLength`, `0` when within it. */
    over: number;
}

/** The texts of the word count part, shared by the Input and the Textarea. */
export interface CountTranslations {
    wordCount?: TranslationText<CountDetails>;
    wordCountOver?: TranslationText<CountDetails>;
}

export function getOverLimit(
    count: number,
    maxLength: number | undefined,
    behavior: MaxLengthBehavior = 'block'
): number {
    if (maxLength == null || behavior === 'block') return 0;
    return Math.max(0, count - maxLength);
}

export function formatCountText(
    translations: CountTranslations | undefined,
    count: number,
    maxLength: number | undefined,
    over: number
): string | null {
    const template = translations?.wordCount;
    if (!template || maxLength == null) return null;

    const entry = over > 0 && translations?.wordCountOver ? translations.wordCountOver : template;
    const details = { count, max: maxLength, over };
    return typeof entry === 'function' ? entry(details) : fillPlaceholders(entry, details);
}

/** The `tooLong` violation a surrounding field reports for `maxLengthBehavior="invalid"`, else `null`. */
export function getLengthViolation(
    value: string,
    options: {
        maxLength: number | undefined;
        behavior: MaxLengthBehavior | undefined;
        translations: CountTranslations | undefined;
    }
): FieldConstraintViolation | null {
    const { maxLength, behavior, translations } = options;
    if (behavior !== 'invalid') return null;

    // UTF-16 units like the native `maxlength`: never less than the server counts
    const count = value.length;
    const over = getOverLimit(count, maxLength, behavior);
    if (over === 0) return null;

    return {
        match: 'tooLong',
        message: formatCountText(translations, count, maxLength, over) ?? '',
    };
}
