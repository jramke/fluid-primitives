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
             * `hydrationData` above - populated by both, so `getComponentInstance` can find a
             * component that was mounted by hand with `mount` too.
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

/** @internal */
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

/** @internal */
export interface ComponentInterface<Api> {
    document: Document;
    machine: Machine<any>;
    api: Api;
    hydrator: ComponentHydrator;

    init(): void;
    destroy(): void;
    render(): void;
}

/** The hydration entry of one component instance, as PHP renders it for `mountAll()` and `mount()`. */
export interface ComponentHydrationData {
    /** Whether the instance mounts on its own: `false` when it was rendered with `autoMount="{false}"`. */
    autoMount: boolean;
    /** The props of the instance. Typed per component by `HydrationPropsRegistry`. */
    props: {
        id: string;
        ids: { [key: string]: string };
        [key: string]: unknown;
    };
}

/**
 * Types the `props` of `mountAll()` and `mount()` per component. It is empty on purpose: a project
 * fills it with its own generated types, keyed by the namespaced name used at the call site.
 * A component that is not in it gets the untyped `props` of `ComponentHydrationData`.
 *
 * @example
 * ```typescript
 * declare module 'fluid-primitives' {
 *     interface HydrationPropsRegistry {
 *         'ui:select': SelectHydrationProps;
 *     }
 * }
 * ```
 */
export interface HydrationPropsRegistry {}

/**
 * Overrides the type of the props whose shape changes through a registered client prop converter,
 * for example the `collection` of a Select: JSON on the wire, a `ListCollection` once converted.
 * It is keyed by the bare component name. Derive each entry from the converters with
 * `ConverterMachineProps` instead of typing it by hand.
 */
export interface HydrationPropsOverrides {}

/** @internal */
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
 * The `props` type of the callback of `mountAll()` and `mount()` for the component name `K`: its
 * entry in `HydrationPropsRegistry`, with the props that have a converter replaced by their
 * `HydrationPropsOverrides`. A name that is not in the registry gets the untyped props.
 */
export type HydrationPropsFor<K extends string> = K extends keyof HydrationPropsRegistry
    ? Omit<HydrationPropsRegistry[K], keyof PickOverride<K>> & PickOverride<K>
    : ComponentHydrationData['props'];

/** @internal */
export interface FluidPrimitivesGlobals {
    locale?: string;
    /**
     * Enables dev-only checks. Set automatically to whether
     * TYPO3's own Application Context is development (see `HydrationRegistry::resolveGlobals()`),
     * nothing for a consumer to configure. These checks aren't gated by a bundler env variable, so
     * nothing is stripped from the production bundle either way - they simply never run unless
     * this is true.
     */
    debug?: boolean;
    [key: string]: unknown;
}
