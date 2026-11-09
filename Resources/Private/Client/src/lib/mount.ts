import type { HydrationPropsFor, KnownComponentName, MountRecord } from '../types';
import { instancesRenderedInside, observePage } from './auto-mount';
import { applyClientPropConverters } from './client-prop-converters';
import type { Component } from './component';
import { ComponentHydrator } from './component-hydrator';
import { parseNamespacedComponentName, partAttributePrefix, toKebabCase } from './component-name';
import { devWarn, isDebug } from './debug';
import {
    createFluidPrimitives,
    getFluidPrimitives,
    getHydrationData,
    readHydrationDataBlocks,
} from './hydration';
import { removePortalsWithin } from './portal';

type MountCallback = (data: any) => Component<unknown, unknown> | void;

/** The `data-*` attribute names found in a piece of the page, by their value: a rootId for a part. */
type PartAttributes = Map<string, string[]>;

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

// On window.FluidPrimitives, not in module state: the page can hold two copies of this module.
const mountStates = () => (window.FluidPrimitives.mountStates ??= new WeakMap());
const mountRecords = () => (window.FluidPrimitives.mountRecords ??= new WeakMap());

function recordMount(
    record: Omit<MountRecord, 'instance'>,
    instance: Component<unknown, unknown> | void
) {
    mountStates().set(record.entry, 'mounted');
    if (instance) mountRecords().set(instance, { ...record, instance });
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
        getFluidPrimitives() ?? createFluidPrimitives());
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
    if (!element.isConnected) {
        // its parts would not be found in the document, and its instances never rendered
        devWarn('mountWithin(): the element is not in the page. Insert it first.');
        return;
    }
    readHydrationDataBlocks();
    mountArrived([element]);
}

/**
 * @internal
 *
 * Mounts the components whose parts are in `elements` or anything inside them, with every
 * `mountAll()` callback.
 */
export function mountArrived(elements: Iterable<Element>) {
    const parts: PartAttributes = new Map();
    for (const element of elements) {
        for (const el of [element, ...element.querySelectorAll('*')]) {
            for (const { name, value } of el.attributes) {
                if (!name.startsWith('data-')) continue;
                let names = parts.get(value);
                if (!names) parts.set(value, (names = []));
                names.push(name);
            }
        }
    }
    if (parts.size === 0) return;

    for (const [componentName, callback] of Object.entries(
        window.FluidPrimitives?.mounters ?? {}
    )) {
        // one callback that throws leaves the others to mount
        try {
            mountEntries(componentName, callback, parts);
        } catch (error) {
            console.error(`[fluid-primitives] Mounting ${componentName} failed.`, error);
        }
    }
}

/**
 * Mounts the entries of `componentName`. Without `parts`, as `mountAll()` itself does: every entry
 * that is not mounted and whose markup did not leave the page, as a callback can set up a component
 * that renders no part of its own. With `parts`, as for markup that arrived: only the entries whose
 * parts are in it, since the data of markup that left the page stays, and the stencil of a
 * `<template>` has data but no markup on the page.
 */
function mountEntries(componentName: string, callback: MountCallback, parts?: PartAttributes) {
    const { namespace, baseName } = parseNamespacedComponentName(componentName);
    const hydrationInstances = getHydrationData(componentName);
    if (!hydrationInstances) return;

    const states = mountStates();
    const mountedInstances = instancesOf(namespace, toKebabCase(baseName));
    const prefix = partAttributePrefix(toKebabCase(baseName));
    // the few rootIds that arrived, not every entry of the page
    const ids = parts
        ? [...parts.keys()].filter(id => Object.hasOwn(hydrationInstances, id))
        : Object.keys(hydrationInstances);

    for (const id of ids) {
        const entry = hydrationInstances[id];
        if (entry.autoMount === false) continue;

        if (parts) {
            // ponytail: a prefix match, so a FieldArray part (`data-field-array-*`) counts for a
            // Field of the same id too; match the part names of the anatomy if ids ever repeat
            if (!parts.get(id)!.some(name => name.startsWith(prefix))) continue;
            const mounted = ownValue(mountedInstances, id);
            if (mounted) {
                // The next page with the rootId of a component whose markup already left, e.g. a
                // layout component Turbo swaps: the old instance goes now, not a task later.
                // ponytail: an old instance with content still on the page (a portal outside the
                // swapped region) keeps the rootId; queue the arrival on it if that case shows up
                const record = mountRecords().get(mounted);
                if (!record || !mounted.isRenderedOnlyInside()) continue;
                destroyRecords([record], { forgetHydrationData: false });
            } else if (states.get(entry) === 'mounted') {
                continue;
            }
        } else if (Object.hasOwn(mountedInstances, id) || states.has(entry)) {
            continue;
        }

        const instance = callback({
            ...entry,
            props: applyClientPropConverters(baseName, entry.props),
            createHydrator: () => new ComponentHydrator(baseName, id),
        });
        recordMount({ componentName, id, instances: mountedInstances, entry }, instance);
        if (!instance) continue;

        instance.namespace = namespace;
        mountedInstances[id] = instance;
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
    const instances = instancesOf(namespace, toKebabCase(baseName));
    recordMount({ componentName, id: rootId, instances, entry: hydrationData }, instance);
    if (!instance) return undefined;

    instance.namespace = namespace;
    instances[rootId] = instance;

    return instance;
}

/**
 * Destroys every mounted instance whose parts are inside `root`, its portaled content included, and
 * forgets it, its hydration data included. `mountAll()` already does this for markup that leaves the
 * page, so call it only when the instances must be gone before your next line runs, e.g. before you
 * rename the fields of the rows after a removed one. Call it before you insert markup that replaces
 * `root` with the same rootIds: their hydration data is forgotten. Instances whose markup already
 * left the page are not found here, `mountAll()` destroys those.
 *
 * @param root - The element that is about to be removed, or was just removed.
 */
export function destroyComponentsWithin(root: Element | Document) {
    const element = root instanceof Document ? root.documentElement : root;
    if (root instanceof Element) removePortalsWithin(root);

    destroyRecords(
        [...instancesRenderedInside([element])].filter(({ instance }) =>
            instance.isRenderedOnlyInside(element)
        ),
        // else the next mountAll() mounts it again, without any DOM
        { forgetHydrationData: true }
    );
}

/**
 * @internal
 *
 * Destroys the instances of `records`. Without `forgetHydrationData`, the hydration data stays and
 * the entry is marked as left, so its markup mounts again when it comes back and only then.
 */
export function destroyRecords(
    records: Iterable<MountRecord>,
    { forgetHydrationData }: { forgetHydrationData: boolean }
) {
    for (const { componentName, id, instances, instance, entry } of records) {
        if (isDebug()) {
            console.log(
                `[fluid-primitives] Destroying component instance: ${instance.getClientName()}:${id}`
            );
        }
        instance.destroy();
        // also when a subclass overrides destroy() without calling super.destroy()
        if (instances[id] === instance) delete instances[id];
        if (forgetHydrationData) delete getHydrationData(componentName)?.[id];
        else mountStates().set(entry, 'left');
    }
}

/**
 * @internal
 *
 * Stops tracking an instance, so `getComponentInstance()` no longer finds it and `mount()` or
 * `mountAll()` mounts its `rootId` again. `Component.destroy()` calls it.
 */
export function forgetComponentInstance(instance: Component<unknown, unknown>) {
    const record = window.FluidPrimitives?.mountRecords?.get(instance);
    if (!record) return;
    window.FluidPrimitives.mountRecords!.delete(instance);
    window.FluidPrimitives.mountStates?.delete(record.entry);
    if (record.instances[record.id] === instance) delete record.instances[record.id];
}
