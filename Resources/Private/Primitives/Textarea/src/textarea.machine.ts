import { createMachine } from '@zag-js/core';
import { createLiveRegion } from '@zag-js/live-region';
import { debounce } from '@zag-js/utils';
import * as dom from './textarea.dom';
import type { TextareaSchema } from './textarea.types';

export const machine = createMachine<TextareaSchema>({
    initialState() {
        return 'idle';
    },
    context({ bindable, prop }) {
        return {
            value: bindable<string>(() => ({ defaultValue: prop('defaultValue') ?? '' })),
        };
    },
    refs() {
        return {
            liveRegion: null,
            announce: null,
        };
    },
    computed: {
        count({ context }) {
            return context.get('value').length;
        },
        countText({ prop, computed }) {
            const wordCountTemplate = prop('translations')?.wordCount;
            const maxLength = prop('maxLength');
            if (!wordCountTemplate || maxLength == null) return null;

            const count = computed('count');
            return wordCountTemplate
                .replaceAll('%count%', String(count))
                .replaceAll('%max%', String(maxLength));
        },
    },
    // Reads the DOM's actual current value on startup rather than trusting `defaultValue` blindly -
    // a browser can restore a different value than what was server-rendered (autofill, bfcache).
    entry: ['syncValueFromDom'],
    // Runs for the machine's whole lifetime (not tied to a particular state) - sets up the
    // liveRegion ref only if the consumer actually placed a `liveRegion` part in their template,
    // and tears it down when the machine stops.
    effects: ['manageLiveRegion'],
    states: {
        idle: {},
    },
    on: {
        VALUE_CHANGE: { actions: ['setValue'] },
    },
    implementations: {
        actions: {
            setValue({ context, event, prop, refs, computed }) {
                const value = event.value as string;
                context.set('value', value);
                prop('onValueChange')?.({ value });

                const countText = computed('countText');
                if (countText) refs.get('announce')?.(countText);
            },
            syncValueFromDom({ context, scope }) {
                const textareaEl = dom.getTextareaEl(scope);
                if (textareaEl) context.set('value', textareaEl.value);
            },
        },
        effects: {
            manageLiveRegion({ scope, refs, prop }) {
                const liveRegionEl = dom.getLiveRegionEl(scope);
                if (!liveRegionEl) return undefined;

                const liveRegion = createLiveRegion({ level: 'polite', root: liveRegionEl });
                const debounceMs = prop('announceDebounce') ?? 600;
                const announce =
                    debounceMs > 0
                        ? debounce((text: string) => liveRegion.announce(text), debounceMs)
                        : (text: string) => liveRegion.announce(text);

                refs.set('liveRegion', liveRegion);
                refs.set('announce', announce);

                return () => {
                    liveRegion.destroy();
                };
            },
        },
    },
});
