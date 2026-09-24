import { VanillaMachine as Machine } from '@zag-js/vanilla';
import type { ComponentHydrator } from './lib';
import type { Component } from './lib/component';

declare global {
    interface Window {
        FluidPrimitives: {
            /**
             * Nested by Fluid namespace identifier first (e.g. "ui"/"primitives" - the same prefix
             * a `mountAll`/`mount` call must use, see `parseNamespacedComponentName`), then by the
             * bare component name, then by rootId - never a flat `"ui:select"`-string-keyed map.
             * Two collections can legitimately register a same-named component with a different
             * shape (a styled wrapper around a primitive it doesn't expose every prop of), and
             * nesting by namespace first keeps those genuinely separate.
             */
            hydrationData: {
                [namespace: string]: {
                    [componentName: string]: {
                        [id: string]: ComponentHydrationData;
                    };
                };
            };
            globals?: FluidPrimitivesGlobals;
            /**
             * Every component instance `mountAll`/`mount` has created, nested the same way as
             * `hydrationData` above - populated by both (not just `mountAll`, as the older
             * `uncontrolledInstances` name implied), so `getComponentInstance` can find a `mount`-ed
             * controlled component too.
             */
            componentInstances: {
                [namespace: string]: {
                    [componentName: string]: {
                        [id: string]: Component<unknown, unknown>;
                    };
                };
            };
            /**
             * Root components PHP found nested inside a tracked scope - a `ui:template` stencil, or
             * a FieldArray row - keyed by that scope's own id (see
             * `HydrationRegistry::recordNestedComponent()`'s own docblock for why this exists rather
             * than inferring nesting from rendered DOM shape). Read via
             * {@see getNestedComponents}, not indexed directly.
             */
            nestedComponents?: {
                [scopeId: string]: NestedComponentEntry[];
            };
        };
    }
}

export interface NestedComponentEntry {
    /**
     * The nested component's own hydration registry key, "namespace:clientBaseName" (e.g.
     * "primitives:field") - a single opaque, round-tripped string, never parsed/indexed as an
     * object path the way `hydrationData`/`componentInstances` are.
     */
    name: string;
    /** Its bare rootId - the same form `ComponentHydrator`'s own `rootId` uses, no namespace prefix. */
    id: string;
}

export interface ComponentInterface<Api> {
    document: Document;
    machine: Machine<any>;
    api: Api;
    hydrator: ComponentHydrator | null;

    init(): void;
    destroy(): void;
    render(): void;
}

export interface ComponentHydrationData {
    controlled: boolean;
    props: {
        id: string;
        ids: { [key: string]: string };
        [key: string]: unknown;
    };
}

/**
 * Keyed by the exact namespaced string a `mountAll`/`mount` call site uses (e.g. `"ui:select"`),
 * extended per-namespace by a project's own generated hydration types via `declare module
 * 'fluid-primitives' { interface HydrationPropsRegistry { "ui:select": SelectHydrationProps } }` -
 * empty here by design, fluid-primitives itself never populates this. A key absent from this
 * registry (not yet generated, or a project that never runs the generator) falls back to
 * {@see ComponentHydrationData}'s own untyped `props` shape - graceful degradation, not an error.
 */
export interface HydrationPropsRegistry {}

/**
 * Per-component overrides for props whose wire (JSON) shape differs from what the constructor
 * actually needs after a registered client prop converter runs (see `client-prop-converters.ts`) -
 * e.g. Select's `collection`: a plain JSON shape on the wire, a real `@zag-js/collection`
 * `ListCollection` instance once converted. Populated by the primitive itself (co-located with its
 * own `registerClientPropConverters` call, always shipped - see `Select.ts`), not by generated
 * code, and derived from the converter function's own signature via `ConverterMachineProps`
 * rather than hand-typed a second time.
 */
export interface HydrationPropsOverrides {}

export type KnownComponentName = Extract<keyof HydrationPropsRegistry, string>;

/**
 * `K` with any `"namespace:"` prefix stripped (e.g. `"ui:select"` -> `"select"`) - converters (see
 * `client-prop-converters.ts`) are registered once per TS class under its own bare name,
 * independent of which namespace ends up mounting it, so {@see HydrationPropsOverrides} stays
 * bare-keyed even though {@see HydrationPropsRegistry} itself is namespaced.
 */
type BareComponentName<K extends string> = K extends `${string}:${infer BaseName}` ? BaseName : K;

type PickOverride<K extends string> =
    BareComponentName<K> extends keyof HydrationPropsOverrides
        ? HydrationPropsOverrides[BareComponentName<K>]
        : object;

/**
 * The typed `props` shape for `mountAll`/`mount`'s callback, keyed off the exact namespaced string
 * literal passed at the call site - registry entry minus whatever keys have a registered converter
 * override, plus that override. Falls back to the generic untyped bag for a key not present in
 * {@see HydrationPropsRegistry} at all.
 */
export type HydrationPropsFor<K extends string> = K extends keyof HydrationPropsRegistry
    ? Omit<HydrationPropsRegistry[K], keyof PickOverride<K>> & PickOverride<K>
    : ComponentHydrationData['props'];

export interface FluidPrimitivesGlobals {
    locale?: string;
    /**
     * Enables dev-only checks like {@see warnAboutDuplicateIds}. Set automatically to whether
     * TYPO3's own Application Context is development (see `HydrationRegistry::resolveGlobals()`),
     * nothing for a consumer to configure. These checks aren't gated by a bundler env variable, so
     * nothing is stripped from the production bundle either way - they simply never run unless
     * this is true.
     */
    debug?: boolean;
    [key: string]: unknown;
}
