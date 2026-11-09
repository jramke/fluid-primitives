import type {
    ComponentHydrationData,
    FluidPrimitivesGlobals,
    NestedComponentEntry,
} from '../types';
import { parseNamespacedComponentName, toKebabCase } from './component-name';

const HYDRATION_DATA_SELECTOR =
    'script[type="application/json"][data-fluid-primitives-hydration-data]';

/**
 * @internal
 *
 * The hydration data, globals and mounted instances of the page. The server writes the data as one JSON block
 * per rendering pass: a page from the page cache has one of its cached content and one of the uncached content
 * (USER_INT) of the request. The first call reads them into `window.FluidPrimitives`, later calls return it.
 */
export function getFluidPrimitives(): Window['FluidPrimitives'] | undefined {
    if (!window.FluidPrimitives) readHydrationDataBlocks();
    return window.FluidPrimitives;
}

/** @internal An empty `window.FluidPrimitives`, for a page without hydration data. */
export function createFluidPrimitives(): Window['FluidPrimitives'] {
    return {
        componentInstances: {},
        hydrationData: Object.create(null),
        nestedComponents: {},
    };
}

/**
 * @internal
 *
 * Reads the hydration data blocks in the page into `window.FluidPrimitives` and removes them: the
 * block of the next page that Turbo merges into the head does not pile up next to them, and every
 * block left in the page is one that was not read yet.
 */
export function readHydrationDataBlocks(): void {
    const blocks = document.querySelectorAll<HTMLScriptElement>(HYDRATION_DATA_SELECTOR);
    if (blocks.length === 0) return;

    const target = (window.FluidPrimitives ??= createFluidPrimitives());
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
        target.globals = { ...target.globals, ...data.globals } as FluidPrimitivesGlobals;
        addHydrationEntries(target.hydrationData, data.hydrationData ?? {});
        target.nestedComponents = { ...target.nestedComponents, ...data.nestedComponents };
    }
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
 * @internal
 *
 * Root components PHP found nested inside the tracked scope `scopeId` (a `ui:template` stencil's
 * own id, or a FieldArray row's own id) - see `HydrationRegistry::recordNestedComponent()`'s own
 * docblock for why this is authoritative, PHP-recorded data rather than something inferred from
 * scanning rendered DOM shape (which can't find a component that renders no DOM element of its own,
 * e.g. `Dialog`'s `Root`, or whose content has since portaled elsewhere).
 */
export function getNestedComponents(scopeId: string): NestedComponentEntry[] {
    return getFluidPrimitives()?.nestedComponents?.[scopeId] ?? [];
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
