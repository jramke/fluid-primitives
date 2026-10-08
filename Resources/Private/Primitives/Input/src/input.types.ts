import type { EventObject } from '@zag-js/core';
import type { JSX, PropTypes } from '@zag-js/types';

export interface InputTranslations {
    /**
     * The text of the word count part. `%count%` is replaced with the number of characters, `%max%`
     * with `maxLength`, e.g. `%count% / %max% characters`. Set it to `false` to omit the text and
     * skip the announcements.
     */
    wordCount?: string | false;
}

export interface InputProps {
    /** The unique identifier of the input. */
    id: string;
    /** The ids of the parts other elements reference: `input` (the `for` of its label) and `wordCount`. */
    ids?: Record<string, string>;
    /** The name of the input, as it is submitted with the form. Taken from the closest field. */
    name?: string;
    /** Whether the input is disabled. Taken from the closest field. */
    disabled?: boolean;
    /** Whether the input is read-only. Taken from the closest field. */
    readOnly?: boolean;
    /** Whether the input is required. Taken from the closest field. */
    required?: boolean;
    /** Whether the input is invalid. Taken from the closest field. */
    invalid?: boolean;
    /** The initial value of the input. */
    defaultValue?: string;
    /** The most characters allowed. It also drives the word count, e.g. `42 / 250 characters`. */
    maxLength?: number;
    /** A regular expression the value has to match. */
    pattern?: string;
    /** The kind of virtual keyboard to show on devices that have one. */
    inputMode?: JSX.HTMLAttributes<HTMLInputElement>['inputMode'];
    /** The localized texts of the input. */
    translations?: InputTranslations;
    /**
     * Whether the word count is announced to assistive tech. Set to `false` to stay silent.
     * @default true
     */
    announce?: boolean;
    /**
     * Milliseconds to debounce word count announcements by, so rapid typing doesn't
     * spam assistive tech on every keystroke. Set to 0 to announce every change immediately.
     * @default 600
     */
    announceDebounce?: number;
    /**
     * Runs on every native `input` event, before the value is committed. Return the value to write
     * back to the input, e.g. uppercased or digits only. The cursor position is kept. It cannot be
     * set from a Fluid template, only by constructing `Input` in your own entry file.
     */
    transform?: (value: string, event: Event) => string;
    /** Called when the value changes. */
    onValueChange?: (details: { value: string }) => void;
}

export interface InputSchema {
    props: InputProps;
    context: {
        value: string;
    };
    refs: {
        announce: ((text: string) => void) | null;
    };
    computed: {
        count: number;
        /** e.g. "42 / 250 characters", or `null` when `maxLength` or the translation is unset. */
        countText: string | null;
    };
    state: 'idle';
    event: EventObject;
    action: string;
    effect: string;
}

export interface InputHandle {
    /** The current value of the input. */
    value: string;
    /** The number of characters in the value. */
    count: number;
    /** The word count text, e.g. `42 / 250 characters`. `null` without `maxLength` or when the text is turned off. */
    countText: string | null;
    /** Whether the input is disabled. */
    disabled: boolean;
    /** Whether the input is read-only. */
    readOnly: boolean;
    /** Whether the input is required. */
    required: boolean;
    /** Whether the input is invalid. */
    invalid: boolean;
    /** The name of the input. */
    name: string | undefined;
    /** The most characters allowed. */
    maxLength: number | undefined;
}

export interface InputApi extends InputHandle {
    getRootProps(): PropTypes['element'];
    getInputProps(): PropTypes['input'];
    getLabelProps(): PropTypes['label'];
    getWordCountProps(): PropTypes['element'];
}
