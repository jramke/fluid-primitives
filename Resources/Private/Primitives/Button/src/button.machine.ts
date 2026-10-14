import { createMachine } from '@zag-js/core';
import { devWarn } from '../../../Client';
import * as dom from './button.dom';
import type { ButtonSchema } from './button.types';

export const machine = createMachine<ButtonSchema>({
    initialState() {
        return 'idle';
    },
    computed: {
        disabled({ prop }) {
            return !!prop('disabled') || !!prop('loading');
        },
        state({ prop, computed }) {
            if (prop('loading')) return 'loading';
            return computed('disabled') ? 'disabled' : 'idle';
        },
    },
    watch({ track, computed, prop, scope }) {
        track([() => computed('disabled')], () => {
            if (!computed('disabled') || prop('focusableWhenDisabled')) return;

            const buttonEl = dom.getRootEl(scope);
            if (buttonEl && scope.isActiveElement(buttonEl)) {
                devWarn(
                    '[Button] A focused button was disabled, so it loses focus. Set `focusableWhenDisabled` to keep it.',
                    buttonEl
                );
            }
        });
    },
    effects: ['ignoreInteractionWhenDisabled'],
    states: {
        idle: {},
    },
    implementations: {
        effects: {
            // An `aria-disabled` button still gets the native click that submits a form, and the
            // events other primitives listen to when it is their trigger, so they are stopped here
            // in the capture phase, before any other listener of the button runs.
            ignoreInteractionWhenDisabled({ scope, computed }) {
                const buttonEl = dom.getRootEl(scope);
                if (!buttonEl) return undefined;

                const ignore = (event: Event) => {
                    if (!computed('disabled')) return;
                    event.preventDefault();
                    event.stopImmediatePropagation();
                };
                const ignoreActivationKey = (event: KeyboardEvent) => {
                    if (event.key === 'Enter' || event.key === ' ') ignore(event);
                };

                buttonEl.addEventListener('click', ignore, true);
                buttonEl.addEventListener('pointerdown', ignore, true);
                buttonEl.addEventListener('mousedown', ignore, true);
                buttonEl.addEventListener('keydown', ignoreActivationKey, true);
                buttonEl.addEventListener('keyup', ignoreActivationKey, true);

                return () => {
                    buttonEl.removeEventListener('click', ignore, true);
                    buttonEl.removeEventListener('pointerdown', ignore, true);
                    buttonEl.removeEventListener('mousedown', ignore, true);
                    buttonEl.removeEventListener('keydown', ignoreActivationKey, true);
                    buttonEl.removeEventListener('keyup', ignoreActivationKey, true);
                };
            },
        },
    },
});
