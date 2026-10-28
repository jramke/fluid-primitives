import { createMachine } from '@zag-js/core';
import { createLiveRegion } from '@zag-js/live-region';
import { debounce } from '@zag-js/utils';
import { countCharacters, formatCountText, getOverLimit } from './input.count';
import * as dom from './input.dom';
import type { InputSchema } from './input.types';

export const machine = createMachine<InputSchema>({
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
            announce: null,
        };
    },
    computed: {
        count({ context, prop }) {
            return countCharacters(context.get('value'), prop('maxLengthBehavior'));
        },
        over({ prop, computed }) {
            return getOverLimit(computed('count'), prop('maxLength'), prop('maxLengthBehavior'));
        },
        countText({ prop, computed }) {
            return formatCountText(
                prop('translations'),
                computed('count'),
                prop('maxLength'),
                computed('over')
            );
        },
    },
    // Reads the DOM's actual current value on startup rather than trusting `defaultValue` blindly -
    // a browser can restore a different value than what was server-rendered (autofill, bfcache).
    entry: ['syncValueFromDom'],
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
                const inputEl = dom.getInputEl(scope);
                if (inputEl) context.set('value', inputEl.value);
            },
        },
        effects: {
            manageLiveRegion({ refs, prop, computed }) {
                if (prop('announce') === false || computed('countText') === null) return undefined;

                const liveRegion = createLiveRegion({ level: 'polite' });
                const debounceMs = prop('announceDebounce') ?? 600;
                const announce =
                    debounceMs > 0
                        ? debounce((text: string) => liveRegion.announce(text), debounceMs)
                        : (text: string) => liveRegion.announce(text);

                refs.set('announce', announce);

                return () => {
                    liveRegion.destroy();
                };
            },
        },
    },
});
