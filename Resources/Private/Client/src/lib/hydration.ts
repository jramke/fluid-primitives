import { ListCollection, type CollectionItem } from '@zag-js/collection';
import type {
    ComponentHydrationData,
    FluidPrimitivesGlobals,
    HydrationPropsFor,
    KnownComponentName,
    NestedComponentEntry,
} from '../types';
import { applyClientPropConverters } from './client-prop-converters';
import { Component } from './component';

// A part's own name is lowerCamelCase (mirroring zag-js's own `ids` prop keys), but its attribute
// always renders lower-kebab. Keep in sync with: Classes/Utility/ComponentNameUtility.php's
// camelCaseToLowerCaseDashed().
export function toKebabCase(part: string): string {
    return part.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * Splits a required `"namespace:name"` string (e.g. `"ui:select"`) into its two parts - every
 * public lookup below (`getHydrationData`, `mountAll`, `mount`, `getComponentInstance`) requires
 * this form, with no bare-name fallback: two collections can legitimately define a same-named
 * component with a different shape (a styled wrapper around a primitive it doesn't expose every
 * prop of), so the caller always states which one it means.
 */
function parseNamespacedComponentName(componentName: string): {
    namespace: string;
    baseName: string;
} {
    const separatorIndex = componentName.indexOf(':');
    if (separatorIndex === -1) {
        throw new Error(
            `[fluid-primitives] "${componentName}" must include a namespace, e.g. "ui:${componentName}" - ` +
                'mountAll/mount/getHydrationData/getComponentInstance always require one.'
        );
    }
    return {
        namespace: componentName.slice(0, separatorIndex),
        baseName: componentName.slice(separatorIndex + 1),
    };
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

    const { namespace, baseName } = parseNamespacedComponentName(component);
    // Hydration data itself is always registered under the kebab-case clientBaseName (mirrors
    // PHP's HydrationRegistry) - converting here means every caller, direct or via
    // mount()/mountAll(), can consistently pass the same camelCase name used everywhere else
    // (static componentName, query() part names). A multi-segment name (e.g.
    // "molecules.checkboxGroup") walks one nested level per dot-segment, mirroring how PHP's
    // HydrationRegistry.add() builds the tree - toKebabCase() is dot-agnostic, so it can run on
    // the whole string before splitting.
    const clientBaseName = toKebabCase(baseName);
    let instances: unknown = hydrationData[namespace];
    for (const segment of clientBaseName.split('.')) {
        if (!instances || typeof instances !== 'object') {
            return null;
        }
        instances = (instances as Record<string, unknown>)[segment];
    }
    if (!instances) {
        return null;
    }

    if (!id) {
        return instances;
    }

    return (instances as Record<string, unknown>)[id] || null;
}

/**
 * Root components PHP found nested inside the tracked scope `scopeId` (a `ui:template` stencil's
 * own id, or a FieldArray row's own id) - see `HydrationRegistry::recordNestedComponent()`'s own
 * docblock for why this is authoritative, PHP-recorded data rather than something inferred from
 * scanning rendered DOM shape (which can't find a component that renders no DOM element of its own,
 * e.g. `Dialog`'s `Root`, or whose content has since portaled elsewhere).
 */
function getNestedComponents(scopeId: string): NestedComponentEntry[] {
    return window.FluidPrimitives?.nestedComponents?.[scopeId] ?? [];
}

/**
 * Looks up another already-mounted component instance (from either `mountAll` or `mount`) by its
 * namespaced component name and hydration id (the `id` prop it was rendered with). For primitives
 * that compose two independent instances of themselves at runtime (e.g. Menu submenus linking a
 * parent/child pair via their own `id`s) rather than through props alone.
 */
export function getComponentInstance<
    T extends Component<unknown, unknown> = Component<unknown, unknown>,
>(componentName: string, id: string): T | undefined {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    return window.FluidPrimitives?.componentInstances?.[namespace]?.[toKebabCase(baseName)]?.[
        id
    ] as T | undefined;
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
 * Mounts every not-yet-mounted, uncontrolled hydration instance of `componentName` (a required
 * `"namespace:name"` string, e.g. `"ui:select"`). Safe to call more than once (e.g. after
 * lazily-inserted DOM adds new instances) - already mounted instances are skipped rather than
 * re-instantiated.
 *
 * `props` in the callback is inferred from `componentName` itself via {@see HydrationPropsFor} -
 * no explicit generic needed at the call site. A component name a project hasn't generated types
 * for yet (or ever) falls back to the untyped bag, same as today.
 */
export function mountAll<K extends KnownComponentName | (string & {})>(
    componentName: K,
    callback: (data: {
        controlled: boolean;
        props: HydrationPropsFor<Extract<K, string>>;
        createHydrator: () => ComponentHydrator;
    }) => Component<unknown, unknown> | void
) {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const hydrationInstances = getHydrationData(componentName);
    if (!hydrationInstances) return;

    const clientBaseName = toKebabCase(baseName);
    window.FluidPrimitives.componentInstances[namespace] ??= {};
    window.FluidPrimitives.componentInstances[namespace][clientBaseName] ??= {};
    const mountedInstances = window.FluidPrimitives.componentInstances[namespace][clientBaseName];

    Object.keys(hydrationInstances).forEach(id => {
        if (hydrationInstances[id].controlled) return;
        if (mountedInstances[id]) return;

        const instance = callback({
            ...hydrationInstances[id],
            props: applyClientPropConverters(
                baseName,
                hydrationInstances[id].props
            ) as HydrationPropsFor<Extract<K, string>>,
            createHydrator: () => new ComponentHydrator(baseName, id),
        });
        if (!instance) return;

        instance.namespace = namespace;
        mountedInstances[id] = instance;
    });
}

/**
 * Destroys every mounted component instance (from either `mountAll` or `mount`) whose root element
 * is `root` itself or a descendant of it, and drops them from the tracked instance registry.
 * Intended for cleaning up before removing a subtree from the DOM (e.g. a lazily-mounted recurring-
 * field row).
 *
 * Matches only on the instance's own `root` part, not on any of its other parts - a `FieldArray`'s
 * own `removeTrigger`/`item` parts also live inside the row that is merely being removed, and
 * matching those would destroy the very component whose row it is, rather than only what's actually
 * nested inside that row. A component with no `root` part (e.g. `Dialog`) is never matched.
 */
export function destroyComponentsWithin(root: Element | Document) {
    if (!window.FluidPrimitives) return;

    for (const namespaceBucket of Object.values(window.FluidPrimitives.componentInstances)) {
        for (const instances of Object.values(namespaceBucket)) {
            for (const [id, instance] of Object.entries(instances)) {
                const rootPartSelector = instance.hydrator?.selector('root');
                if (!rootPartSelector) continue;

                const isRootItself = root instanceof Element && root.matches(rootPartSelector);
                const hasRootPartInNode = root.querySelector(rootPartSelector) !== null;

                if (isRootItself || hasRootPartInNode) {
                    console.log(`Destroying component instance: ${instance.getClientName()}:${id}`);
                    instance.destroy();
                    delete instances[id];
                }
            }
        }
    }
}

/**
 * Gets one specific hydration instance of `componentName` (a required `"namespace:name"` string)
 * by its `rootId` and hands it to `callback`, regardless of whether it was rendered with
 * `controlled="{true}"`. Unlike {@see mountAll}, calling it twice for the same `rootId` re-invokes
 * `callback` and constructs a new instance each time - but the resulting instance is still tracked
 * (overwriting whichever one a previous call tracked), so {@see getComponentInstance} and
 * {@see destroyComponentsWithin} can find it, the same way a `mountAll`-created instance can.
 *
 * `props` in the callback is inferred from `componentName` the same way {@see mountAll}'s is.
 */
export function mount<
    K extends KnownComponentName | (string & {}),
    T extends Component<unknown, unknown>,
>(
    componentName: K,
    rootId: string,
    callback: (data: {
        controlled: boolean;
        props: HydrationPropsFor<Extract<K, string>>;
        createHydrator: () => ComponentHydrator;
    }) => T | void
): T | undefined {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const hydrationData = getHydrationData(componentName, rootId);
    if (!hydrationData) return undefined;

    const instance = callback({
        ...hydrationData,
        props: applyClientPropConverters(baseName, hydrationData.props) as HydrationPropsFor<
            Extract<K, string>
        >,
        createHydrator: () => new ComponentHydrator(baseName, rootId),
    });
    if (!instance) return undefined;

    instance.namespace = namespace;
    const clientBaseName = toKebabCase(baseName);
    window.FluidPrimitives.componentInstances[namespace] ??= {};
    window.FluidPrimitives.componentInstances[namespace][clientBaseName] ??= {};
    window.FluidPrimitives.componentInstances[namespace][clientBaseName][rootId] = instance;

    return instance;
}

/**
 * Locates one component instance's parts. Every part `ui:ref` renders carries a single
 * `data-<component>-<part>="<rootId>"` attribute (zag's own part convention), so a lookup never
 * depends on an id and is document-wide by default - which is also what finds content that was
 * portaled away from the component's root.
 */
export class ComponentHydrator {
    componentName: string;
    // Kebab form of componentName - what appears in the part attribute names and hydration keys.
    // Derived once here so nothing downstream converts repeatedly.
    clientComponentName: string;
    doc: Document;
    rootId: string;

    constructor(componentName: string, rootId: string | undefined, doc: Document = document) {
        this.componentName = componentName;
        this.clientComponentName = toKebabCase(componentName);
        this.doc = doc;
        if (!rootId) {
            throw new Error(`Root ID is required for component hydration: ${componentName}`);
        }
        this.rootId = rootId;
    }

    private get attrPrefix(): string {
        return `data-${this.clientComponentName.replaceAll('.', '-')}-`;
    }

    // Keep in sync with: Classes/Utility/ComponentRefUtility.php getAttributeName()
    attr(part: string): string {
        return this.attrPrefix + toKebabCase(part);
    }

    selector(part: string): string {
        return `[${this.attr(part)}="${this.rootId}"]`;
    }

    // Keep in sync with: Classes/Utility/ComponentRefUtility.php getScopeKey()
    scopeKey(part: string, value?: string): string {
        const key = `${this.clientComponentName}:${this.rootId}:${toKebabCase(part)}`;
        return value ? `${key}:${value}` : key;
    }

    query<T extends Element>(part: string, parent: Element | Document = this.doc): T | null {
        return parent.querySelector<T>(this.selector(part));
    }

    queryAll<T extends Element>(part: string, parent: Element | Document = this.doc): T[] {
        return Array.from(parent.querySelectorAll<T>(this.selector(part)));
    }

    /** Client-side counterpart of `ui:ref`, for elements created after hydration. */
    stamp(element: Element, part: string, value?: string): void {
        element.setAttribute(this.attr(part), this.rootId);
        if (value !== undefined) {
            element.setAttribute('data-value', value);
        }
    }

    /** The part this component's own scope marks `el` as (kebab-case), if any. */
    private getOwnPart(el: Element): string | null {
        for (const { name, value } of el.attributes) {
            if (value === this.rootId && name.startsWith(this.attrPrefix)) {
                return name.slice(this.attrPrefix.length);
            }
        }
        return null;
    }

    /**
     * Sets `data-value` on every element within `root` (root included) that is a part of *this*
     * component's own scope, for a new, real `value` - the shared half of
     * {@see restampValue}/{@see renameValue}, which differ only in what they each then do about any
     * nested, independent root component `root` happens to compose (a fresh copy vs. an in-place
     * rename - see `restampValue`'s own docblock for why the two are never auto-detected from one
     * another).
     *
     * `attributes`, if given, are additionally set on the same elements `value` is applied to - for
     * primitives whose `render()` reads plain per-element data attributes rather than resolving a
     * collection item (e.g. RadioGroup's `disabled`/`invalid`, NavigationMenu's `Link` `current`).
     * A boolean value toggles a bare `data-x` attribute (e.g. `{ disabled: true }` -> `data-disabled`,
     * present only when true); a string value sets `data-x="value"` directly.
     */
    private restampOwnScope(
        root: Element,
        value: string,
        attributes?: Record<string, boolean | string>
    ): void {
        const restamp = (el: Element) => {
            if (this.getOwnPart(el) === null) return;
            el.setAttribute('data-value', value);
            for (const [name, attributeValue] of Object.entries(attributes ?? {})) {
                if (typeof attributeValue === 'string') {
                    el.setAttribute(`data-${name}`, attributeValue);
                } else {
                    el.toggleAttribute(`data-${name}`, attributeValue);
                }
            }
        };

        restamp(root);
        root.querySelectorAll('*').forEach(restamp);
    }

    /**
     * Re-stamps `root` for a new, real `value` (via {@see restampOwnScope}) and prepares any
     * nested, *independent* root component found inside it (e.g. a `Field`+`Input` composed inside
     * a `FieldArray` row, or any other primitive that composes another one inside its own per-item
     * markup) as a brand-new instance copied from its own pristine stencil data, via
     * {@see prepareNestedRootComponents}. Use after cloning a `<template>` (see `Template`) to make
     * the clone represent one real item/row in a single call, instead of manually setting
     * `data-value` on the root and separately on every nested value-scoped part (e.g. a combobox
     * item's own `item-text`/`item-indicator`) *and* separately preparing whatever independent
     * components that item happens to compose. Fully generic - not
     * tied to any one primitive - so a `<template>` item "just works" regardless of what it
     * contains, without the calling primitive needing to know or handle that itself.
     *
     * Only for a *freshly cloned* item - one that has never been through this or
     * {@see renameValue} before (a `<template>`'s content is never mutated by a previous clone, so
     * this is always true right after `content.cloneNode(true)`). For an *existing* item pulled
     * from the live DOM whose value is merely changing (e.g. `FieldArray` reindexing a later row
     * down by one after an earlier row was removed), call {@see renameValue} instead.
     *
     * The two used to be auto-detected from a nested component's own *current* id shape (no
     * `:value` suffix yet vs. already has one) rather than asked for explicitly - but that shape
     * can't actually tell a never-touched, already-live server-rendered row apart from a
     * never-touched template clone; both look identical. That silently mis-classified an
     * already-mounted row as "fresh" the first time an earlier row's removal caused it to be
     * reindexed: its DOM references moved out from under it (via `restampIdReferences`, which both
     * paths run) without its `ComponentHydrator.rootId`/`componentInstances` entry following along -
     * only {@see renameValue}'s path updates those - leaving its already-mounted `Field`/`Input`
     * instances silently unable to find their own elements again. The caller already knows
     * unambiguously which case it's in (a fresh `Template` clone vs. an element already in the
     * document), so it now says so by calling the matching method instead of leaving it to a guess.
     *
     * `stencilKey` is the stencil's own {@see scopeKey} (see `Template`'s constructor, its only
     * caller) - what nested root components `root` composes is looked up from
     * `window.FluidPrimitives.nestedComponents[stencilKey]` (PHP-recorded, see
     * `HydrationRegistry::recordNestedComponent()`), not found by scanning `root` itself. That's
     * what lets this find a nested component regardless of what DOM shape it renders - including
     * one that renders no identifiable wrapper of its own at all (e.g. `Dialog`'s `Root`, which is
     * just `<f:slot />` - see `ui:portal`'s own `isRenderStencil` handling for the other half of
     * making a portalled nested component work inside a stencil).
     *
     * Returns the distinct client component names of any nested root components found and
     * prepared (empty when `root` contains none, the common case - e.g. `FileUpload`'s own item
     * previews) - call the matching `mountAll()`s for each of these after inserting a freshly
     * cloned item into the document, per `mountAll`'s own docblock ("after lazily-inserted DOM
     * adds new instances").
     */
    restampValue(
        root: Element,
        value: string,
        stencilKey: string,
        attributes?: Record<string, boolean | string>
    ): string[] {
        this.restampOwnScope(root, value, attributes);
        const ownPart = this.getOwnPart(root);
        return prepareNestedRootComponents(
            stencilKey,
            root,
            value,
            ownPart ? this.scopeKey(ownPart, value) : null
        );
    }

    /**
     * Re-stamps `root` for a new, real `value` (via {@see restampOwnScope}) and re-keys every
     * nested, independent root component found inside it (e.g. a `FieldArray` row's own
     * `Field`+`Input`) to reference `value` instead of whatever position it previously held - both
     * their DOM references (via {@see restampIdReferences}) and, for ones that are already live/mounted,
     * their own `ComponentHydrator.rootId` and their entry in
     * `window.FluidPrimitives.componentInstances`/`hydrationData` (via
     * {@see renameNestedComponentEntry}), so their future re-renders keep resolving their own
     * elements and later lookups (`getComponentInstance`, `destroyComponentsWithin`) keep finding
     * them under their new id.
     *
     * For an *existing* item pulled from the live DOM whose position is merely shifting - never for
     * a freshly cloned `<template>` item, which has no existing instance/hydration-data entry to
     * re-key in the first place; use {@see restampValue} for that. See that method's own docblock
     * for why the two are never auto-detected from one another.
     *
     * Looks up what `root` composes from `window.FluidPrimitives.nestedComponents[<scopeKey>]`,
     * keyed by `root`'s own part and `data-value` *before* this call restamps it, which is why
     * that's read first, before {@see restampOwnScope} changes it. That's a correct lookup key both
     * for a row rendered for real (PHP records it there too - see `ComponentHydrationCollector`'s
     * FieldArray-ambient-context check) and for one `restampValue` prepared earlier (which migrates
     * the same entry to the row's new key as its own last step, for exactly this reason).
     *
     * Restamps id *references* (via {@see restampIdReferences}) across the whole document, not just
     * `root`'s own subtree - unlike {@see restampValue}, where a fresh clone can't yet have anything
     * portaled out of it (nothing composed by it has mounted/opened yet), `root` here is an existing,
     * already-live item, so a nested component it composes may already have portaled its own content
     * elsewhere in the document (e.g. an open `Popover`'s positioner, rendered via `ui:portal` into
     * the page footer) by the time this runs. Scoping to `root` alone would restamp that component's
     * trigger in place but silently miss its portaled content, leaving it referencing a now-stale id.
     */
    renameValue(root: Element, value: string): void {
        const ownPart = this.getOwnPart(root);
        const previousScopeKey = ownPart
            ? this.scopeKey(ownPart, root.getAttribute('data-value') ?? undefined)
            : null;
        this.restampOwnScope(root, value);
        if (!ownPart || !previousScopeKey) return;

        const remaps = nestedRootRemapsFromMetadata(previousScopeKey, value);
        if (remaps.length === 0) return;

        restampIdReferences(this.doc, remaps);

        for (const { componentName, oldRootId, newRootId } of remaps) {
            renameNestedComponentEntry(componentName, oldRootId, newRootId, remaps);
        }

        migrateNestedComponentsScope(previousScopeKey, this.scopeKey(ownPart, value), remaps);
    }

    destroy() {
        // No-op for now; if we ever need to clean up anything, do it here.
    }
}

interface RootIdRemap {
    /** The nested component's own namespaced hydration key (e.g. "primitives:field") - the same
     * opaque form `NestedComponentEntry.name` carries, matching what `getHydrationData`/`mountAll`
     * themselves require. */
    componentName: string;
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
 * Rewrites, on every element in `root` (root itself included), whatever references one of
 * `remaps`' `oldRootId` so it references the matching `newRootId` instead: every part attribute of
 * that nested component (`data-<nested>-<part>="oldRootId"`), and any real `id` that embeds it (a
 * part given an explicit id through `ids`, see `ui:ref`). The id is rewritten via plain string
 * substitution, not recomputed from a formula - an Input's `label`/`input` parts are server-stamped
 * with their enclosing Field's own `label`/`control` ids (see `FieldIdMapping`), which an id
 * derived from Input's own root id would silently lose. Scoped to `root` (the whole cloned item,
 * not just one nested component's own subtree) since an override can reference a sibling
 * component's id, not just an ancestor's - or, from {@see ComponentHydrator.renameValue}, to the
 * whole document, when a nested component's content may already live outside `root` entirely (see
 * that method's own docblock).
 */
function restampIdReferences(root: Element | Document, remaps: RootIdRemap[]): void {
    const restamp = (el: Element) => {
        for (const { name, value } of Array.from(el.attributes)) {
            if (name === 'id') {
                const match = remaps.find(({ oldRootId }) => referencesRootId(value, oldRootId));
                if (match) el.setAttribute(name, value.replace(match.oldRootId, match.newRootId));
            } else if (name.startsWith('data-')) {
                const match = remaps.find(({ oldRootId }) => oldRootId === value);
                if (match) el.setAttribute(name, match.newRootId);
            }
        }
    };

    if (root instanceof Element) restamp(root);
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
        const match = remaps.find(({ oldRootId }) => referencesRootId(value, oldRootId));
        remapped[key] = match ? value.replace(match.oldRootId, match.newRootId) : value;
    }
    return remapped;
}

/**
 * Copies a stencil's cached hydration props to a freshly derived id, for a nested root component
 * found in a freshly cloned `<template>` item (see {@see prepareNestedRootComponents}). Deep-clones
 * the stencil's own props
 * (registered once at server-render time, when that one stencil instance rendered) rather than
 * moving them, since the stencil's own entry must stay put for the *next* clone to copy from too -
 * unlike {@see renameNestedComponentEntry}, which re-keys a single existing entry in place.
 *
 * Rewrites a `[]` "append" placeholder in any string `name` prop to `value` (the same trailing-
 * `[]`-means-append convention `form.path.ts`'s `appendFieldPathSegment`/`FieldContext.php` use).
 * Returns whether a stencil entry was actually found and copied, so a caller only counts this
 * component name as "found" when there was real hydration data to prepare.
 */
function copyNestedComponentEntry(
    componentName: string,
    stencilRootId: string,
    newRootId: string,
    remaps: RootIdRemap[],
    value: string
): boolean {
    const stencilData = getHydrationData(componentName, stencilRootId);
    if (!stencilData) return false;

    const rewrittenProps: ComponentHydrationData['props'] = {
        ...stencilData.props,
        id: newRootId,
        ids: remapIdsProp(stencilData.props.ids, remaps),
    };
    if (typeof rewrittenProps.name === 'string' && rewrittenProps.name.includes('[]')) {
        rewrittenProps.name = rewrittenProps.name.replace('[]', `[${value}]`);
    }

    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const clientBaseName = toKebabCase(baseName);
    window.FluidPrimitives.hydrationData[namespace] ??= {};
    window.FluidPrimitives.hydrationData[namespace][clientBaseName] ??= {};
    window.FluidPrimitives.hydrationData[namespace][clientBaseName][newRootId] = {
        ...stencilData,
        props: rewrittenProps,
    };
    return true;
}

/**
 * Re-keys a single nested root component's own entry from `oldRootId` to `newRootId` in place -
 * for an existing item pulled from the live DOM (see {@see ComponentHydrator.renameValue}), as
 * opposed to a fresh one still in its pristine stencil state (see
 * {@see copyNestedComponentEntry}). Updates both its `window.FluidPrimitives.
 * hydrationData` entry and, for one that's already live/mounted, its own `ComponentHydrator.
 * rootId` and its entry in `window.FluidPrimitives.componentInstances`, so its future
 * re-renders keep resolving their own elements correctly and later lookups
 * (`getComponentInstance`, `destroyComponentsWithin`) keep finding it under its new id.
 */
function renameNestedComponentEntry(
    componentName: string,
    oldRootId: string,
    newRootId: string,
    remaps: RootIdRemap[]
): void {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const clientBaseName = toKebabCase(baseName);

    const instances = window.FluidPrimitives?.componentInstances?.[namespace]?.[clientBaseName];
    const instance = instances?.[oldRootId];
    if (instance) {
        delete instances[oldRootId];
        instances[newRootId] = instance;
        if (instance.hydrator) {
            instance.hydrator.rootId = newRootId;
        }
    }

    const hydrationInstances = window.FluidPrimitives?.hydrationData?.[namespace]?.[clientBaseName];
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

/**
 * Builds the `value`-suffixed id each root component PHP recorded as nested inside `scopeKey`
 * (see {@see getNestedComponents}) should move to - the metadata itself, not this formula, is what
 * tells the two apart from any *other* root component on the page.
 *
 * Recovers each one's stable, stencil-rooted prefix from its *current* recorded id first -
 * stripping back to before the last `:` segment, if it has one - so a component already renamed
 * once (`x:0` -> `x:1`) has that suffix *replaced* rather than accumulated (`x:0:1`). A component
 * that's never been touched at all (fresh from either a never-rendered-before-now server row or an
 * untouched `<template>` stencil) has no `:` in its rootId to strip, so this is a correct no-op for
 * it - its whole current id already is the stable prefix.
 *
 * Shared by both {@see prepareNestedRootComponents} (fresh `<template>` clones) and
 * {@see ComponentHydrator.renameValue} (existing items whose position is shifting) - the remap
 * shape itself doesn't differ between the two; only what each one does with it afterward (copy a
 * stencil's props to the new id vs. re-key an existing entry in place) does.
 */
function nestedRootRemapsFromMetadata(scopeKey: string, value: string): RootIdRemap[] {
    return getNestedComponents(scopeKey).map(({ name, id: currentRootId }) => {
        const lastColonIndex = currentRootId.lastIndexOf(':');
        const stencilRootId =
            lastColonIndex === -1 ? currentRootId : currentRootId.slice(0, lastColonIndex);

        return {
            componentName: name,
            oldRootId: currentRootId,
            newRootId: `${stencilRootId}:${value}`,
        };
    });
}

/**
 * Records a copy of `window.FluidPrimitives.nestedComponents[oldScopeKey]` (if present) under
 * `newScopeKey`, rewriting each entry's own `id` through the same `remaps`
 * {@see nestedRootRemapsFromMetadata} just computed from it - so a *later*
 * {@see ComponentHydrator.renameValue} on this same item (a second reindex) still finds it under
 * the id the item actually has by then, rather than the one it was first recorded under. Leaves
 * `oldScopeKey` in place: for a fresh clone that is the stencil, which the *next* clone needs too.
 * Returns whether there was anything to copy.
 */
function copyNestedComponentsScope(
    oldScopeKey: string,
    newScopeKey: string,
    remaps: RootIdRemap[]
): boolean {
    const entries = window.FluidPrimitives?.nestedComponents?.[oldScopeKey];
    if (!entries || oldScopeKey === newScopeKey) return false;

    window.FluidPrimitives.nestedComponents ??= {};
    window.FluidPrimitives.nestedComponents[newScopeKey] = entries.map(entry => {
        const match = remaps.find(
            remap => remap.componentName === entry.name && remap.oldRootId === entry.id
        );
        return match ? { name: entry.name, id: match.newRootId } : entry;
    });
    return true;
}

/**
 * {@see copyNestedComponentsScope}, then drops `oldScopeKey` - for an existing item whose own key
 * is changing (see {@see ComponentHydrator.renameValue}), as opposed to a fresh clone.
 */
function migrateNestedComponentsScope(
    oldScopeKey: string,
    newScopeKey: string,
    remaps: RootIdRemap[]
): void {
    if (copyNestedComponentsScope(oldScopeKey, newScopeKey, remaps)) {
        delete window.FluidPrimitives.nestedComponents?.[oldScopeKey];
    }
}

/**
 * Prepares every nested, independent root component inside a freshly cloned `<template>` item for
 * `value` - the fresh-clone half of {@see ComponentHydrator.restampValue}, which this backs. Every
 * element that *references* one of those root ids - not just the root element itself - is
 * re-stamped too, via {@see restampIdReferences} (which also fixes up any part whose id references
 * a *different* nested component's own id, e.g. Input's label/input reusing Field's), plus the
 * same substitution applied to each one's own `ids` prop override map via {@see remapIdsProp}.
 * Without this, a nested component's parts would keep the root id of the stencil they were cloned
 * from and stay attached to it, since they're outside `restampValue`'s own scope (which only
 * covers the *outer* component's own parts).
 *
 * Copies each one's stencil hydration data (registered once at server-render time, when that one
 * stencil instance rendered) to the new id via {@see copyNestedComponentEntry}, so a subsequent
 * `mountAll()` can construct it. Deliberately does not construct component instances itself -
 * there is no registry mapping a componentName to its constructor anywhere in this library (each
 * primitive's own `mountAll('field', callback)` call, with its own constructor, is defined
 * per-consumer in their own entry.ts) - see {@see ComponentHydrator.restampValue}'s own docblock
 * for what to do with the client component names this returns instead.
 */
function prepareNestedRootComponents(
    stencilKey: string,
    root: Element,
    value: string,
    newScopeKey: string | null
): string[] {
    const remaps = nestedRootRemapsFromMetadata(stencilKey, value);
    if (remaps.length === 0) return [];

    // Restamp DOM references for every nested root component found before rewriting any hydration
    // props/instance state below - a part's `ids` override (see restampIdReferences) may
    // reference a *different* nested component's own id, so every mapping in the item needs to be
    // known up front rather than resolved one component at a time.
    restampIdReferences(root, remaps);

    const componentNames = new Set<string>();

    for (const { componentName, oldRootId, newRootId } of remaps) {
        if (copyNestedComponentEntry(componentName, oldRootId, newRootId, remaps, value)) {
            componentNames.add(componentName);
        }
    }

    // `newScopeKey` (the clone root's own part, keyed by `value`) is where this row's own entry is
    // recorded - meaningful for a FieldArray row (renameValue's own later lookup depends on it); for
    // FileUpload's own item clones nothing ever reads this key back, so it's harmless, unused
    // bookkeeping. The stencil's own entry stays put for the next clone.
    if (newScopeKey) {
        copyNestedComponentsScope(stencilKey, newScopeKey, remaps);
    }

    return Array.from(componentNames);
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
