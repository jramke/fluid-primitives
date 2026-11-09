import type { ComponentHydrationData } from '../types';
import { parseNamespacedComponentName, toKebabCase } from './component-name';
import { getHydrationData, getNestedComponents } from './hydration';

interface RootIdRemap {
    /** The nested component's own namespaced hydration key (e.g. "primitives:field") - the same
     * opaque form `NestedComponentEntry.name` carries, matching what `getHydrationData`/`mountAll`
     * themselves require. */
    componentName: string;
    oldRootId: string;
    newRootId: string;
}

/**
 * `id` with the first old root id of `remaps` that stands on its own in it replaced by its new one,
 * or `null` when `id` references none. On its own means no letter, digit or `_` right before or
 * after it, as in `field:<rootId>:control` or `contact-<rootId>`: a plain substring would also match
 * the `2` of `12`, and only the first occurrence would miss the rootId `f` of `field:f:control`.
 *
 * Which segment matches when a rootId occurs twice (`field:field:control`) does not matter: the DOM
 * ids and the `ids` prop go through this same function, so they stay the same and unique per clone.
 */
function remapId(id: string, remaps: RootIdRemap[]): string | null {
    const isWordChar = (char: string | undefined) => char !== undefined && /\w/.test(char);
    for (const { oldRootId, newRootId } of remaps) {
        let index = id.indexOf(oldRootId);
        while (index !== -1) {
            const end = index + oldRootId.length;
            if (!isWordChar(id[index - 1]) && !isWordChar(id[end])) {
                return id.slice(0, index) + newRootId + id.slice(end);
            }
            index = id.indexOf(oldRootId, index + 1);
        }
    }
    return null;
}

/**
 * Rewrites, on every element in `root` (root itself included), whatever references one of
 * `remaps`' `oldRootId` so it references the matching `newRootId` instead: every part attribute of
 * that nested component (`data-<nested>-<part>="oldRootId"`), and any real `id` that embeds it (a
 * part given an explicit id through `ids`, see `ui:ref`). The id is rewritten via plain string
 * substitution, not recomputed from a formula - an Input's `label`/`input` parts are server-stamped
 * with their enclosing Field's own `label`/`control` ids (see `FieldIdMapping`), which an id
 * derived from Input's own root id would silently lose. Scoped to `root` (the whole cloned item,
 * not just one nested component's own subtree) since an override can reference a sibling
 * component's id, not just an ancestor's.
 */
function restampIdReferences(root: Element, remaps: RootIdRemap[]): void {
    const restamp = (el: Element) => {
        for (const { name, value } of Array.from(el.attributes)) {
            if (name === 'id') {
                const remapped = remapId(value, remaps);
                if (remapped !== null) el.setAttribute(name, remapped);
            } else if (name.startsWith('data-')) {
                const match = remaps.find(({ oldRootId }) => oldRootId === value);
                if (match) el.setAttribute(name, match.newRootId);
            }
        }
    };

    restamp(root);
    root.querySelectorAll('*').forEach(restamp);
}

/**
 * Remaps every value in a nested root component's `ids` prop override map using the same
 * old->new root id substitutions {@see restampIdReferences} applied to the DOM, so the hydration
 * props a later `mountAll()` constructs an instance from stay consistent with where its parts
 * actually live post-restamp - without this, a component like Input (whose `ids.label`/`ids.input`
 * may reference its enclosing Field's own ids, not its own) would keep looking for its label/
 * input elements at their stale, pre-restamp ids and find nothing.
 */
function remapIdsProp(ids: Record<string, string>, remaps: RootIdRemap[]): Record<string, string> {
    const remapped: Record<string, string> = {};
    for (const [key, value] of Object.entries(ids)) {
        remapped[key] = remapId(value, remaps) ?? value;
    }
    return remapped;
}

/**
 * Copies the hydration data a stencil registered at server render time to the id of one clone. It is
 * a copy, the stencil's own entry stays for the next clone.
 *
 * A `[]` "append" placeholder in a string `name` prop becomes `[value]`, the same trailing-`[]`
 * convention `form.path.ts`'s `appendFieldPathSegment` and `FieldContext.php` use.
 */
function copyNestedComponentEntry(
    componentName: string,
    stencilRootId: string,
    newRootId: string,
    remaps: RootIdRemap[],
    value: string
) {
    const stencilData = getHydrationData(componentName, stencilRootId);
    if (!stencilData) return;

    const rewrittenProps: ComponentHydrationData['props'] = {
        ...stencilData.props,
        id: newRootId,
        ids: remapIdsProp(stencilData.props.ids, remaps),
    };
    if (typeof rewrittenProps.name === 'string' && rewrittenProps.name.includes('[]')) {
        rewrittenProps.name = rewrittenProps.name.replace('[]', () => `[${value}]`);
    }

    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const clientBaseName = toKebabCase(baseName);
    window.FluidPrimitives.hydrationData[namespace] ??= {};
    window.FluidPrimitives.hydrationData[namespace][clientBaseName] ??= {};
    window.FluidPrimitives.hydrationData[namespace][clientBaseName][newRootId] = {
        ...stencilData,
        props: rewrittenProps,
    };
}

/**
 * The ids the nested root components of a stencil get in one clone: their own id with a `:<n>`
 * suffix, the same `n` for all so an `ids` override that references a sibling stays consistent. It is
 * the lowest `n` that no component has taken yet, read from the shared hydration data because
 * the page can hold two copies of this module.
 */
function nestedRootRemaps(stencilKey: string): RootIdRemap[] {
    const nested = getNestedComponents(stencilKey);
    let suffix = 1;
    while (nested.some(({ name, id }) => getHydrationData(name, `${id}:${suffix}`))) suffix++;

    return nested.map(({ name, id }) => ({
        componentName: name,
        oldRootId: id,
        newRootId: `${id}:${suffix}`,
    }));
}

/**
 * @internal
 *
 * Prepares every nested root component inside a freshly cloned `<template>` item, see
 * `ComponentHydrator.restampValue`. Every element that references one of their root ids is
 * re-stamped, not just the root elements: a part may reference the id of a different nested
 * component (Input's label and input reuse Field's ids), and so may their `ids` props. The
 * hydration data is copied for each, so `mountAll()` constructs them once the clone is in the page.
 */
export function prepareNestedRootComponents(stencilKey: string, root: Element, value: string) {
    const remaps = nestedRootRemaps(stencilKey);
    if (remaps.length === 0) return;

    // every mapping is known before the first rewrite, see remapIdsProp
    restampIdReferences(root, remaps);

    for (const { componentName, oldRootId, newRootId } of remaps) {
        copyNestedComponentEntry(componentName, oldRootId, newRootId, remaps, value);
    }
}
