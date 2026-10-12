import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';

/** What the button is doing, and which indicators are shown. `loading` wins over `disabled`. */
export type ButtonState = 'idle' | 'loading' | 'disabled';

export interface ButtonProps {
    /** The unique identifier of the button. */
    id: string;
    /** The ids of the parts other elements reference: `root`. */
    ids?: Record<string, string>;
    /** Whether the button is disabled. */
    disabled?: boolean;
    /** Whether the button is loading. A loading button is disabled too. */
    loading?: boolean;
    /**
     * Keeps a disabled button focusable. It then gets `aria-disabled` instead of the `disabled`
     * attribute, and clicks and key presses are ignored.
     */
    focusableWhenDisabled?: boolean;
}

export interface ButtonSchema {
    props: ButtonProps;
    computed: {
        disabled: boolean;
        state: ButtonState;
    };
    state: 'idle';
    event: EventObject;
    action: string;
    effect: string;
}

export interface ButtonIndicatorProps {
    /** The state of the button the indicator is shown in. */
    state: ButtonState;
}

export interface ButtonApi {
    /** The state of the button, which decides the indicators that are shown. */
    state: ButtonState;
    /** Whether the button is disabled, either on its own or because it is loading. */
    disabled: boolean;
    /** Whether the button is loading. */
    loading: boolean;
    /** Disables or enables the button. A loading button stays disabled. */
    setDisabled(value: boolean): void;
    /** Starts or stops loading. A loading button is disabled too. */
    setLoading(value: boolean): void;
    /** Adds an event listener to the button element, the same as `addEventListener` of the element. */
    addEventListener(...args: Parameters<HTMLButtonElement['addEventListener']>): void;
    getRootProps(): PropTypes['button'];
    getIndicatorProps(props: ButtonIndicatorProps): PropTypes['element'];
}
