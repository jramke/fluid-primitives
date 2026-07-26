import { ListCollection, type CollectionItem } from '@zag-js/collection';
import type { ComponentHydrationData, FluidPrimitivesGlobals } from '../types';
import { Component } from './component';

export function getHydrationData(component: string): Record<string, ComponentHydrationData> | null;
export function getHydrationData(component: string, id: string): ComponentHydrationData | null;
export function getHydrationData(component?: string, id?: string) {
    const hydrationData = window.FluidPrimitives?.hydrationData;

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

export function getGlobals(): FluidPrimitivesGlobals | null {
    const globals = window.FluidPrimitives?.globals;

    if (!globals || typeof globals !== 'object') {
        return null;
    }

    return globals;
}

export function getGlobal<T = unknown>(key: string): T | undefined {
    const globals = getGlobals();
    if (!globals || !(key in globals)) {
        return undefined;
    }

    return globals[key] as T;
}

/**
 * Mounts every not-yet-mounted, uncontrolled hydration instance of
 * `componentName`. Safe to call more than once (e.g. after
 * {@see mergeHydrationData} adds instances for lazily-inserted DOM) - already
 * mounted instances are skipped rather than re-instantiated.
 */
export function mount(
    componentName: string,
    callback: (
        data: ComponentHydrationData & { createHydrator: () => ComponentHydrator }
    ) => Component<unknown, unknown> | void
) {
    const hydrationInstances = getHydrationData(componentName);
    if (!hydrationInstances) return;

    if (!window.FluidPrimitives.uncontrolledInstances[componentName]) {
        window.FluidPrimitives.uncontrolledInstances[componentName] = {};
    }
    const mountedInstances = window.FluidPrimitives.uncontrolledInstances[componentName];

    Object.keys(hydrationInstances).forEach(id => {
        if (hydrationInstances[id].controlled) return;
        if (mountedInstances[id]) return;

        const instance = callback({
            ...hydrationInstances[id],
            createHydrator: () =>
                new ComponentHydrator(componentName, id, hydrationInstances[id].props.ids),
        });
        if (!instance) return;

        mountedInstances[id] = instance;
    });
}

/**
 * Folds a fragment's own hydration payload (as returned by a component
 * fragment endpoint, see `Jramke\FluidPrimitives\Service\ComponentFragmentRenderer`
 * on the PHP side) into the page-wide `window.FluidPrimitives.hydrationData`,
 * so a subsequent {@see mount} call picks up the newly inserted instances.
 */
export function mergeHydrationData(
    data: Record<string, Record<string, ComponentHydrationData>> | null | undefined
) {
    if (!data || !window.FluidPrimitives) return;

    for (const [componentName, instances] of Object.entries(data)) {
        if (!window.FluidPrimitives.hydrationData[componentName]) {
            window.FluidPrimitives.hydrationData[componentName] = {};
        }
        Object.assign(window.FluidPrimitives.hydrationData[componentName], instances);
    }
}

/**
 * Destroys every mounted, uncontrolled component instance whose root element
 * is `root` itself or a descendant of it, and drops them from the tracked
 * instance registry. Intended for cleaning up before removing a subtree from
 * the DOM (e.g. a lazily-mounted recurring-field row).
 */
export function destroyComponentsWithin(root: Element | Document) {
    if (!window.FluidPrimitives) return;

    for (const instances of Object.values(window.FluidPrimitives.uncontrolledInstances)) {
        for (const [id, instance] of Object.entries(instances)) {
            const componentPartsInNode = root.querySelectorAll(
                `[id^="${instance.getName()}:${id}"]`
            );
            const hasComponentPartsInNode = componentPartsInNode.length > 0;

            if (hasComponentPartsInNode) {
                console.log(`Destroying component instance: ${instance.getName()}:${id}`);
                instance.destroy();
                delete instances[id];
            }
        }
    }
}

export function mountControlled<T>(
    componentName: string,
    rootId: string,
    callback: (data: ComponentHydrationData & { createHydrator: () => ComponentHydrator }) => T
): T | undefined {
    const hydrationData = getHydrationData(componentName, rootId);
    if (!hydrationData) return undefined;

    return callback({
        ...hydrationData,
        createHydrator: () => new ComponentHydrator(componentName, rootId, hydrationData.props.ids),
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
        this.elementRefs.forEach(el => {
            if (el instanceof Element) {
                el.setAttribute(`data-hydrate-${this.componentName}`, this.rootId);
            } else {
                el.forEach(e => e.setAttribute(`data-hydrate-${this.componentName}`, this.rootId));
            }
        });
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
    if (hydrationCollection instanceof ListCollection) {
        return hydrationCollection;
    }

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
