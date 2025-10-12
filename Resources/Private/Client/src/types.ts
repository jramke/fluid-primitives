import type { ComponentHydrator, Machine } from './lib';
import type { Component } from './lib/component';

declare global {
	interface Window {
		FluidPrimitives: {
			hydrationData: {
				[componentName: string]: {
					[id: string]: ComponentHydrationData;
				};
			};
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
