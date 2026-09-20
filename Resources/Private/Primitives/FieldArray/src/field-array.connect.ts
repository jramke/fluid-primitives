import { nextTick } from '@zag-js/dom-query';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import {
    destroyComponentsWithin,
    hydrateTemplateClone,
    renameNestedRootComponents,
} from '../../../Client/src/lib/hydration';
import { Template } from '../../../Client/src/lib/template';
import type { FieldValue } from '../../Field/src/field.types';
import { getFieldValueFromContainer } from '../../Field/src/field.utils';
import { renameFieldMachineForForm } from '../../Form/src/form.registry';
import type { FieldArray } from '../FieldArray';
import { parts } from './field-array.anatomy';
import * as dom from './field-array.dom';
import type { FieldArrayApi, FieldArrayApiActions, FieldArrayApiProps } from './field-array.types';

/**
 * Builds `FieldArray`'s API. Deliberately takes the `Component` instance itself, not a Zag
 * `service` the way `Field`/`Form`'s own `connect()` do - their API is "read context, return a
 * plain object", but `append`/`remove` here are DOM mutations over an open-ended row collection,
 * which needs `Component`-level helpers (`getElement`/`getElements`/`hydrator`/`refresh`) that a
 * Zag service's `context`/`scope` alone don't provide. Still takes `normalize` like every other
 * `connect()` though, so `getAddTriggerProps`/`getRemoveTriggerProps` stay framework-portable and
 * carry the same `data-scope`/`data-part` anatomy attrs every other primitive's parts do.
 */
export function connect<T extends PropTypes>(
    component: FieldArray,
    normalize: NormalizeProps<T>
): FieldArrayApi {
    const actions: FieldArrayApiActions = {
        getRows: () => getRows(component),
        canAppend: () => canAppend(component),
        canRemove: () => canRemove(component),
        append: () => append(component),
        remove: index => remove(component, index),
    };

    const props: FieldArrayApiProps = {
        getAddTriggerProps: () => {
            const disabled = !actions.canAppend();
            return normalize.button({
                ...parts.addTrigger.attrs,
                id: dom.getAddTriggerId(component.machine.scope),
                onClick: () => actions.append(),
                'aria-disabled': disabled ? true : undefined,
                'data-disabled': disabled ? true : undefined,
            });
        },
        // No `id` here, unlike every other part's `getXProps()` (including `getAddTriggerProps`
        // above) - `removeTrigger`'s `id` is a *multi-instance*, per-row one, and its ownership
        // already belongs entirely to `ComponentHydrator.restampValue`/`hydrateTemplateClone`/
        // `renameNestedRootComponents` (see `reindexRowsAfter` below), which re-key it whenever a
        // row is cloned or shifted. Recomputing it here from `index` too would create a second,
        // independent source of truth for the same id, and this one has no way to even agree with
        // the other - it has no access to the stencil-rootId part those functions restamp from.
        getRemoveTriggerProps: (index: number) => {
            const disabled = !actions.canRemove();
            return normalize.button({
                ...parts.removeTrigger.attrs,
                onClick: () => actions.remove(index),
                'aria-disabled': disabled ? true : undefined,
                'data-disabled': disabled ? true : undefined,
                'data-value': index,
            });
        },
    };

    return {
        ...actions,
        ...props,
    };
}

function getRows(component: FieldArray): { index: number }[] {
    return component
        .getElements<HTMLElement>('item')
        .map(el => ({ index: Number(el.dataset.value) }))
        .filter(({ index }) => !Number.isNaN(index))
        .sort((a, b) => a.index - b.index);
}

function canAppend(component: FieldArray): boolean {
    const maxItems = component.machine.prop('maxItems');
    return maxItems == null || getRows(component).length < maxItems;
}

function canRemove(component: FieldArray): boolean {
    const minItems = component.machine.prop('minItems') ?? 0;
    return getRows(component).length > minItems;
}

/**
 * Clones the `itemTemplate` stencil for a new row and appends it to `itemGroup`, exactly
 * `FileUpload.renderItems()`'s pattern for a newly-picked file - `hydrateTemplateClone`
 * additionally prepares any nested root components (Field/Input/...) the row contains, but
 * doesn't construct them (see its own docblock for why that step can't happen here) - the
 * `onItemAdded` prop and the `fluid-primitives:field-array:itemadded` event this fires both
 * carry the client component names found, so consumer code can call `mountAll()` again for
 * each of them.
 */
function append(component: FieldArray): void {
    if (!component.hydrator) return;
    if (!canAppend(component)) return;

    const itemGroupEl = component.getElement('itemGroup');
    if (!itemGroupEl) return;

    const index = nextIndex(component);
    const clone = new Template(component.hydrator, 'itemTemplate', { value: String(index) });
    const componentNames = hydrateTemplateClone(clone.root, String(index));

    itemGroupEl.appendChild(clone);

    const detail = { index, componentNames };
    itemGroupEl.dispatchEvent(
        new CustomEvent('fluid-primitives:field-array:itemadded', { bubbles: true, detail })
    );
    component.machine.prop('onItemAdded')?.(detail);

    announce(component, 'rowAdded', index, clone.root);
    component.refresh();
}

/**
 * Removes a row and shifts every later row's index (and its nested fields' `name`s) down by
 * one, via `renameFieldMachineForForm` - the same mechanism `Form.api.renameField` itself uses,
 * which keeps recurring-field rows contiguously indexed without touching any field's value/
 * touched/dirty/error state.
 */
function remove(component: FieldArray, index: number): void {
    if (!canRemove(component)) return;

    const rowEl = component
        .getElements<HTMLElement>('item')
        .find(el => el.dataset.value === String(index));
    if (!rowEl) return;

    const itemGroupEl = component.getElement('itemGroup');
    const focusTargetEl = findFocusTargetAfterRemoval(component, rowEl);
    // Read the row's own field values before it's torn down and detached - `rowEl` stays fully
    // readable afterward (`.remove()` only unlinks a node), but announcing first keeps "read the
    // row" and "destroy the row" from being interleaved.
    announce(component, 'rowRemoved', index, rowEl);

    destroyComponentsWithin(rowEl);
    rowEl.remove();
    reindexRowsAfter(component, index);

    const detail = { index };
    itemGroupEl?.dispatchEvent(
        new CustomEvent('fluid-primitives:field-array:itemremoved', { bubbles: true, detail })
    );
    component.machine.prop('onItemRemoved')?.(detail);

    component.refresh();

    nextTick(() => focusTargetEl?.focus());
}

/**
 * Resolves what should receive focus once `rowEl` is gone, so keyboard/screen-reader users
 * don't lose focus to `<body>` when the element they were just interacting with (its own
 * `removeTrigger`) disappears: the next row's `removeTrigger`, or the previous row's if the
 * removed row was last, or `addTrigger` if no rows remain. Resolved to a real element
 * *before* removal, so the reference stays valid through the DOM mutation and reindexing that
 * follow - `getElements('item')` already reflects visual/DOM order, so this doesn't need to
 * reason about numeric row indices at all.
 */
function findFocusTargetAfterRemoval(
    component: FieldArray,
    rowEl: HTMLElement
): HTMLElement | null {
    const rows = component.getElements<HTMLElement>('item');
    const removedPosition = rows.indexOf(rowEl);
    const siblingRowEl = rows[removedPosition + 1] ?? rows[removedPosition - 1];

    return (
        siblingRowEl?.querySelector<HTMLElement>('[data-part="remove-trigger"]') ??
        component.getElement<HTMLElement>('addTrigger')
    );
}

function nextIndex(component: FieldArray): number {
    const rows = getRows(component);
    return rows.length > 0 ? Math.max(...rows.map(row => row.index)) + 1 : 0;
}

function reindexRowsAfter(component: FieldArray, removedIndex: number): void {
    const rowsToShift = getRows(component).filter(({ index }) => index > removedIndex);

    for (const { index } of rowsToShift) {
        const rowEl = component
            .getElements<HTMLElement>('item')
            .find(el => el.dataset.value === String(index));
        if (!rowEl) continue;

        const newIndex = index - 1;

        rowEl
            .querySelectorAll<HTMLElement>('[data-scope="field"][data-part="root"]')
            .forEach(fieldRootEl => {
                const oldName = fieldRootEl.dataset.name;
                if (!oldName) return;
                const newName = oldName.replace(`[${index}]`, `[${newIndex}]`);
                renameFieldMachineForForm(fieldRootEl, oldName, newName);
            });

        // renameFieldMachineForForm (above) only updates each nested field's `name` prop for
        // submission purposes - it never touches DOM ids. Without also re-keying those here, a
        // later row appended at this now-freed-up index would clone the same stencil and collide
        // with these nested Field/Input's still-stale ids (see `renameNestedRootComponents`'s own
        // docblock for the full mechanism).
        renameNestedRootComponents(rowEl, String(index), String(newIndex));
        component.hydrator?.restampValue(rowEl, String(newIndex));
    }
}

function announce(
    component: FieldArray,
    translationKey: 'rowAdded' | 'rowRemoved',
    index: number,
    rowEl: HTMLElement
): void {
    const entry = component.machine.prop('translations')?.[translationKey];
    const text =
        typeof entry === 'function'
            ? entry({
                  index,
                  rowEl,
                  getFieldValue: (name: string) => getRowFieldValue(component, rowEl, index, name),
              })
            : entry;

    if (typeof text !== 'string' || text === '') return;

    component.machine.refs
        .get('liveRegion')
        ?.announce(text.replaceAll('%number%', String(index + 1)));
}

function getRowFieldValue(
    component: FieldArray,
    rowEl: HTMLElement,
    index: number,
    name: string
): FieldValue {
    const arrayName = component.machine.prop('name');
    return getFieldValueFromContainer(rowEl, `${arrayName}[${index}][${name}]`);
}
