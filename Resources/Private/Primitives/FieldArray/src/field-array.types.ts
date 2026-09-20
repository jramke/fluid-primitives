import type { EventObject } from '@zag-js/core';
import type { LiveRegion } from '@zag-js/live-region';
import type { PropTypes } from '@zag-js/types';
import type { FieldValue } from '../../Field/src/field.types';

/**
 * Handed to a `translations.rowAdded`/`rowRemoved` callback (in place of a plain string) so it can
 * build an announcement from the row's own data, e.g. "Person Ada Lovelace was removed" instead of
 * the generic default - `getFieldValue` resolves a *bare* field name (`'firstName'`, not the full
 * `people[2][firstName]`) scoped to this one row. `rowEl` is handed over as an escape hatch for
 * anything `getFieldValue` doesn't cover; for `rowRemoved` it's already detached from the document
 * (removal happens first) but still fully readable, since `.remove()` only unlinks a node, it
 * doesn't clear it.
 */
export interface FieldArrayAnnounceInfo {
    index: number;
    rowEl: HTMLElement;
    getFieldValue: (name: string) => FieldValue;
}

export type FieldArrayAnnounceTranslation =
    string | false | ((info: FieldArrayAnnounceInfo) => string | false);

export interface FieldArrayProps {
    id: string;
    name: string;
    /**
     * Number of rows rendered server-side - only used by `Root.fluid.html`/`FieldArrayContext` to
     * get `emptyState`/`addTrigger`/`removeTrigger` right before hydration; the client machine
     * itself always derives `canAppend`/`canRemove`/row count from the live DOM instead (see
     * `field-array.connect.ts`), so this is inert once JS takes over.
     */
    itemCount?: number;
    /**
     * Minimum number of rows required - once exactly this many remain, `remove()` (and a click on
     * any row's `removeTrigger`) is a no-op, and every `removeTrigger` element is disabled.
     * @default 0
     */
    minItems?: number;
    /**
     * Maximum number of rows allowed - once this many exist, `append()` (and a click on
     * `addTrigger`) is a no-op, and `addTrigger` is disabled.
     */
    maxItems?: number;
    /**
     * A `%number%` placeholder in a plain string is replaced with the row's 1-based position (see
     * `Root.fluid.html`'s own prop for the localized defaults, e.g. "Row %number% removed."). Pass
     * a callback instead to build the message from the row's own field values via
     * `FieldArrayAnnounceInfo.getFieldValue` - `%number%` is still substituted in whatever string a
     * callback returns, so it can rely on the same placeholder rather than interpolating it by hand.
     */
    translations?: {
        rowAdded?: FieldArrayAnnounceTranslation;
        rowRemoved?: FieldArrayAnnounceTranslation;
    };
    /**
     * Called after a new row is appended (from a click on `addTrigger`) with its index and the
     * client component names `ComponentHydrator.restampValue` found nested inside it (e.g.
     * `['field', 'input']`) - call the matching `mountAll()`s here, since `FieldArray` itself doesn't know
     * which primitives a row's own `itemTemplate` contains. Mirrors `Form`'s own `onSubmit`/
     * `render` prop-callback convention, rather than requiring every consumer to wire up the
     * `fluid-primitives:field-array:itemadded` DOM event by hand - that event still fires too, for
     * anything that needs to react without holding a reference to this instance.
     */
    onItemAdded?: (detail: { index: number; componentNames: string[] }) => void;
    /**
     * Called after a row (from a click on that row's own `removeTrigger`) has been torn down and
     * removed, with the index it was removed from (later rows have already been reindexed by this
     * point). Mirrors `onItemAdded` - a `fluid-primitives:field-array:itemremoved` DOM event also
     * fires alongside it, for the same reason.
     */
    onItemRemoved?: (detail: { index: number }) => void;
}

export interface FieldArrayApiActions {
    getRows(): { index: number }[];
    /** Whether `append()` would currently add a row, i.e. `maxItems` hasn't been reached. */
    canAppend(): boolean;
    /** Whether `remove()` would currently remove a row, i.e. more than `minItems` remain. */
    canRemove(): boolean;
    append(): void;
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
