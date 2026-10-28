import type { FieldConstraintViolation } from '../../Field/src/field.types';

/**
 * What happens when the value is longer than `maxLength`:
 * - `block`: the browser stops the typing and cuts a paste off at the limit
 * - `warn`: typing and pasting past the limit is allowed, the count and `data-over-limit` show it
 * - `invalid`: like `warn`, and a surrounding field becomes invalid (`tooLong`) so a form does not submit
 */
export type MaxLengthBehavior = 'block' | 'warn' | 'invalid';

/** The texts of the word count part, shared by the Input and the Textarea. */
export interface CountTranslations {
    wordCount?: string;
    wordCountOver?: string;
}

// `warn` and `invalid` count code points like the server's `mb_strlen`, `block` the UTF-16 units
// the native `maxlength` enforces.
export function countCharacters(value: string, behavior: MaxLengthBehavior = 'block'): number {
    return behavior === 'block' ? value.length : [...value].length;
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

    const text = over > 0 && translations?.wordCountOver ? translations.wordCountOver : template;
    return text
        .replaceAll('%count%', String(count))
        .replaceAll('%max%', String(maxLength))
        .replaceAll('%over%', String(over));
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

    const count = countCharacters(value, behavior);
    const over = getOverLimit(count, maxLength, behavior);
    if (over === 0) return null;

    return {
        match: 'tooLong',
        message: formatCountText(translations, count, maxLength, over) ?? '',
    };
}
