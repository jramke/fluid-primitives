import type * as select from '@zag-js/select';
import {
    fillPlaceholders,
    resolveTranslations as resolve,
    type TranslationText,
} from '../../../Client';

/**
 * The translations as a template or a client script writes them: `itemAnnouncement` with `%label%`
 * and `%value%` placeholders or as a function of the item, `itemAnnouncementSelected` the same for
 * a selected item, the clear label as a plain string.
 */
export type SelectTranslationTexts = {
    clearTriggerLabel?: string;
    itemAnnouncement?: TranslationText<select.ItemAnnouncementDetails>;
    itemAnnouncementSelected?: string;
};

const itemValues = ({ label, value }: select.ItemAnnouncementDetails) => ({ label, value });

/**
 * Zag's `itemAnnouncement` is one function of the item and whether it is selected, which Fluid
 * can't author: a text with `%label%`/`%value%` placeholders becomes that function, and
 * `itemAnnouncementSelected` is the text for a selected item, so one language file entry per state
 * is enough. Without it, `itemAnnouncement` covers both states, as a function from a client script
 * does. An item with neither text is announced by its label.
 */
export function resolveTranslations(
    translations: SelectTranslationTexts | undefined
): select.Props['translations'] {
    if (!translations) return undefined;

    const { itemAnnouncementSelected, ...rest } = translations;
    const resolved = resolve<select.Props['translations']>(rest, {
        itemAnnouncement: itemValues,
    });
    if (typeof itemAnnouncementSelected !== 'string' || !resolved) return resolved;

    const announce = resolved.itemAnnouncement;
    return {
        ...resolved,
        itemAnnouncement: details =>
            details.selected
                ? fillPlaceholders(itemAnnouncementSelected, itemValues(details))
                : (announce?.(details) ?? details.label),
    };
}
