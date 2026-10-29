import { describe, expect, test } from 'vitest';
import { resolveTranslations } from '../../../Resources/Private/Primitives/Select/src/select.translations';

const item = (selected: boolean) => ({ value: 'de', label: 'Germany', selected });

describe('select translations', () => {
    test('announces an item with the placeholders of the text of its state', () => {
        const t = resolveTranslations({
            clearTriggerLabel: 'Clear',
            itemAnnouncement: '%label% (%value%)',
            itemAnnouncementSelected: '%label% chosen',
        });

        expect(t?.clearTriggerLabel).toBe('Clear');
        expect(t?.itemAnnouncement?.(item(false))).toBe('Germany (de)');
        expect(t?.itemAnnouncement?.(item(true))).toBe('Germany chosen');
    });

    test('lets itemAnnouncement cover both states without a selected text, and a function too', () => {
        const text = resolveTranslations({ itemAnnouncement: '%label%!' });
        const own = (details: { label: string }) => `own ${details.label}`;
        const fn = resolveTranslations({ itemAnnouncement: own });

        expect(text?.itemAnnouncement?.(item(true))).toBe('Germany!');
        expect(fn?.itemAnnouncement).toBe(own);
    });

    test('announces the label of an unselected item when only the selected text exists', () => {
        const t = resolveTranslations({ itemAnnouncementSelected: '%label% chosen' });

        expect(t?.itemAnnouncement?.(item(false))).toBe('Germany');
        expect(t?.itemAnnouncement?.(item(true))).toBe('Germany chosen');
    });
});
