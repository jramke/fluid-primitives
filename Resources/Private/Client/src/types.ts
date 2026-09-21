import { VanillaMachine as Machine } from '@zag-js/vanilla';
import type { ComponentHydrator } from './lib';
import type { Component } from './lib/component';

declare global {
    interface Window {
        FluidPrimitives: {
            hydrationData: {
                [componentName: string]: {
                    [id: string]: ComponentHydrationData;
                };
            };
            globals?: FluidPrimitivesGlobals;
            uncontrolledInstances: {
                [componentName: string]: {
                    [id: string]: Component<unknown, unknown>;
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
    /** The nested component's clientComponentName, e.g. "field" or "dialog". */
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
 * The wire shape `Jramke\FluidPrimitives\Domain\Dto\ListCollection::jsonSerialize()` actually
 * produces - not the same as `@zag-js/collection`'s own `ListCollection<T>` instance shape a
 * primitive's `transformProps()` constructs *from* this (see `getListCollectionFromHydrationData`).
 * The canonical `getTsType()` target for {@see ListCollection}'s own
 * `ClientTypeAwareInterface` implementation - imported by every generated `*.hydration.ts` whose
 * primitive has a `collection` prop, instead of each one declaring its own duplicate inline literal.
 */
export interface ListCollectionData {
    items: unknown[];
    size: number;
    first: string | null;
    last: string | null;
    itemToValueKey: string | null;
    itemToStringKey: string | null;
    isItemDisabledKey: string | null;
    groupByKey: string | null;
    groupSort: string | string[] | null;
}

/**
 * Per-primitive hydration `props` shapes, extended via TS module augmentation by each generated
 * `*.hydration.ts` (`declare module 'fluid-primitives/client' { interface HydrationPropsRegistry {
 * select: SelectHydrationProps } }`) - see `mountAll`/`mount` in `lib/hydration.ts`, which key off
 * this to infer `props`'s type from the `componentName` string literal alone. Empty by default -
 * intentionally not fully generic-parameterized (`mountAll<SelectHydrationProps>(...)`) so entry
 * files need no type annotation at all; a component with no generated augmentation yet falls
 * through to {@see ComponentHydrationData}'s own untyped `props` bag.
 */
export interface HydrationPropsRegistry {}

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
