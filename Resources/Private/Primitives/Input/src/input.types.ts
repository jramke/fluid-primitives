import type { EventObject } from '@zag-js/core';
import type { LiveRegion } from '@zag-js/live-region';
import type { PropTypes } from '@zag-js/types';

export interface InputTranslations {
    /** Set to `false` to omit the word count part's text and skip live-region announcements. */
    wordCount?: string | false;
}

export interface InputProps {
    id: string;
    name?: string;
    disabled?: boolean;
    readOnly?: boolean;
    required?: boolean;
    invalid?: boolean;
    defaultValue?: string;
    maxLength?: number;
    pattern?: string;
    inputMode?: string;
    translations?: InputTranslations;
    /**
     * Milliseconds to debounce word count live-region announcements by, so rapid typing doesn't
     * spam assistive tech on every keystroke. Set to 0 to announce every change immediately.
     * @default 600
     */
    announceDebounce?: number;
    /**
     * Runs on every native `input` event, before the value is committed - return the value to
     * actually write back to the input (e.g. uppercased, digits-only). Cursor position is
     * preserved across the rewrite. TS-only: functions can't cross the PHP -> client JSON
     * boundary, so this can only be set by constructing `Input` in a custom entry file.
     */
    transform?: (value: string, event: Event) => string;
    onValueChange?: (details: { value: string }) => void;
}

export interface InputSchema {
    props: InputProps;
    context: {
        value: string;
    };
    refs: {
        liveRegion: LiveRegion | null;
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

export interface InputApi extends InputHandle {
    getRootProps(): PropTypes['element'];
    getInputProps(): PropTypes['input'];
    getLabelProps(): PropTypes['label'];
    getWordCountProps(): PropTypes['element'];
    getLiveRegionProps(): PropTypes['element'];
}
