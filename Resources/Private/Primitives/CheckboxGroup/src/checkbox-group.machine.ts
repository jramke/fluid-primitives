import { createMachine } from '@zag-js/core';
import type { CheckboxGroupSchema } from './checkbox-group.types';

export const machine = createMachine<CheckboxGroupSchema>({
    initialState() {
        return 'ready';
    },
    context({ bindable, prop }) {
        return {
            value: bindable(() => ({
                defaultValue: prop('defaultValue') ?? [],
                value: prop('value'),
                onChange(value) {
                    prop('onValueChange')?.({ value });
                },
            })),
        };
    },
    states: {
        ready: {
            on: {
                'VALUE.SET': {
                    actions: ['setValue'],
                },
                'VALUE.ADD': {
                    actions: ['addValue'],
                },
                'VALUE.REMOVE': {
                    actions: ['removeValue'],
                },
                'VALUE.TOGGLE': {
                    actions: ['toggleValue'],
                },
            },
        },
    },
    computed: {
        isAtMax({ prop, context }) {
            const max = prop('maxSelectedValues');
            if (max == null) return false;
            return context.get('value').length >= max;
        },
        isInteractive({ prop }) {
            return !prop('disabled') && !prop('readOnly');
        },
    },
    implementations: {
        actions: {
            setValue({ context, event }) {
                const newValue = event.value as string[];
                context.set('value', newValue);
            },
            addValue({ context, event, computed }) {
                if (!computed('isInteractive')) return;
                const val = event.value as string;
                const currentValue = context.get('value');
                if (currentValue.includes(val)) return;
                if (computed('isAtMax')) return;
                const newValue = [...currentValue, val];
                context.set('value', newValue);
            },
            removeValue({ context, event, computed }) {
                if (!computed('isInteractive')) return;
                const val = event.value as string;
                const newValue = context.get('value').filter((v: string) => v !== val);
                context.set('value', newValue);
            },
            toggleValue({ context, event, computed }) {
                const val = event.value as string;
                const currentValue = context.get('value');

                if (currentValue.includes(val)) {
                    if (!computed('isInteractive')) return;
                    const newValue = currentValue.filter((v: string) => v !== val);
                    context.set('value', newValue);
                } else {
                    if (!computed('isInteractive')) return;
                    if (computed('isAtMax')) return;
                    const newValue = [...currentValue, val];
                    context.set('value', newValue);
                }
            },
        },
    },
});
