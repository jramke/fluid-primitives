import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';

export interface TextareaTranslations {
    /** Set to `false` to omit the word count part's text and skip the announcements. */
    wordCount?: string | false;
}

export type TextareaSubmitOn = 'enter' | 'mod+enter';

export interface TextareaProps {
    id: string;
    /** Ids of the parts other elements reference: `textarea` (its label's `for`) and `wordCount`. */
    ids?: Record<string, string>;
    name?: string;
    disabled?: boolean;
    readOnly?: boolean;
    required?: boolean;
    invalid?: boolean;
    defaultValue?: string;
    maxLength?: number;
    rows?: number;
    /**
     * Submits the nearest `<form>` on a keypress instead of inserting a newline: `'enter'` submits
     * on plain Enter (Shift+Enter still inserts a newline), `'mod+enter'` submits on Cmd/Ctrl+Enter
     * (plain Enter always inserts a newline). Unset - the default - never intercepts Enter.
     */
    submitOn?: TextareaSubmitOn;
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
     * Runs on every native `input` event, before the value is committed - return the value to
     * actually write back to the textarea (e.g. trimmed, digits-only). Cursor position is
     * preserved across the rewrite. TS-only: functions can't cross the PHP -> client JSON
     * boundary, so this can only be set by constructing `Textarea` in a custom entry file.
     */
    transform?: (value: string, event: Event) => string;
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
    value: string;
    count: number;
    countText: string | null;
    disabled: boolean;
    readOnly: boolean;
    required: boolean;
    invalid: boolean;
    name: string | undefined;
    maxLength: number | undefined;
}

export interface TextareaApi extends TextareaHandle {
    getRootProps(): PropTypes['element'];
    getTextareaProps(): PropTypes['textarea'];
    getLabelProps(): PropTypes['label'];
    getWordCountProps(): PropTypes['element'];
}
