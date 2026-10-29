/** What a `%placeholder%` of a translation can be filled with. */
export type PlaceholderValues = Record<string, string | number | null | undefined>;

/**
 * A translation as a template author or a client script writes it: a text with `%placeholders%`
 * for what is only known when the text is shown, or a function that builds the text from the same
 * details.
 */
export type TranslationText<TDetails> = string | ((details: TDetails) => string);

/**
 * Replaces every `%name%` in `text` with the value of `name`. A placeholder without a value is left
 * as written, so a typo stays visible.
 *
 * @param text - The text with its placeholders.
 * @param values - The values by placeholder name.
 * @example
 * fillPlaceholders('Preview of %fileName%', { fileName: 'a.png' }); // 'Preview of a.png'
 */
export function fillPlaceholders(text: string, values: PlaceholderValues): string {
    return text.replace(/%(\w+)%/g, (placeholder, name: string) =>
        name in values ? String(values[name] ?? '') : placeholder
    );
}

/**
 * Turns the texts of a `translations` prop into what a machine takes. Fluid can't author a
 * callback, so a template writes a text with `%placeholders%` for each translation that a machine
 * wants as a function, while a client script passes the function itself. Both end up as the same
 * function here.
 *
 * Every key of `functions` becomes a function: a text is filled with the placeholder values
 * `functions` derives from the details the machine calls it with, a function is kept as it is.
 * Every other key stays a text. Anything else, such as `false`, is left out, so the machine falls
 * back to its own default.
 *
 * @param translations - The `translations` prop as it arrives, or `undefined`.
 * @param functions - The keys that are functions in the machine, each with what fills its placeholders.
 * @returns The translations for the machine, or `undefined` without any.
 * @example
 * resolveTranslations(
 *     { valueText: '%value% items', incrementLabel: 'More' },
 *     { valueText: (value: string) => ({ value }) }
 * );
 * // { valueText: value => `${value} items`, incrementLabel: 'More' }
 */
export function resolveTranslations<T = Record<string, unknown>>(
    translations: Record<string, unknown> | undefined,
    functions: Record<string, (details: any) => PlaceholderValues> = {}
): T | undefined {
    if (!translations) return undefined;

    const resolved: Record<string, unknown> = {};
    for (const [key, entry] of Object.entries(translations)) {
        const fill = functions[key];
        if (!fill) {
            if (typeof entry === 'string') resolved[key] = entry;
        } else if (typeof entry === 'function') {
            resolved[key] = entry;
        } else if (typeof entry === 'string') {
            resolved[key] = (details: unknown) => fillPlaceholders(entry, fill(details));
        }
    }

    return resolved as T;
}
