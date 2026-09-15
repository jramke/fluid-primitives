import { createMachine } from '@zag-js/core';
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
    // Reads the DOM's actual current value on startup rather than trusting `defaultValue` blindly -
    // a browser can restore a different value than what was server-rendered (autofill, bfcache).
    entry: ['syncValueFromDom'],
    states: {
        idle: {},
    },
    on: {
        VALUE_CHANGE: { actions: ['setValue'] },
    },
    implementations: {
        actions: {
            setValue({ context, event, prop }) {
                const value = event.value as string;
                context.set('value', value);
                prop('onValueChange')?.({ value });
            },
            syncValueFromDom({ context, scope }) {
                const inputEl = dom.getInputEl(scope);
                if (inputEl) context.set('value', inputEl.value);
            },
        },
    },
});
