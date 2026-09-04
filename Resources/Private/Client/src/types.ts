import type { CollectionItem, ListCollection } from '@zag-js/collection';
import { VanillaMachine as Machine } from '@zag-js/vanilla';
import type { ComponentHydrator } from './lib';
import type { Component } from './lib/component';

export interface ComboboxFilterHookDetails {
    inputValue: string;
    collection: ListCollection<CollectionItem>;
    component: Component<unknown, unknown>;
}

export type ComboboxFilterHookResult =
    | ListCollection<CollectionItem>
    | CollectionItem[]
    | null
    | undefined;

export type ComboboxFilterResolver = (
    details: ComboboxFilterHookDetails
) => ComboboxFilterHookResult;

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
        };
    }
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

export interface FluidPrimitivesGlobals {
    locale?: string;
    [key: string]: unknown;
}
