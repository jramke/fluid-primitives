import type { EventObject } from '@zag-js/core';
import type { LiveRegion } from '@zag-js/live-region';
import type { PropTypes } from '@zag-js/types';
import type { FieldValue } from '../../Field/src/field.value';

/**
 * What a `translations.rowAdded` or `rowRemoved` callback receives, to build an announcement from the
 * data of the row, e.g. "Person Ada Lovelace was removed" instead of the generic default.
 */
export interface FieldArrayAnnounceInfo {
    /** The index of the row. */
    index: number;
    /**
     * The element of the row, for anything `getFieldValue` doesn't cover. For `rowRemoved` it is
     * already detached from the document, but still readable.
     */
    rowEl: HTMLElement;
    /** Reads the value of a field in this row by its bare name, `'firstName'` and not `people[2][firstName]`. */
    getFieldValue: (name: string) => FieldValue;
}

/** A screen reader announcement: a string, `false` to stay silent, or a callback that builds one from the row. */
export type FieldArrayAnnounceTranslation =
    string | false | ((info: FieldArrayAnnounceInfo) => string | false);

export interface FieldArrayProps {
    /** The unique identifier of the field array. */
    id: string;
    /** The name the rows are nested under, e.g. `people` for `people[0][firstName]`. */
    name: string;
    /**
     * The number of rows the server rendered. It sets the state of the triggers before hydration,
     * once the client runs it counts the rows in the DOM instead.
     */
    itemCount?: number;
    /**
     * The fewest rows allowed. At this many, `remove()` and the remove triggers do nothing and the
     * remove triggers are disabled.
     * @default 0
     */
    minItems?: number;
    /**
     * The most rows allowed. At this many, `append()` and the add trigger do nothing and the add
     * trigger is disabled.
     */
    maxItems?: number;
    /**
     * The screen reader announcements of an added and a removed row. In a string, `%number%` is
     * replaced with the 1-based position of the row, e.g. `Row %number% removed.`. A callback builds
     * the message from the data of the row, and may use `%number%` in what it returns.
     */
    translations?: {
        rowAdded?: FieldArrayAnnounceTranslation;
        rowRemoved?: FieldArrayAnnounceTranslation;
    };
    /**
     * Called after a row was added by the add trigger, with its index and the client names of the
     * components inside it, e.g. `['field', 'input']`. Call their `mountAll()` here: the field
     * array doesn't know what the template of a row contains.
     */
    onItemAdded?: (detail: { index: number; componentNames: string[] }) => void;
    /**
     * Called after a row was removed by its remove trigger, with the index it had. The later rows
     * have already moved up by then.
     */
    onItemRemoved?: (detail: { index: number }) => void;
}

export interface FieldArrayApiActions {
    /** The rows that currently exist, by index. */
    getRows(): { index: number }[];
    /** Whether `append()` would currently add a row, i.e. `maxItems` hasn't been reached. */
    canAppend(): boolean;
    /** Whether `remove()` would currently remove a row, i.e. more than `minItems` remain. */
    canRemove(): boolean;
    /** Adds a row, if `maxItems` allows it. */
    append(): void;
    /** Removes the row at `index`, if `minItems` allows it. */
    remove(index: number): void;
}

export interface FieldArrayApiProps {
    getAddTriggerProps(): PropTypes['button'];
    getRemoveTriggerProps(index: number): PropTypes['button'];
}

export interface FieldArrayApi extends FieldArrayApiActions, FieldArrayApiProps {}

export interface FieldArraySchema {
    props: FieldArrayProps;
    context: Record<string, never>;
    refs: {
        liveRegion: LiveRegion | null;
    };
    state: 'idle';
    event: EventObject;
    action: string;
    effect: string;
}
