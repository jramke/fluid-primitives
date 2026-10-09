import type { DateView, IntlTranslations } from '@zag-js/date-picker';

/**
 * The labels as PHP ships them: texts, with `%placeholders%` for what is only known when the
 * label renders. `false` leaves a label out.
 */
export type TranslationTexts = Record<string, string | false | undefined>;

/**
 * Zag's translations, plus the role descriptions it hard-codes in English.
 * TODO: chakra-ui/zag#3421 makes them part of Zag's own translations.
 */
export interface DatePickerTranslations extends IntlTranslations {
    roleDescription?: {
        content?: string;
        table?: Partial<Record<DateView, string>>;
    };
}

const capitalize = (word: string) => word.charAt(0).toUpperCase() + word.slice(1);

const fill = (text: string | false | undefined, values: Record<string, string | number>) =>
    text ? text.replace(/%(\w+)%/g, (_, key: string) => String(values[key] ?? '')) : undefined;

/** `Date.toDateString()`, which is what Zag hands over for a preset, in the language of the locale. */
const formatDay = (dateString: string, locale: string) => {
    const date = new Date(dateString);
    return Number.isNaN(date.getTime())
        ? dateString
        : new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(date);
};

/**
 * Builds the translations Zag takes from the texts of the language file. Most of them are
 * functions of the date or of the view, which is why PHP can't send them as they are.
 */
export function buildTranslations(
    texts: TranslationTexts = {},
    locale = 'en-US'
): DatePickerTranslations {
    // An omitted label (`false`) is `undefined` for Zag, which leaves the attribute out.
    const label = (key: string, values: Record<string, string | number> = {}) =>
        fill(texts[key], values) as string;

    return {
        clearTrigger: label('clearTrigger'),
        monthSelect: label('monthSelect'),
        yearSelect: label('yearSelect'),
        content: label('content'),
        weekColumnHeader: label('weekColumnHeader'),
        trigger: open => label(open ? 'triggerClose' : 'triggerOpen'),
        prevTrigger: view => label(`prevTrigger${capitalize(view)}`),
        nextTrigger: view => label(`nextTrigger${capitalize(view)}`),
        // The next view when there is one to switch to, else the view itself.
        viewTrigger: (view, nextView) =>
            label(
                nextView ? `viewTriggerTo${capitalize(nextView)}` : `viewTrigger${capitalize(view)}`
            ),
        presetTrigger: ([start = '', end = '']) =>
            label('presetTrigger', {
                start: formatDay(start, locale),
                end: formatDay(end, locale),
            }),
        weekNumberCell: number => label('weekNumberCell', { number }),
        dayCell: state =>
            label(
                state.unavailable
                    ? 'dayCellUnavailable'
                    : state.firstInRange
                      ? 'dayCellRangeStart'
                      : state.lastInRange
                        ? 'dayCellRangeEnd'
                        : state.inRange
                          ? 'dayCellInRange'
                          : state.selected
                            ? 'dayCellSelected'
                            : 'dayCell',
                { date: state.valueText }
            ),
        placeholder: () => ({
            day: texts.placeholderDay || 'dd',
            month: texts.placeholderMonth || 'mm',
            year: texts.placeholderYear || 'yyyy',
        }),
        roleDescription: {
            content: texts.contentRoleDescription || undefined,
            table: {
                day: texts.tableRoleDescriptionDay || undefined,
                month: texts.tableRoleDescriptionMonth || undefined,
                year: texts.tableRoleDescriptionYear || undefined,
            },
        },
    };
}

/**
 * The placeholder of the input: the date pattern of the locale written with the letters of the
 * translation, e.g. `TT.MM.JJJJ`. Zag only knows the English ones.
 */
export function getPlaceholder(
    translations: DatePickerTranslations | undefined,
    locale = 'en-US'
): string | undefined {
    const letters: Record<string, string> | undefined = translations?.placeholder?.(locale);
    if (!letters) return undefined;

    return new Intl.DateTimeFormat(locale)
        .formatToParts(new Date())
        .map(part => letters[part.type] ?? part.value)
        .join('');
}
