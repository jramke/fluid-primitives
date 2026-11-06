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
import { devWarn, isDebug } from './debug';
import { removePortalsWithin } from './portal';

/**
 * @internal
 *
 * A part's own name is lowerCamelCase (mirroring zag-js's own `ids` prop keys), but its attribute
 * always renders lower-kebab. Keep in sync with: Classes/Utility/ComponentNameUtility.php's
 * camelCaseToLowerCaseDashed().
 */
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

const HYDRATION_DATA_SELECTOR =
    'script[type="application/json"][data-fluid-primitives-hydration-data]';

/**
 * @internal
 *
 * The hydration data, globals and mounted instances of the page. The server writes the data as one JSON block
 * per rendering pass: a page from the page cache has one of its cached content and one of the uncached content
 * (USER_INT) of the request. The first call reads them into `window.FluidPrimitives` and removes them from
 * the page, later calls return it.
 */
export function getFluidPrimitives(): Window['FluidPrimitives'] | undefined {
    return (window.FluidPrimitives ??= readHydrationDataBlocks()!);
}

function readHydrationDataBlocks(): Window['FluidPrimitives'] | undefined {
    const blocks = document.querySelectorAll<HTMLScriptElement>(HYDRATION_DATA_SELECTOR);
    if (blocks.length === 0) return undefined;

    const fluidPrimitives = { ...emptyFluidPrimitives(), globals: {} as FluidPrimitivesGlobals };
    mergeHydrationDataBlocks(fluidPrimitives, blocks);
    return fluidPrimitives;
}

function emptyFluidPrimitives(): Window['FluidPrimitives'] {
    return {
        componentInstances: {},
        hydrationData: Object.create(null),
        nestedComponents: {},
    };
}

// A block is removed once read: the next one Turbo merges into the head does not pile up next to it,
// and every block left in the page is one that was not read yet.
function mergeHydrationDataBlocks(
    target: Window['FluidPrimitives'],
    blocks: Iterable<HTMLScriptElement>
) {
    for (const block of blocks) {
        // removed first, so a block that does not parse is not read again with every later change
        block.remove();
        let data;
        try {
            data = JSON.parse(block.textContent || '{}');
        } catch (error) {
            console.error('[fluid-primitives] A hydration data block is no valid JSON.', error);
            continue;
        }
        if (data.globals) target.globals = { ...target.globals, ...data.globals };
        addHydrationEntries(target.hydrationData, data.hydrationData ?? {});
        target.nestedComponents = { ...target.nestedComponents, ...data.nestedComponents };
    }
}

/**
 * Reads the blocks that arrived after the first read, e.g. with HTML that Turbo swapped in.
 *
 * @returns Whether there was one.
 */
function readNewHydrationDataBlocks(): boolean {
    const blocks = document.querySelectorAll<HTMLScriptElement>(HYDRATION_DATA_SELECTOR);
    if (blocks.length === 0) return false;

    mergeHydrationDataBlocks((window.FluidPrimitives ??= emptyFluidPrimitives()), blocks);
    return true;
}

// Walks down the namespace and the segments of a component name (one level per dot, as in getHydrationData())
// to the entries, and files each under its rootId: one component can have entries in both blocks. The levels have
// no prototype, so a component or rootId named like an Object.prototype member ("constructor") is a key like any other.
function addHydrationEntries(target: Record<string, any>, source: Record<string, any>) {
    for (const [key, value] of Object.entries(source)) {
        // a level can hold a rootId named autoMount, but there its value is an entry, not a boolean
        if (typeof value.autoMount === 'boolean') target[key] = value;
        else addHydrationEntries((target[key] ??= Object.create(null)), value);
    }
}

/** @internal */
export function getHydrationData(component: string): Record<string, ComponentHydrationData> | null;
export function getHydrationData(component: string, id: string): ComponentHydrationData | null;
export function getHydrationData(component?: string, id?: string) {
    const hydrationData = getFluidPrimitives()?.hydrationData;

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

    // own keys only: a rootId such as "constructor" would read Object.prototype
    return Object.hasOwn(instances, id) ? (instances as Record<string, unknown>)[id] : null;
}

/**
 * Root components PHP found nested inside the tracked scope `scopeId` (a `ui:template` stencil's
 * own id, or a FieldArray row's own id) - see `HydrationRegistry::recordNestedComponent()`'s own
 * docblock for why this is authoritative, PHP-recorded data rather than something inferred from
 * scanning rendered DOM shape (which can't find a component that renders no DOM element of its own,
 * e.g. `Dialog`'s `Root`, or whose content has since portaled elsewhere).
 */
function getNestedComponents(scopeId: string): NestedComponentEntry[] {
    return getFluidPrimitives()?.nestedComponents?.[scopeId] ?? [];
}

/**
 * Finds a component instance that is already mounted, by the namespaced name it was mounted with and
 * its hydration id (the `id` prop it was rendered with). Works for instances from `mountAll()` and
 * `mount()` alike. It is for primitives that compose two independent instances of themselves at
 * runtime, e.g. the submenus of a Menu.
 *
 * @param componentName - The namespaced name, e.g. `ui:menu`.
 * @param id - The hydration id of the instance.
 * @returns The instance, or `undefined` if none is mounted under that id.
 */
export function getComponentInstance<
    T extends Component<unknown, unknown> = Component<unknown, unknown>,
>(componentName: string, id: string): T | undefined {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const registry = getFluidPrimitives()?.componentInstances;
    return ownValue(ownValue(ownValue(registry, namespace), toKebabCase(baseName)), id) as
        T | undefined;
}

// The instance registry reads own keys only and its buckets have no prototype, so a component or
// rootId named like an Object.prototype member ("constructor", "__proto__") is a key like any other.
function ownValue<T>(record: Record<string, T> | undefined, key: string): T | undefined {
    return record && Object.hasOwn(record, key) ? record[key] : undefined;
}

function instancesOf(namespace: string, clientBaseName: string) {
    const registry = window.FluidPrimitives.componentInstances;
    registry[namespace] = ownValue(registry, namespace) ?? Object.create(null);
    registry[namespace][clientBaseName] =
        ownValue(registry[namespace], clientBaseName) ?? Object.create(null);
    return registry[namespace][clientBaseName];
}

/**
 * The request-wide values the server rendered into the page: `locale`, `debug` and whatever the
 * project's global context exposes with `#[ExposeToClient]`.
 *
 * @returns The globals, or `null` when the page has none: the server only writes hydration data when a
 * component hydrates or a global was read while rendering.
 */
export function getGlobals(): FluidPrimitivesGlobals | null {
    const globals = getFluidPrimitives()?.globals;

    if (!globals || typeof globals !== 'object') {
        return null;
    }

    return globals;
}

/**
 * Reads one global by its dot path, e.g. `getGlobal('lightbox.icons')`. A key that literally contains a
 * dot wins over the nested lookup. Walks objects and arrays and never throws: a missing segment, or a
 * page without globals, gives `fallback` (or `undefined` without one).
 *
 * @param path - The name of the global, with `.` between nesting levels.
 * @param fallback - Returned when the global does not exist.
 */
export function getGlobal<T = unknown>(path: string): T | undefined;
export function getGlobal<T>(path: string, fallback: T): T;
export function getGlobal<T>(path: string, fallback?: T): T | undefined {
    const globals = getGlobals();
    if (!globals) {
        return fallback;
    }

    if (Object.hasOwn(globals, path)) {
        return globals[path] as T;
    }

    let current: unknown = globals;
    for (const segment of path.split('.')) {
        if (current === null || typeof current !== 'object' || !Object.hasOwn(current, segment)) {
            return fallback;
        }
        current = (current as Record<string, unknown>)[segment];
    }

    return current as T;
}

/**
 * Mounts every instance of a component that is not mounted yet and was not rendered with
 * `autoMount="{false}"`, and keeps doing so for markup that is added to the page later: a row of a
 * FieldArray, a clone of a `Template`, HTML that Turbo or a `fetch()` swapped in. An instance is
 * destroyed once every element it rendered onto has left the page, and mounts again when its markup
 * comes back. A callback that returns no instance runs once per hydration entry. Calling `mountAll()`
 * again replaces the callback and skips what is mounted.
 *
 * The `props` in the callback are typed from `componentName` via `HydrationPropsFor`. A component
 * without generated types gets an untyped bag.
 *
 * @param componentName - The namespaced name, e.g. `ui:select`.
 * @param callback - Creates the instance of one hydration entry and returns it.
 *
 * @example
 * ```typescript
 * mountAll('ui:accordion', ({ props }) => new Accordion(props).init());
 * ```
 */
export function mountAll<K extends KnownComponentName | (string & {})>(
    componentName: K,
    callback: (data: {
        autoMount: boolean;
        props: HydrationPropsFor<Extract<K, string>>;
        createHydrator: () => ComponentHydrator;
    }) => Component<unknown, unknown> | void
) {
    parseNamespacedComponentName(componentName);
    const fluidPrimitives = (window.FluidPrimitives =
        getFluidPrimitives() ?? emptyFluidPrimitives());
    fluidPrimitives.mounters = { ...fluidPrimitives.mounters, [componentName]: callback };
    observePage();

    mountEntries(componentName, callback);
}

/**
 * Mounts the components inside `element` right away, with the callbacks of `mountAll()`, and reads
 * the hydration data blocks that arrived with them. `mountAll()` mounts them anyway, a microtask
 * after the markup arrived, so call it only when you need the instances on your next line, e.g.
 * right after you insert the clone of a `Template`.
 *
 * @param element - An element in the page that holds the new markup.
 */
export function mountWithin(element: Element) {
    readNewHydrationDataBlocks();
    mountParts(partAttributesByRootId([element]));
}

type PartAttributes = Map<string, string[]>;

/** The `data-*` attribute names of `elements` and everything inside them, by attribute value. */
function partAttributesByRootId(elements: Iterable<Element>): PartAttributes {
    const parts: PartAttributes = new Map();
    for (const element of elements) {
        for (const el of [element, ...element.querySelectorAll('*')]) {
            for (const { name, value } of el.attributes) {
                if (!name.startsWith('data-')) continue;
                const names = parts.get(value) ?? [];
                names.push(name);
                parts.set(value, names);
            }
        }
    }
    return parts;
}

function mountParts(parts: PartAttributes) {
    if (parts.size === 0) return;
    for (const [componentName, callback] of Object.entries(
        window.FluidPrimitives?.mounters ?? {}
    )) {
        mountEntries(componentName, callback, parts);
    }
}

// On the hydration entry and the instance, not in module state: the page can hold two copies of this
// module. Not enumerable, so the copy of a stencil's entry for a clone does not take it along.
const MOUNT_STATE = Symbol.for('fluid-primitives.mount-state');
const MOUNTED_FROM = Symbol.for('fluid-primitives.mounted-from');

/** `mounted`: the callback ran. `left`: the instance was destroyed because its markup left the page. */
type MountState = 'mounted' | 'left' | undefined;

function mountStateOf(entry: ComponentHydrationData): MountState {
    return (entry as any)[MOUNT_STATE];
}

function setMountState(entry: ComponentHydrationData | undefined, state: MountState) {
    if (entry) Object.defineProperty(entry, MOUNT_STATE, { value: state, configurable: true });
}

function entryOf(instance: object): ComponentHydrationData | undefined {
    return (instance as any)[MOUNTED_FROM];
}

function markMounted(entry: ComponentHydrationData, instance: object | void) {
    setMountState(entry, 'mounted');
    if (instance)
        Object.defineProperty(instance, MOUNTED_FROM, { value: entry, configurable: true });
}

/**
 * Mounts the entries of `componentName`. With `parts`, only those whose parts are in it, as for
 * markup that arrived: the data of markup that left the page stays, and the stencil of a `<template>`
 * has data but no markup on the page. Without, every entry but one whose markup left the page: a
 * callback can set up a component that renders no part of its own.
 */
function mountEntries(
    componentName: string,
    callback: (data: any) => Component<unknown, unknown> | void,
    parts?: PartAttributes
) {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const hydrationInstances = getHydrationData(componentName);
    if (!hydrationInstances) return;

    const mountedInstances = instancesOf(namespace, toKebabCase(baseName));
    const prefix = partAttributePrefix(toKebabCase(baseName));

    Object.keys(hydrationInstances).forEach(id => {
        const entry = hydrationInstances[id];
        if (entry.autoMount === false) return;
        if (Object.hasOwn(mountedInstances, id) || mountStateOf(entry) === 'mounted') return;
        // ponytail: a prefix match, so a FieldArray part (`data-field-array-*`) counts for a Field of
        // the same id too; match the part names of the anatomy if ids ever repeat across components
        const hasArrived = parts?.get(id)?.some(name => name.startsWith(prefix));
        if (parts ? !hasArrived : mountStateOf(entry) === 'left') return;

        const instance = callback({
            ...entry,
            props: applyClientPropConverters(baseName, entry.props),
            createHydrator: () => new ComponentHydrator(baseName, id),
        });
        markMounted(entry, instance);
        if (!instance) return;

        instance.namespace = namespace;
        mountedInstances[id] = instance;
    });
}

/**
 * Destroys every mounted instance whose parts are inside `root`, its portaled content included, and
 * forgets it, its hydration data included. `mountAll()` already does this for markup that leaves the
 * page, so call it only when the instances must be gone before your next line runs, e.g. before you
 * rename the fields of the rows after a removed one.
 *
 * @param root - The element that is about to be removed.
 */
export function destroyComponentsWithin(root: Element | Document) {
    if (root instanceof Element) removePortalsWithin(root);

    destroyInstancesWhere(
        instance => instance.isRenderedOnlyInside(root),
        // else the next mountAll() mounts it again, without any DOM
        { forgetHydrationData: true }
    );
}

function* trackedInstances() {
    for (const [namespace, namespaceBucket] of Object.entries(
        window.FluidPrimitives?.componentInstances ?? {}
    )) {
        for (const [name, instances] of Object.entries(namespaceBucket)) {
            for (const [id, instance] of Object.entries(instances)) {
                // Not instanceof: an instance of the other copy of this module is a Component too.
                if (typeof instance.isRenderedOnlyInside !== 'function') continue;
                yield { namespace, name, id, instance, instances };
            }
        }
    }
}

function instancesWhere(test: (instance: Component<unknown, unknown>) => boolean) {
    return Array.from(trackedInstances(), ({ instance }) => instance).filter(test);
}

/** @returns How many instances it destroyed. */
function destroyInstancesWhere(
    isGone: (instance: Component<unknown, unknown>) => boolean,
    { forgetHydrationData }: { forgetHydrationData: boolean }
): number {
    let destroyed = 0;
    for (const { namespace, name, id, instance, instances } of trackedInstances()) {
        if (!isGone(instance)) continue;
        destroyed++;

        if (isDebug()) {
            console.log(
                `[fluid-primitives] Destroying component instance: ${instance.getClientName()}:${id}`
            );
        }
        instance.destroy();
        delete instances[id];
        if (forgetHydrationData) {
            const hydrationInstances = getHydrationData(`${namespace}:${name}`);
            if (hydrationInstances) delete hydrationInstances[id];
        } else {
            setMountState(entryOf(instance), 'left');
        }
    }
    return destroyed;
}

let pageObserver: MutationObserver | undefined;

// Watches the whole document, not the body: Turbo Drive replaces the body element, and merges the
// hydration data block of the next page into the head.
function observePage() {
    pageObserver ??= new MutationObserver(records => {
        const removed = new Set<Element>();
        const added = new Set<Element>();
        for (const record of records) {
            record.removedNodes.forEach(node => node instanceof Element && removed.add(node));
            record.addedNodes.forEach(node => node instanceof Element && added.add(node));
        }

        // A node that was moved, e.g. into a portal, is removed and added in the same batch.
        const gone = Array.from(removed).filter(el => !el.isConnected);
        if (gone.length > 0) destroyLater(gone);

        const arrived = Array.from(added).filter(el => el.isConnected);
        if (arrived.length > 0) {
            // the data can arrive after its markup, so new data looks at the whole page
            mountParts(
                partAttributesByRootId(
                    readNewHydrationDataBlocks() ? [document.documentElement] : arrived
                )
            );
        }
    });
    pageObserver.observe(document.documentElement, { childList: true, subtree: true });
}

/**
 * Destroys the instances that left the page with `gone` a task later, when they are still gone: an
 * element that is detached and put back across an `await`, as Turbo does with a
 * `data-turbo-permanent` element, keeps its instance and its state. The hydration data stays, the
 * markup can come back, e.g. from the cache of Turbo.
 */
function destroyLater(gone: Element[]) {
    const candidates = new Set(instancesWhere(instance => instance.isRenderedOnlyInside()));
    setTimeout(() => {
        // the portals of an element still gone; removing them is a change the observer sees, so
        // a component with its content in a portal is destroyed with the next round
        gone.filter(el => !el.isConnected).forEach(removePortalsWithin);
        const destroyed = destroyInstancesWhere(
            instance => candidates.has(instance) && instance.isRenderedOnlyInside(),
            { forgetHydrationData: false }
        );
        // markup that arrived while an instance of the same rootId was still mounted, e.g. a
        // navigation in the layout of every page Turbo visits
        if (destroyed > 0) mountParts(partAttributesByRootId([document.documentElement]));
    });
}

function partAttributePrefix(clientComponentName: string): string {
    return `data-${clientComponentName.replaceAll('.', '-')}-`;
}

/**
 * @internal
 *
 * Stops tracking an instance, so `getComponentInstance()` no longer finds it and `mount()` or
 * `mountAll()` mounts its `rootId` again. `Component.destroy()` calls it.
 */
export function forgetComponentInstance(instance: Component<unknown, unknown>) {
    setMountState(entryOf(instance), undefined);
    for (const namespaceBucket of Object.values(window.FluidPrimitives?.componentInstances ?? {})) {
        for (const instances of Object.values(namespaceBucket)) {
            for (const [id, tracked] of Object.entries(instances)) {
                if (tracked === instance) delete instances[id];
            }
        }
    }
}

/**
 * Mounts one instance by its `rootId`, also one that was rendered with `autoMount="{false}"`. Like
 * `mountAll()`, it skips an instance that is already mounted: it returns that one and does not call
 * `callback`, so a script that runs twice still gets one instance. To mount it again, call
 * `destroy()` on it first. `getComponentInstance()` and `destroyComponentsWithin()` find it like any
 * other.
 *
 * The `props` in the callback are typed like those of `mountAll()`.
 *
 * @param componentName - The namespaced name, e.g. `ui:dialog`.
 * @param rootId - The `rootId` the component was rendered with.
 * @param callback - Creates the instance and returns it.
 * @returns The instance, the one already mounted, or `undefined` if there is no hydration entry for
 * `rootId`.
 */
export function mount<
    K extends KnownComponentName | (string & {}),
    T extends Component<unknown, unknown>,
>(
    componentName: K,
    rootId: string,
    callback: (data: {
        autoMount: boolean;
        props: HydrationPropsFor<Extract<K, string>>;
        createHydrator: () => ComponentHydrator;
    }) => T | void
): T | undefined {
    const mounted = getComponentInstance<T>(componentName, rootId);
    if (mounted) return mounted;

    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const hydrationData = getHydrationData(componentName, rootId);
    if (!hydrationData) {
        devWarn(
            `mount(): no hydration data for ${componentName} "${rootId}". Is it rendered on this page, ` +
                'or is it a fragment inserted without its payload?'
        );
        return undefined;
    }

    const instance = callback({
        ...hydrationData,
        props: applyClientPropConverters(baseName, hydrationData.props) as HydrationPropsFor<
            Extract<K, string>
        >,
        createHydrator: () => new ComponentHydrator(baseName, rootId),
    });
    markMounted(hydrationData, instance);
    if (!instance) return undefined;

    instance.namespace = namespace;
    instancesOf(namespace, toKebabCase(baseName))[rootId] = instance;

    return instance;
}

/**
 * Finds the parts of one component instance. Every part that `ui:ref` renders carries a
 * `data-<component>-<part>="<rootId>"` attribute, so a lookup needs no id and searches the whole
 * document by default. That is also what finds content that was portaled away from the root.
 */
export class ComponentHydrator {
    /** The name of the component, in camelCase. */
    componentName: string;
    /** The kebab-case form of `componentName`, as it appears in attribute names. */
    clientComponentName: string;
    /** The document the parts are searched in. */
    doc: Document;
    /** The `rootId` the component was rendered with. */
    rootId: string;

    /**
     * @param componentName - The name of the component, in camelCase.
     * @param rootId - The `rootId` the component was rendered with. Throws if it is missing.
     * @param doc - The document the parts are searched in.
     */
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
        return partAttributePrefix(this.clientComponentName);
    }

    // Keep in sync with: Classes/Utility/ComponentRefUtility.php getAttributeName()
    /** The name of the attribute that marks `part`, e.g. `data-accordion-item-trigger`. */
    attr(part: string): string {
        return this.attrPrefix + toKebabCase(part);
    }

    /** The CSS selector of every element of `part` that belongs to this instance. */
    selector(part: string): string {
        return `[${this.attr(part)}="${CSS.escape(this.rootId)}"]`;
    }

    // Keep in sync with: Classes/Utility/ComponentRefUtility.php getScopeKey()
    /** The key that identifies `part`, and optionally one `value` of it, across this instance. */
    scopeKey(part: string, value?: string): string {
        const key = `${this.clientComponentName}:${this.rootId}:${toKebabCase(part)}`;
        return value ? `${key}:${value}` : key;
    }

    /**
     * Finds the first element of `part`.
     *
     * @param part - The name of the part, in camelCase.
     * @param parent - Where to search, the whole document by default.
     * @returns The element, or `null` if there is none.
     */
    query<T extends Element = HTMLElement>(
        part: string,
        parent: Element | Document = this.doc
    ): T | null {
        return parent.querySelector<T>(this.selector(part));
    }

    /**
     * Finds every element of `part`.
     *
     * @param part - The name of the part, in camelCase.
     * @param parent - Where to search, the whole document by default.
     */
    queryAll<T extends Element = HTMLElement>(
        part: string,
        parent: Element | Document = this.doc
    ): T[] {
        return Array.from(parent.querySelectorAll<T>(this.selector(part)));
    }

    /**
     * Marks an element as `part` of this instance, like `ui:ref` does on the server. For elements
     * you create after hydration.
     *
     * @param element - The element to mark.
     * @param part - The name of the part, in camelCase.
     * @param value - Sets `data-value`, for a part that exists once per item.
     */
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
     * @internal
     *
     * Sets `data-value` on `root` and on every element inside it that is a part of *this*
     * component's own scope, e.g. the row of a FieldArray and its remove trigger.
     *
     * `attributes`, if given, are set on the same elements, for primitives whose `render()` reads
     * plain per-element data attributes (RadioGroup's `disabled`/`invalid`, NavigationMenu's `current`).
     * A boolean toggles a bare `data-x` attribute, a string sets `data-x="value"`.
     */
    setValue(root: Element, value: string, attributes?: Record<string, boolean | string>): void {
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
     * @internal
     *
     * Makes a freshly cloned `<template>` item (see `Template`) one real item: {@see setValue}, then
     * gives every independent root component inside it, such as the `Field` and `Input` of a
     * FieldArray row, a root id of its own and a copy of its stencil's hydration data. Which
     * components those are comes from `window.FluidPrimitives.nestedComponents[stencilKey]`, recorded
     * by PHP, so it works for a component that renders no element of its own, such as `Dialog`'s root.
     *
     * A component keeps the id it gets here until it is destroyed. The id does not contain `value`:
     * `value` says where the item is now, and that changes (a FieldArray reindexes its rows) while a
     * Zag machine cannot change its id.
     * `mountAll()` mounts them once the clone is in the page.
     */
    restampValue(
        root: Element,
        value: string,
        stencilKey: string,
        attributes?: Record<string, boolean | string>
    ): void {
        this.setValue(root, value, attributes);
        prepareNestedRootComponents(stencilKey, root, value);
    }

    /** Nothing to release yet, it exists so every instance can be destroyed alike. */
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
 * component's id, not just an ancestor's.
 */
function restampIdReferences(root: Element, remaps: RootIdRemap[]): void {
    const restamp = (el: Element) => {
        for (const { name, value } of Array.from(el.attributes)) {
            if (name === 'id') {
                const match = remaps.find(({ oldRootId }) => referencesRootId(value, oldRootId));
                if (match)
                    el.setAttribute(
                        name,
                        value.replace(match.oldRootId, () => match.newRootId)
                    );
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
        const match = remaps.find(({ oldRootId }) => referencesRootId(value, oldRootId));
        remapped[key] = match ? value.replace(match.oldRootId, () => match.newRootId) : value;
    }
    return remapped;
}

/**
 * Copies the hydration data a stencil registered at server render time to the id of one clone. It is
 * a copy, the stencil's own entry stays for the next clone.
 *
 * A `[]` "append" placeholder in a string `name` prop becomes `[value]`, the same trailing-`[]`
 * convention `form.path.ts`'s `appendFieldPathSegment` and `FieldContext.php` use.
 *
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
 * Prepares every nested root component inside a freshly cloned `<template>` item, see
 * {@see ComponentHydrator.restampValue}. Every element that references one of their root ids is
 * re-stamped, not just the root elements: a part may reference the id of a different nested
 * component (Input's label and input reuse Field's ids), and so may their `ids` props. The
 * hydration data is copied for each, so `mountAll()` constructs them once the clone is in the page.
 */
function prepareNestedRootComponents(stencilKey: string, root: Element, value: string) {
    const remaps = nestedRootRemaps(stencilKey);
    if (remaps.length === 0) return;

    // every mapping is known before the first rewrite, see remapIdsProp
    restampIdReferences(root, remaps);

    for (const { componentName, oldRootId, newRootId } of remaps) {
        copyNestedComponentEntry(componentName, oldRootId, newRootId, remaps, value);
    }
}

// A key names a property of the item, or a path of them joined by dots (`meta.id`).
// Keep in sync with: ListCollection::getFromKey() of PHP, which renders the same collection on the server.
function getByPath(item: unknown, path: string): any {
    return path.split('.').reduce<any>((value, key) => value?.[key], item);
}

function asString(value: unknown): string {
    return value == null ? '' : String(value);
}

export function getListCollectionFromHydrationData<T extends CollectionItem>(hydrationCollection: {
    items: T[];
    itemToValueKey?: string | null;
    itemToStringKey?: string | null;
    isItemDisabledKey?: string | null;
    groupByKey?: string | null;
    groupSort?: 'asc' | 'desc' | Array<string> | null;
}): ListCollection<T> {
    if (hydrationCollection instanceof ListCollection) {
        return hydrationCollection;
    }

    const { itemToValueKey, itemToStringKey, isItemDisabledKey, groupByKey, groupSort } =
        hydrationCollection;

    return new ListCollection<T>({
        items: hydrationCollection.items,
        // Zag types these as returning a `string` but never coerces. Fluid turns a quoted `'1'` into
        // an int, and the PHP collection reads it back as the string `'1'`, so do the same here.
        itemToValue: (item: any) => asString(getByPath(item, itemToValueKey ?? 'value')),
        itemToString: (item: any) => {
            const label = getByPath(item, itemToStringKey ?? 'label');
            // a plain `{value}` item needs no label, a configured `itemToStringKey` never falls back
            return asString(
                label ?? (itemToStringKey ? '' : getByPath(item, itemToValueKey ?? 'value'))
            );
        },
        isItemDisabled: isItemDisabledKey
            ? (item: any) => getByPath(item, isItemDisabledKey)
            : undefined,
        groupBy: groupByKey ? (item: any) => getByPath(item, groupByKey) : undefined,
        groupSort: groupSort ?? undefined,
    });
}
