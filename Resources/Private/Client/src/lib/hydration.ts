import { ListCollection, type CollectionItem } from '@zag-js/collection';
import type { ComponentHydrationData, FluidPrimitivesGlobals } from '../types';
import { Component } from './component';

// Keep in sync with: Classes/Utility/ComponentUtility.php
const ID_NAMESPACE_OVERRIDES: Record<string, string> = {
    'navigation-menu': 'nav-menu',
    clipboard: 'clip',
    'file-upload': 'file',
};

// Keep in sync with: Classes/Utility/ComponentUtility.php
type PartSegmentOverride =
    | string
    | {
          segment: string;
          valueSeparator?: string;
          rootIdSeparator?: string;
          segmentSeparator?: string;
          namespace?: string;
      };

const PART_SEGMENT_OVERRIDES: Record<string, Record<string, PartSegmentOverride>> = {
    // TODO: Revisit this override map after upgrading to zag-js v2.
    'radio-group': {
        item: 'radio',
        itemHiddenInput: 'radio:input',
        itemControl: 'radio:control',
        itemText: 'radio:label',
    },
    accordion: {
        itemTrigger: 'trigger',
        itemContent: 'content',
    },
    select: {
        hiddenSelect: 'select',
        itemGroup: 'optgroup',
        itemGroupLabel: 'optgroup-label',
        item: 'option',
    },
    combobox: {
        positioner: 'popper',
        trigger: 'toggle-btn',
        clearTrigger: 'clear-btn',
        itemGroup: 'optgroup',
        itemGroupLabel: 'optgroup-label',
        item: 'option',
    },
    tabs: {
        trigger: { segment: 'trigger', valueSeparator: '-' },
        content: { segment: 'content', valueSeparator: '-' },
    },
    'number-input': {
        incrementTrigger: 'inc',
        decrementTrigger: 'dec',
    },
    popover: {
        positioner: 'popper',
        description: 'desc',
        closeTrigger: 'close',
    },
    switch: {
        hiddenInput: 'input',
    },
    'file-upload': {
        hiddenInput: 'input',
        itemSizeText: 'item-size',
        itemDeleteTrigger: 'item-delete',
    },
    tooltip: {
        positioner: 'popper',
    },
    dialog: {
        closeTrigger: 'close',
    },
    menu: {
        contextTrigger: 'ctx-trigger',
        positioner: 'popper',
        itemGroup: 'group',
        itemGroupLabel: 'group-label',
        item: {
            namespace: '',
            segment: '',
            rootIdSeparator: '',
            segmentSeparator: '',
            valueSeparator: '/',
        },
    },
    slider: {
        valueText: 'value-text',
        hiddenInput: 'input',
    },
    'scroll-area': {
        root: { segment: 'root', rootIdSeparator: '-' },
        viewport: { segment: 'viewport', rootIdSeparator: '-' },
        content: { segment: 'content', rootIdSeparator: '-' },
    },
};

// A part's own name is lowerCamelCase (mirroring zag-js's own `ids` prop keys, so overriding a
// part's id reads the same way it does in zag itself), but `data-part` always renders lower-kebab
// for CSS/selector consistency. Keep in sync with: Classes/Utility/ComponentUtility.php's
// camelCaseToLowerCaseDashed()/lowerCaseDashedToCamelCase().
export function toKebabCase(part: string): string {
    return part.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

function toCamelCase(part: string): string {
    return part.replace(/-([a-z0-9])/g, (_match, char: string) => char.toUpperCase());
}

export function getHydrationData(component: string): Record<string, ComponentHydrationData> | null;
export function getHydrationData(component: string, id: string): ComponentHydrationData | null;
export function getHydrationData(component?: string, id?: string) {
    const hydrationData = window.FluidPrimitives?.hydrationData;

    if (!hydrationData || typeof hydrationData !== 'object') {
        return null;
    }

    if (!component) {
        return hydrationData;
    }

    // Hydration data itself is always registered under the kebab-case clientComponentName
    // (mirrors PHP's HydrationRegistry) - converting here means every caller, direct or via
    // mount()/mountAll(), can consistently pass the same camelCase name used everywhere else
    // (static componentName, getElement() part names).
    const clientComponentName = toKebabCase(component);

    if (!hydrationData[clientComponentName]) {
        return null;
    }

    if (!id) {
        return hydrationData[clientComponentName];
    }

    return hydrationData[clientComponentName][id] || null;
}

/**
 * Looks up another already-mounted, uncontrolled component instance by its component name and
 * hydration id (the `id` prop it was rendered with). For primitives that compose two independent
 * instances of themselves at runtime (e.g. Menu submenus linking a parent/child pair via their own
 * `id`s) rather than through props alone.
 */
export function getComponentInstance<
    T extends Component<unknown, unknown> = Component<unknown, unknown>,
>(componentName: string, id: string): T | undefined {
    const clientComponentName = toKebabCase(componentName);
    return window.FluidPrimitives?.uncontrolledInstances?.[clientComponentName]?.[id] as
        T | undefined;
}

export function getGlobals(): FluidPrimitivesGlobals | null {
    const globals = window.FluidPrimitives?.globals;

    if (!globals || typeof globals !== 'object') {
        return null;
    }

    return globals;
}

export function getGlobal<T = unknown>(key: string): T | undefined {
    const globals = getGlobals();
    if (!globals || !(key in globals)) {
        return undefined;
    }

    return globals[key] as T;
}

/**
 * Dev-only safety net for a footgun inherent to `ui:ref`: a part rendered without a `value`
 * discriminator gets the same `id` every time it renders, which is correct for a true singleton
 * part (root, trigger, content, ...) but silently produces duplicate ids if the same value-less
 * part is placed more than once per component instance (e.g. a decorative separator between
 * groups) - duplicate DOM ids don't throw, they just make `getElementById`/`querySelector`-based
 * lookups (including this library's own `getElement`) silently resolve to whichever element
 * happens to match first.
 *
 * Scoped to `[data-scope][id]` so it only ever flags fluid-primitives-managed elements, never
 * unrelated ids elsewhere on the consumer's page. Gated by `window.FluidPrimitives.globals.debug`
 * (see {@see getGlobal}) - set automatically to whether TYPO3's own Application Context is
 * development ({@see \Jramke\FluidPrimitives\Registry\HydrationRegistry}), nothing for a consumer
 * to configure. Never runs unless that's true, so it costs nothing in production and never needs
 * stripping from the bundle.
 */
export function warnAboutDuplicateIds(root: Document | Element = document): void {
    if (!getGlobal<boolean>('debug')) return;

    const elementsById = new Map<string, Element[]>();
    root.querySelectorAll('[data-scope][id]').forEach(el => {
        const matches = elementsById.get(el.id) ?? [];
        matches.push(el);
        elementsById.set(el.id, matches);
    });

    for (const [id, elements] of elementsById) {
        if (elements.length <= 1) continue;

        console.warn(
            `[fluid-primitives] Duplicate id "${id}" found on ${elements.length} elements. ` +
                'A part rendered without a `value` discriminator was likely used more than once ' +
                'in the same component instance - see the "Marking Elements for Hydration" section ' +
                'of the Hydration docs.',
            elements
        );
    }
}

let duplicateIdCheckScheduled = false;

/**
 * Coalesces {@see warnAboutDuplicateIds} calls into one scan per burst of hydration, rather than
 * one per `ComponentHydrator` constructed (a page can construct dozens in one synchronous burst
 * during initial hydration). Scheduled via a microtask so it runs once, right after the current
 * burst finishes, regardless of how many hydrators triggered it.
 */
function scheduleDuplicateIdCheck(): void {
    if (!getGlobal<boolean>('debug')) return;
    if (duplicateIdCheckScheduled) return;

    duplicateIdCheckScheduled = true;
    queueMicrotask(() => {
        duplicateIdCheckScheduled = false;
        warnAboutDuplicateIds();
    });
}

/**
 * Mounts every not-yet-mounted, uncontrolled hydration instance of `componentName`. Safe to call
 * more than once (e.g. after lazily-inserted DOM adds new instances) - already mounted instances
 * are skipped rather than re-instantiated.
 */
export function mountAll(
    componentName: string,
    callback: (
        data: ComponentHydrationData & { createHydrator: () => ComponentHydrator }
    ) => Component<unknown, unknown> | void
) {
    const hydrationInstances = getHydrationData(componentName);
    if (!hydrationInstances) return;

    // Keyed by the kebab form so two mountAll() calls for the same component (one camelCase, one
    // still kebab mid-migration) share one tracked-instance bucket instead of silently doubling up.
    const clientComponentName = toKebabCase(componentName);
    if (!window.FluidPrimitives.uncontrolledInstances[clientComponentName]) {
        window.FluidPrimitives.uncontrolledInstances[clientComponentName] = {};
    }
    const mountedInstances = window.FluidPrimitives.uncontrolledInstances[clientComponentName];

    Object.keys(hydrationInstances).forEach(id => {
        if (hydrationInstances[id].controlled) return;
        if (mountedInstances[id]) return;

        const instance = callback({
            ...hydrationInstances[id],
            createHydrator: () =>
                new ComponentHydrator(componentName, id, hydrationInstances[id].props.ids),
        });
        if (!instance) return;

        mountedInstances[id] = instance;
    });
}

/**
 * Destroys every mounted, uncontrolled component instance whose root element
 * is `root` itself or a descendant of it, and drops them from the tracked
 * instance registry. Intended for cleaning up before removing a subtree from
 * the DOM (e.g. a lazily-mounted recurring-field row).
 *
 * Matches only on the instance's own `[data-part="root"]` element, not a bare id-prefix - a
 * prefix-only match would also catch that instance's *own* repeated sub-parts (e.g. a
 * `FieldArray`'s own `removeTrigger`/`item` ids all start with the same `field-array:{rootId}`
 * prefix as the `FieldArray` instance itself), which would destroy the very component whose row
 * is merely being removed, rather than only what's actually nested inside that row.
 */
export function destroyComponentsWithin(root: Element | Document) {
    if (!window.FluidPrimitives) return;

    for (const instances of Object.values(window.FluidPrimitives.uncontrolledInstances)) {
        for (const [id, instance] of Object.entries(instances)) {
            // Ids are always generated from the kebab clientComponentName, not the canonical
            // componentName instance.getName() itself returns - see Component.getClientName().
            const clientComponentName = instance.getClientName();
            const rootPartSelector = `[id^="${clientComponentName}:${id}"][data-part="root"]`;
            const isRootItself = root instanceof Element && root.matches(rootPartSelector);
            const hasRootPartInNode = root.querySelectorAll(rootPartSelector).length > 0;

            if (isRootItself || hasRootPartInNode) {
                console.log(`Destroying component instance: ${clientComponentName}:${id}`);
                instance.destroy();
                delete instances[id];
            }
        }
    }
}

/**
 * Gets one specific hydration instance of `componentName` by its `rootId` and hands it to
 * `callback`, regardless of whether it was rendered with `controlled="{true}"`. Unlike
 * {@see mountAll}, this does not track mounted state - calling it twice for the same `rootId`
 * runs the callback twice, and instances created this way are invisible to
 * {@see destroyComponentsWithin}.
 */
export function mount<T>(
    componentName: string,
    rootId: string,
    callback: (data: ComponentHydrationData & { createHydrator: () => ComponentHydrator }) => T
): T | undefined {
    const hydrationData = getHydrationData(componentName, rootId);
    if (!hydrationData) return undefined;

    return callback({
        ...hydrationData,
        createHydrator: () => new ComponentHydrator(componentName, rootId, hydrationData.props.ids),
    });
}

export class ComponentHydrator {
    componentName: string;
    // Kebab form of componentName - what actually appears in data-scope, hydration ids, and the
    // ID_NAMESPACE_OVERRIDES/PART_SEGMENT_OVERRIDES maps below (kept in sync with
    // ComponentPartIdUtility's own kebab maps). Derived once here so nothing downstream converts
    // repeatedly.
    clientComponentName: string;
    doc: Document;
    rootId: string;
    ids: { [key: string]: string };

    constructor(
        componentName: string,
        rootId: string | undefined,
        ids: { [key: string]: string } = {},
        doc: Document = document
    ) {
        this.componentName = componentName;
        this.clientComponentName = toKebabCase(componentName);
        this.doc = doc;
        if (!rootId) {
            throw new Error(`Root ID is required for component hydration: ${componentName}`);
        }
        this.rootId = rootId;
        this.ids = ids;
        scheduleDuplicateIdCheck();
    }

    private getIdNamespace(): string {
        return ID_NAMESPACE_OVERRIDES[this.clientComponentName] ?? this.clientComponentName;
    }

    private getPartConfig(part: string): {
        segment: string;
        valueSeparator: string;
        rootIdSeparator: string;
        segmentSeparator: string;
        namespace?: string;
    } {
        const override = PART_SEGMENT_OVERRIDES[this.clientComponentName]?.[part];
        if (!override) {
            return {
                segment: part,
                valueSeparator: ':',
                rootIdSeparator: ':',
                segmentSeparator: ':',
            };
        }
        if (typeof override === 'string') {
            return {
                segment: override,
                valueSeparator: ':',
                rootIdSeparator: ':',
                segmentSeparator: ':',
            };
        }

        return {
            segment: override.segment,
            valueSeparator: override.valueSeparator ?? ':',
            rootIdSeparator: override.rootIdSeparator ?? ':',
            segmentSeparator: override.segmentSeparator ?? ':',
            namespace: override.namespace ?? undefined,
        };
    }

    private computePartId(part: string, value?: string): string {
        if (this.ids[part]) {
            return this.ids[part];
        }

        const idNamespace = this.getIdNamespace();
        const {
            segment: partSegment,
            valueSeparator,
            rootIdSeparator,
            segmentSeparator,
            namespace,
        } = this.getPartConfig(part);

        const resolvedIdNamespace = namespace ?? idNamespace;

        if (part === 'root') {
            return `${resolvedIdNamespace}${rootIdSeparator}${this.rootId}`;
        }

        if (value !== undefined && value !== '') {
            return `${resolvedIdNamespace}${rootIdSeparator}${this.rootId}${segmentSeparator}${partSegment}${valueSeparator}${value}`;
        }

        return `${resolvedIdNamespace}${rootIdSeparator}${this.rootId}${segmentSeparator}${partSegment}`;
    }

    private getValueSeparatorForPart(part: string): string {
        const { valueSeparator } = this.getPartConfig(part);
        return valueSeparator;
    }

    getElement<T extends Element>(part: string, parent: Element | Document = this.doc): T | null {
        const isDoc = parent === this.doc;
        const partId = this.computePartId(part);

        if (isDoc) {
            // Use getElementById (no CSS-escaping needed; IDs may contain colons)
            return this.doc.getElementById(partId) as T | null;
        }

        const dataPart = toKebabCase(part);
        const escapedPartId = CSS.escape(partId);

        return (parent as Element).querySelector<T>(
            `[id="${escapedPartId}"][data-part="${dataPart}"],[id^="${escapedPartId}"][data-part="${dataPart}"]`
        );
    }

    getElements<T extends Element>(part: string, parent?: Element | Document): T[] {
        const searchScope: Element | Document =
            parent !== undefined ? parent : this.getElement('root') || this.doc;

        const dataPart = toKebabCase(part);
        const escapedPartId = CSS.escape(this.computePartId(part));
        const escapedValueSeparator = CSS.escape(this.getValueSeparatorForPart(part));

        return Array.from(
            searchScope.querySelectorAll<T>(
                `[id="${escapedPartId}"][data-part="${dataPart}"],[id^="${escapedPartId + escapedValueSeparator}"][data-part="${dataPart}"]`
            )
        );
    }

    generateRefAttributesString(part: string, value?: string): string {
        const id = this.computePartId(part, value);
        return `id="${id}" data-scope="${this.clientComponentName}" data-part="${toKebabCase(part)}"${value !== undefined ? ` data-value="${value}"` : ''}`;
    }

    setRefAttributes(element: Element, part: string, value?: string): void {
        element.setAttribute('id', this.computePartId(part, value));
        element.setAttribute('data-scope', this.clientComponentName);
        element.setAttribute('data-part', toKebabCase(part));
        if (value !== undefined) {
            element.setAttribute('data-value', value);
        }
    }

    /**
     * Re-stamps every ref'd element within `root` (root included) for a new, real `value`.
     * Scoped to this component (`data-scope`) so a nested, unrelated component's own
     * value-scoped parts aren't touched.
     *
     * Use after cloning a `<template>` (see `Template`) to make the clone represent one real
     * item/row in a single call, instead of manually recomputing
     * `id`/`data-scope`/`data-part`/`data-value` for the root and separately for every nested
     * value-scoped part (e.g. a combobox item's own `item-text`/`item-indicator`). Fully generic -
     * not combobox-specific - so it applies unmodified to any future dynamic-item scenario
     * (file-upload item previews, recurring/array form-field rows, or another primitive's own
     * async items).
     *
     * `attributes`, if given, are additionally set on the same elements `value` is applied to - for
     * primitives whose `render()` reads plain per-element data attributes rather than resolving a
     * collection item (e.g. RadioGroup's `disabled`/`invalid`, NavigationMenu's `Link` `current`).
     * A boolean value toggles a bare `data-x` attribute (e.g. `{ disabled: true }` -> `data-disabled`,
     * present only when true); a string value sets `data-x="value"` directly.
     */
    restampValue(
        root: Element,
        value: string,
        attributes?: Record<string, boolean | string>
    ): void {
        const restamp = (el: Element) => {
            const rawPart = el.getAttribute('data-part');
            if (!rawPart) return;
            const part = toCamelCase(rawPart);
            this.setRefAttributes(el, part, value);
            for (const [name, attributeValue] of Object.entries(attributes ?? {})) {
                if (typeof attributeValue === 'string') {
                    el.setAttribute(`data-${name}`, attributeValue);
                } else {
                    el.toggleAttribute(`data-${name}`, attributeValue);
                }
            }
        };

        if (
            root.getAttribute('data-scope') === this.clientComponentName &&
            root.hasAttribute('id')
        ) {
            restamp(root);
        }
        root.querySelectorAll(`[data-scope="${this.clientComponentName}"][id]`).forEach(restamp);
    }

    destroy() {
        // No-op for now; if we ever need to clean up anything, do it here.
    }
}

interface RootIdRemap {
    clientComponentName: string;
    oldRootId: string;
    newRootId: string;
}

/**
 * True when `id` references `rootId` as a whole id-segment - i.e. `id` either *is* `rootId`, or
 * contains it immediately followed by `:` - not merely as a substring. A plain `.includes()`
 * would false-positive match row `2`'s root id against row `20`'s (both contain the digit `2`).
 */
function referencesRootId(id: string, rootId: string): boolean {
    const index = id.indexOf(rootId);
    if (index === -1) return false;
    const charAfter = id[index + rootId.length];
    return charAfter === undefined || charAfter === ':';
}

/**
 * Restamps every `[id]` element in `root` (root itself included) whose id references one of
 * `remaps`' `oldRootId` to reference the matching `newRootId` instead - via plain string
 * substitution, not by recomputing each part's id from its own component's default formula. A
 * from-scratch regeneration would only ever reproduce *this* component's own default id
 * namespace, silently dropping an id a part was given that reuses a *different* nested
 * component's own namespace instead - e.g. an Input's `label`/`input` parts are server-stamped to
 * reuse their enclosing Field's own `label`/`control` ids (so Field's own `getElement('label')`
 * resolves to Input's actual label element, and so Input's own `render()` - which looks its label/
 * input elements up by that same id via `getElementById` - can find them at all), not Input's own
 * default `input:{id}:label`/`input:{id}:input`. A from-scratch regeneration silently breaks that
 * link on every clone, which is why a newly-added row's fields lose their label association and
 * their `<input>` never gets hydrated in the first place. Scoped to `root` (the whole cloned row,
 * not just one nested component's own subtree) since an override can reference a sibling
 * component's id, not just an ancestor's.
 */
function restampIdReferences(root: Element, remaps: RootIdRemap[]): void {
    const restamp = (el: Element) => {
        const id = el.getAttribute('id');
        if (!id) return;
        const match = remaps.find(({ oldRootId }) => referencesRootId(id, oldRootId));
        if (match) el.setAttribute('id', id.replace(match.oldRootId, match.newRootId));
    };

    if (root instanceof HTMLElement) restamp(root);
    root.querySelectorAll<HTMLElement>('[id]').forEach(restamp);
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
        const match = remaps.find(({ oldRootId }) => referencesRootId(value, oldRootId));
        remapped[key] = match ? value.replace(match.oldRootId, match.newRootId) : value;
    }
    return remapped;
}

/**
 * Prepares a cloned `<template>` row (see {@see Template}) for hydration when it contains nested,
 * independent root components (e.g. a `Field`+`Input` inside a `FieldArray` row) -
 * {@see ComponentHydrator.restampValue} alone only re-stamps ids within the cloned component's own
 * `data-scope`, leaving a nested root component's rootId baked into the static template HTML,
 * which would collide the moment a second row is cloned.
 *
 * For every nested root-component marker found (`[data-part="root"]`, excluding the clone's own
 * root - that one belongs to the outer component, not a nested one), this looks up the stencil's
 * cached hydration props (registered once at server-render time, when that one stencil instance
 * rendered), deep-clones them, rewrites a `[]` "append" placeholder in any string `name` prop to
 * `value` (the same trailing-`[]`-means-append convention `form.path.ts`'s
 * `appendFieldPathSegment`/`FieldContext.php` use), derives a fresh id, registers the rewritten
 * props into `window.FluidPrimitives.hydrationData` under that new id so a subsequent `mountAll()`
 * picks it up, and re-stamps *every* `[id]` element in the row that references the old root id -
 * not just the root element itself - via {@see restampIdReferences}, plus the same substitution
 * applied to the stencil's own `ids` prop override map via {@see remapIdsProp}. Without this, a
 * nested component's non-root parts would keep the stencil's original ids baked in and collide
 * across every clone, since they're outside `restampValue`'s own `data-scope` (which only covers
 * the *outer* `FieldArray`'s parts) - and a part whose id was server-stamped to reuse a
 * *different* component's own id (e.g. Input's `label`/`input` reusing its enclosing Field's
 * `label`/`control` ids) would silently lose that link if its id were instead regenerated from
 * its own component's default formula.
 *
 * Deliberately does not construct component instances itself - there is no registry mapping a
 * componentName to its constructor anywhere in this library (each primitive's own
 * `mountAll('field', callback)` call, with its own constructor, is defined per-consumer in their
 * own entry.ts). Call the relevant `mountAll()`s again after this - per `mountAll`'s own docblock,
 * that's exactly what it's for ("after lazily-inserted DOM adds new instances"). Returns the
 * distinct client component names found, so a caller knows which `mountAll()`s to re-run.
 *
 * Assumes the default `:` root-id separator (true for every field-family primitive - `field`,
 * `input`, `select`, `checkbox`, `number-input`, ... - the realistic contents of a `FieldArray`
 * row); a primitive that overrides its own `root` id format (only `scroll-area` does today, and
 * only for its own parts) isn't a fit for this helper.
 */
export function hydrateTemplateClone(root: Element, value: string): string[] {
    const clientComponentNames = new Set<string>();
    const remaps: RootIdRemap[] = [];

    root.querySelectorAll<HTMLElement>('[data-part="root"][data-scope][id]').forEach(el => {
        const clientComponentName = el.dataset.scope;
        if (!clientComponentName) return;

        const separatorIndex = el.id.indexOf(':');
        const stencilRootId = separatorIndex === -1 ? el.id : el.id.slice(separatorIndex + 1);

        remaps.push({
            clientComponentName,
            oldRootId: stencilRootId,
            newRootId: `${stencilRootId}:${value}`,
        });
    });

    // Restamp DOM ids for every nested root component found before rewriting any hydration props
    // below - a part's `ids` override (see restampIdReferences) may reference a *different*
    // component's own stencil id, so every mapping in the row needs to be known up front rather
    // than resolved one component at a time.
    restampIdReferences(root, remaps);

    for (const { clientComponentName, oldRootId: stencilRootId, newRootId } of remaps) {
        const stencilData = getHydrationData(clientComponentName, stencilRootId);
        if (!stencilData) continue;

        const rewrittenProps: ComponentHydrationData['props'] = {
            ...stencilData.props,
            id: newRootId,
            ids: remapIdsProp(stencilData.props.ids, remaps),
        };
        if (typeof rewrittenProps.name === 'string' && rewrittenProps.name.includes('[]')) {
            rewrittenProps.name = rewrittenProps.name.replace('[]', `[${value}]`);
        }

        if (!window.FluidPrimitives.hydrationData[clientComponentName]) {
            window.FluidPrimitives.hydrationData[clientComponentName] = {};
        }
        window.FluidPrimitives.hydrationData[clientComponentName][newRootId] = {
            ...stencilData,
            props: rewrittenProps,
        };

        clientComponentNames.add(clientComponentName);
    }

    return Array.from(clientComponentNames);
}

/**
 * Re-keys every nested, independent root component within `rowEl` (e.g. a `FieldArray` row's own
 * `Field`+`Input`) whose id ends in `:{oldValue}` to end in `:{newValue}` instead - both their DOM
 * ids (via {@see restampIdReferences}, which also fixes up any part whose id references a
 * *different* nested component's own id, e.g. Input's label/input reusing Field's) and, for ones
 * that are already live/mounted instances, their own `ComponentHydrator.rootId` and their entry in
 * `window.FluidPrimitives.uncontrolledInstances`/`hydrationData`, so their future re-renders keep
 * resolving their own elements correctly and later lookups (`getComponentInstance`,
 * `destroyComponentsWithin`) keep finding them under their new id.
 *
 * Complements `hydrateTemplateClone` (for a *newly cloned* row) - use this whenever a row's own
 * index changes on an *existing* row (e.g. after removing an earlier row shifts later ones down).
 * `Form.api.renameField`/`renameFieldMachineForForm` alone only updates a field's `name` prop for
 * submission purposes; without also renaming its id here, a nested component would keep the id it
 * was originally cloned with, and a later row cloned at that now-reused index would collide with
 * it - two different rows' `Field`s ending up with the identical `label`/`control`/`error` ids.
 *
 * A row rendered server-side (not cloned from a stencil) has no `:{value}`-suffixed nested ids to
 * begin with (its rootId has no colon in it at all), so this is a correct no-op for it - its ids
 * are already globally unique and were never positionally derived in the first place.
 */
export function renameNestedRootComponents(rowEl: Element, oldValue: string, newValue: string) {
    const remaps: RootIdRemap[] = [];

    rowEl.querySelectorAll<HTMLElement>('[data-part="root"][data-scope][id]').forEach(el => {
        const clientComponentName = el.dataset.scope;
        if (!clientComponentName) return;

        const separatorIndex = el.id.indexOf(':');
        const oldRootId = separatorIndex === -1 ? el.id : el.id.slice(separatorIndex + 1);

        const lastColonIndex = oldRootId.lastIndexOf(':');
        if (lastColonIndex === -1) return;

        const stencilRootId = oldRootId.slice(0, lastColonIndex);
        const suffix = oldRootId.slice(lastColonIndex + 1);
        if (suffix !== oldValue) return;

        remaps.push({ clientComponentName, oldRootId, newRootId: `${stencilRootId}:${newValue}` });
    });

    restampIdReferences(rowEl, remaps);

    for (const { clientComponentName, oldRootId, newRootId } of remaps) {
        const instances = window.FluidPrimitives?.uncontrolledInstances?.[clientComponentName];
        const instance = instances?.[oldRootId];
        if (instance) {
            delete instances[oldRootId];
            instances[newRootId] = instance;
            if (instance.hydrator) {
                instance.hydrator.rootId = newRootId;
            }
        }

        const hydrationInstances = window.FluidPrimitives?.hydrationData?.[clientComponentName];
        const stencilData = hydrationInstances?.[oldRootId];
        if (hydrationInstances && stencilData) {
            delete hydrationInstances[oldRootId];
            hydrationInstances[newRootId] = {
                ...stencilData,
                props: {
                    ...stencilData.props,
                    id: newRootId,
                    ids: remapIdsProp(stencilData.props.ids, remaps),
                },
            };
        }
    }
}

export function getListCollectionFromHydrationData<T extends CollectionItem>(hydrationCollection: {
    items: T[];
    itemToValueKey?: string;
    itemToStringKey?: string;
    isItemDisabledKey?: string;
    groupByKey?: string;
    groupSort?: 'asc' | 'desc' | Array<string>;
}): ListCollection<T> {
    if (hydrationCollection instanceof ListCollection) {
        return hydrationCollection;
    }

    const collection = new ListCollection<T>({
        items: hydrationCollection.items,
        itemToValue: hydrationCollection.itemToValueKey
            ? (item: any) => item?.[hydrationCollection.itemToValueKey!]
            : undefined,
        itemToString: hydrationCollection.itemToStringKey
            ? (item: any) => item?.[hydrationCollection.itemToStringKey!]
            : undefined,
        isItemDisabled: hydrationCollection.isItemDisabledKey
            ? (item: any) => item?.[hydrationCollection.isItemDisabledKey!]
            : undefined,
        groupBy: hydrationCollection.groupByKey
            ? (item: any) => {
                  const key = hydrationCollection.groupByKey;
                  if (!key) return undefined;

                  if (key.includes('.')) {
                      return key.split('.').reduce((obj, k) => (obj ? obj[k] : undefined), item);
                  }

                  return item?.[key];
              }
            : undefined,
        groupSort: hydrationCollection.groupSort,
    });
    return collection;
}
