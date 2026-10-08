import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, test } from 'vitest';
import {
    buildTranslations,
    getPlaceholder,
    type TranslationTexts,
} from '../../../Resources/Private/Primitives/DatePicker/src/date-picker.translations';

// The texts the way PHP ships them: straight from the language file the keys come from.
const languageFile = readFileSync(
    join(__dirname, '../../../Resources/Private/Language/locallang.xlf'),
    'utf8'
);
const texts: TranslationTexts = Object.fromEntries(
    [...languageFile.matchAll(/id="datePicker\.(\w+)"[^>]*>\s*<source>([^<]*)<\/source>/g)].map(
        ([, key, text]) => [key, text]
    )
);

const dayCell = (state: Record<string, boolean>, translations = buildTranslations(texts)) =>
    translations.dayCell!({ valueText: 'Friday, October 9, 2026', ...state } as never);

describe('date picker translations', () => {
    test('builds every label of Zag from the texts of the language file', () => {
        const t = buildTranslations(texts);

        expect(t.trigger!(false)).toBe('Open calendar');
        expect(t.trigger!(true)).toBe('Close calendar');
        expect(t.prevTrigger!('day')).toBe('Switch to previous month');
        expect(t.prevTrigger!('year')).toBe('Switch to previous decade');
        expect(t.nextTrigger!('month')).toBe('Switch to next year');
        // The view it switches to, else the view it is in.
        expect(t.viewTrigger!('day', 'month')).toBe('Switch to month view');
        expect(t.viewTrigger!('month', 'year')).toBe('Switch to year view');
        expect(t.viewTrigger!('year', undefined)).toBe('Year view');
        expect(t.weekNumberCell!(41)).toBe('Week 41');
        expect(t.roleDescription).toEqual({
            content: 'datepicker',
            table: { day: 'calendar month', month: 'calendar year', year: 'calendar decade' },
        });
    });

    test('picks the text of a day cell by its state, in the order Zag does', () => {
        const everything = {
            unavailable: true,
            firstInRange: true,
            lastInRange: true,
            inRange: true,
            selected: true,
        };

        expect(dayCell(everything)).toBe('Not available. Friday, October 9, 2026');
        expect(dayCell({ ...everything, unavailable: false })).toBe(
            'Starting range from Friday, October 9, 2026'
        );
        expect(dayCell({ ...everything, unavailable: false, firstInRange: false })).toBe(
            'Range ending at Friday, October 9, 2026'
        );
        expect(dayCell({ inRange: true, selected: true })).toBe(
            'In range. Friday, October 9, 2026'
        );
        expect(dayCell({ selected: true })).toBe('Selected date. Friday, October 9, 2026');
        expect(dayCell({})).toBe('Choose Friday, October 9, 2026');
    });

    test('writes the dates of a preset in the language of the locale', () => {
        const preset = ['Mon Oct 05 2026', 'Sun Oct 11 2026'];

        expect(buildTranslations(texts, 'en-US').presetTrigger!(preset)).toBe(
            'Select October 5, 2026 to October 11, 2026'
        );
        expect(
            buildTranslations({ presetTrigger: '%start% bis %end%' }, 'de-DE').presetTrigger!(
                preset
            )
        ).toBe('5. Oktober 2026 bis 11. Oktober 2026');
    });

    test('leaves a label out that is set to false', () => {
        const t = buildTranslations({ ...texts, clearTrigger: false, prevTriggerDay: false });

        expect(t.clearTrigger).toBeUndefined();
        expect(t.prevTrigger!('day')).toBeUndefined();
        expect(t.prevTrigger!('month')).toBe('Switch to previous year');
    });

    test('writes the date pattern of the locale with the letters of the translation', () => {
        expect(getPlaceholder(buildTranslations(texts, 'en-US'), 'en-US')).toBe('mm/dd/yyyy');
        expect(
            getPlaceholder(
                buildTranslations(
                    { placeholderDay: 'TT', placeholderMonth: 'MM', placeholderYear: 'JJJJ' },
                    'de-DE'
                ),
                'de-DE'
            )
        ).toBe('TT.MM.JJJJ');
        // Translations of an entry file that bring no placeholder leave it to Zag.
        expect(getPlaceholder({}, 'de-DE')).toBeUndefined();
    });
});
