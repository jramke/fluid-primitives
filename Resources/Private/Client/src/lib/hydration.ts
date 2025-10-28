import { ListCollection, type CollectionItem } from '@zag-js/collection';
import type { ComponentHydrationData } from '../types';
import { Component } from './component';

export function getHydrationData(component: string): Record<string, ComponentHydrationData> | null;
export function getHydrationData(component: string, id: string): ComponentHydrationData | null;
export function getHydrationData(component?: string, id?: string) {
	const hydrationData = window.FluidPrimitives.hydrationData;

	if (!hydrationData || typeof hydrationData !== 'object') {
		return null;
	}

	if (!component) {
		return hydrationData;
	}

	if (!hydrationData[component]) {
		return null;
	}

	if (!id) {
		return hydrationData[component];
	}

	return hydrationData[component][id] || null;
}

export function initAllComponentInstances(
	componentName: string,
	callback: (data: ComponentHydrationData) => Component<unknown, unknown> | undefined
) {
	const hydrationInstances = getHydrationData(componentName);
	if (!hydrationInstances) return;

	Object.keys(hydrationInstances).forEach(id => {
		if (hydrationInstances[id].controlled) return;
		const instance = callback(hydrationInstances[id]);
		if (!instance) return;

		if (!window.FluidPrimitives.uncontrolledInstances[componentName]) {
			window.FluidPrimitives.uncontrolledInstances[componentName] = {};
		}
		window.FluidPrimitives.uncontrolledInstances[componentName][id] = instance;
	});
}

export class ComponentHydrator {
	componentName: string;
	doc: Document;
	rootId: string;
	ids: { [key: string]: string };
	elementRefs = new Map<string, Element | Element[]>();

	constructor(
		componentName: string,
		rootId: string | undefined,
		ids: { [key: string]: string } = {},
		doc: Document = document
	) {
		this.componentName = componentName;
		this.doc = doc;
		if (!rootId) {
			throw new Error(`Root ID is required for component hydration: ${componentName}`);
		}
		this.rootId = rootId;
		this.ids = ids;
	}

	getElement<T extends Element>(part: string, parent: Element | Document = this.doc): T | null {
		if (this.elementRefs.has(part)) {
			return (this.elementRefs.get(part) as T) || null;
		}

		let element: T | null = null;

		if (this.ids[part]) {
			element = parent.querySelector<T>(`#${this.ids[part]}`);
		} else {
			element = parent.querySelector<T>(
				`[data-hydrate-${this.componentName}="${this.rootId}"][data-part="${part}"][data-scope="${this.componentName}"]`
			);
		}

		if (element) {
			if (parent === this.doc) {
				this.elementRefs.set(part, element);
			}
			element.removeAttribute(`data-hydrate-${this.componentName}`);
			(element as any).__rootId = this.rootId;
		}

		return element;
	}

	getElements<T extends Element>(part: string, parent: Element | Document = this.doc): T[] {
		if (this.elementRefs.has(part)) {
			return this.elementRefs.get(part) as T[];
		}

		let elements: T[] = [];

		if (this.ids[part]) {
			elements = Array.from(parent.querySelectorAll<T>(`#${this.ids[part]}`));
		} else {
			elements = Array.from(
				parent.querySelectorAll<T>(
					`[data-hydrate-${this.componentName}="${this.rootId}"][data-part="${part}"][data-scope="${this.componentName}"]`
				)
			);
		}

		if (parent === this.doc) {
			this.elementRefs.set(part, elements);
		}
		elements.forEach(el => el.removeAttribute(`data-hydrate-${this.componentName}`));

		return elements;
	}

	generateRefAttributesString(part: string): string {
		const id = this.ids[part] || `${this.rootId}-${part}`;
		return `data-scope="${this.componentName}" data-part="${part}" data-hydrate-${this.componentName}="${id}"`;
	}

	setRefAttributes(element: Element, part: string): void {
		const attributes = this.generateRefAttributesString(part);
		const attributesArray = attributes.split(' ').map(attr => attr.trim());
		attributesArray.forEach(attr => {
			const [key, value] = attr.split('=');
			element.setAttribute(key, value.replace(/"/g, ''));
		});
	}

	destroy() {
		this.elementRefs.clear();
	}
}

export function getListCollectionFromHydrationData<T extends CollectionItem>(hydrationCollection: {
	items: T[];
	itemToValueKey?: string;
	itemToStringKey?: string;
	isItemDisabledKey?: string;
	groupByKey?: string;
}): ListCollection<T> {
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
	});
	return collection;
}
