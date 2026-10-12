import type { NormalizeProps, PropTypes } from '@zag-js/types';
import type { Machine } from '../../../Client';
import { parts } from './button.anatomy';
import * as dom from './button.dom';
import type { ButtonApi, ButtonSchema } from './button.types';

export function connect<T extends PropTypes>(
    machine: Machine<ButtonSchema>,
    normalize: NormalizeProps<T>
): ButtonApi {
    const { scope, prop, computed } = machine;

    const state = computed('state');
    const disabled = computed('disabled');
    const loading = state === 'loading';
    const focusableWhenDisabled = !!prop('focusableWhenDisabled');

    return {
        state,
        disabled,
        loading,

        setDisabled(value: boolean) {
            machine.updateProps({ disabled: value });
        },

        setLoading(value: boolean) {
            machine.updateProps({ loading: value });
        },

        addEventListener(...args: Parameters<HTMLButtonElement['addEventListener']>) {
            dom.getRootEl(scope)?.addEventListener(...args);
        },

        getRootProps() {
            return normalize.button({
                ...parts.root.attrs(scope.id),
                disabled: disabled && !focusableWhenDisabled ? true : undefined,
                'aria-disabled': disabled && focusableWhenDisabled ? 'true' : undefined,
                'data-disabled': disabled ? '' : undefined,
                /** Present while the button is loading. */
                'data-loading': loading ? '' : undefined,
            });
        },

        getIndicatorProps({ state: indicatorState }) {
            return normalize.element({
                ...parts.indicator.attrs(scope.id),
                hidden: indicatorState === state ? undefined : true,
            });
        },
    };
}
