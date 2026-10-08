import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';

export interface TextareaTranslations {
    /**
     * The text of the word count part. `%count%` is replaced with the number of characters, `%max%`
     * with `maxLength`, e.g. `%count% / %max% characters`. Set it to `false` to omit the text and
     * skip the announcements.
     */
    wordCount?: string | false;
}

export type TextareaSubmitOn = 'enter' | 'mod+enter';

export interface TextareaProps {
    /** The unique identifier of the textarea. */
    id: string;
    /** The ids of the parts other elements reference: `textarea` (the `for` of its label) and `wordCount`. */
    ids?: Record<string, string>;
    /** The name of the textarea, as it is submitted with the form. Taken from the closest field. */
    name?: string;
    /** Whether the textarea is disabled. Taken from the closest field. */
    disabled?: boolean;
    /** Whether the textarea is read-only. Taken from the closest field. */
    readOnly?: boolean;
    /** Whether the textarea is required. Taken from the closest field. */
    required?: boolean;
    /** Whether the textarea is invalid. Taken from the closest field. */
    invalid?: boolean;
    /** The initial value of the textarea. */
    defaultValue?: string;
    /** The most characters allowed. It also drives the word count, e.g. `42 / 250 characters`. */
    maxLength?: number;
    /** The number of visible lines. */
    rows?: number;
    /**
     * Submits the closest `<form>` on a keypress instead of inserting a newline. `'enter'` submits on
     * Enter, Shift+Enter still inserts a newline. `'mod+enter'` submits on Cmd or Ctrl+Enter, Enter
     * always inserts a newline. By default Enter is never intercepted.
     */
    submitOn?: TextareaSubmitOn;
    /** The localized texts of the textarea. */
    translations?: TextareaTranslations;
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
     * back to the textarea, e.g. trimmed. The cursor position is kept. It cannot be set from a Fluid
     * template, only by constructing `Textarea` in your own entry file.
     */
    transform?: (value: string, event: Event) => string;
    /** Called when the value changes. */
    onValueChange?: (details: { value: string }) => void;
}

export interface TextareaSchema {
    props: TextareaProps;
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

export interface TextareaHandle {
    /** The current value of the textarea. */
    value: string;
    /** The number of characters in the value. */
    count: number;
    /** The word count text, e.g. `42 / 250 characters`. `null` without `maxLength` or when the text is turned off. */
    countText: string | null;
    /** Whether the textarea is disabled. */
    disabled: boolean;
    /** Whether the textarea is read-only. */
    readOnly: boolean;
    /** Whether the textarea is required. */
    required: boolean;
    /** Whether the textarea is invalid. */
    invalid: boolean;
    /** The name of the textarea. */
    name: string | undefined;
    /** The most characters allowed. */
    maxLength: number | undefined;
}

export interface TextareaApi extends TextareaHandle {
    getRootProps(): PropTypes['element'];
    getTextareaProps(): PropTypes['textarea'];
    getLabelProps(): PropTypes['label'];
    getWordCountProps(): PropTypes['element'];
}
