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
