import { ListCollection, type CollectionItem } from '@zag-js/collection';
import type { ComponentHydrationData, FluidPrimitivesGlobals } from '../types';
import { Component } from './component';

// Keep in sync with: Classes/Utility/ComponentUtility.php
const ID_NAMESPACE_OVERRIDES: Record<string, string> = {
    'navigation-menu': 'nav-menu',
    clipboard: 'clip',
    'file-upload': 'file',
};

// Keep in sync with: Classes/Utility/ComponentUtility.php
type PartSegmentOverride =
    string | { segment: string; valueSeparator?: string; rootIdSeparator?: string };

const PART_SEGMENT_OVERRIDES: Record<string, Record<string, PartSegmentOverride>> = {
    // TODO: Revisit this override map after upgrading to zag-js v2.
    'radio-group': {
        item: 'radio',
        itemHiddenInput: 'radio:input',
        itemControl: 'radio:control',
        itemText: 'radio:label',
    },
    accordion: {
        itemTrigger: 'trigger',
        itemContent: 'content',
    },
    select: {
        hiddenSelect: 'select',
        itemGroup: 'optgroup',
        itemGroupLabel: 'optgroup-label',
        item: 'option',
    },
    combobox: {
        positioner: 'popper',
        trigger: 'toggle-btn',
        clearTrigger: 'clear-btn',
        itemGroup: 'optgroup',
        itemGroupLabel: 'optgroup-label',
        item: 'option',
    },
    tabs: {
        trigger: { segment: 'trigger', valueSeparator: '-' },
        content: { segment: 'content', valueSeparator: '-' },
    },
    'number-input': {
        incrementTrigger: 'inc',
        decrementTrigger: 'dec',
    },
    popover: {
        positioner: 'popper',
        description: 'desc',
        closeTrigger: 'close',
    },
    switch: {
        hiddenInput: 'input',
    },
    'file-upload': {
        hiddenInput: 'input',
        itemSizeText: 'item-size',
        itemDeleteTrigger: 'item-delete',
    },
    tooltip: {
        positioner: 'popper',
    },
    dialog: {
        closeTrigger: 'close',
    },
    slider: {
        valueText: 'value-text',
        hiddenInput: 'input',
    },
    'scroll-area': {
        root: { segment: 'root', rootIdSeparator: '-' },
        viewport: { segment: 'viewport', rootIdSeparator: '-' },
        content: { segment: 'content', rootIdSeparator: '-' },
    },
};

// A part's own name is lowerCamelCase (mirroring zag-js's own `ids` prop keys, so overriding a
// part's id reads the same way it does in zag itself), but `data-part` always renders lower-kebab
// for CSS/selector consistency. Keep in sync with: Classes/Utility/ComponentUtility.php's
// camelCaseToLowerCaseDashed()/lowerCaseDashedToCamelCase().
export function toKebabCase(part: string): string {
    return part.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

function toCamelCase(part: string): string {
    return part.replace(/-([a-z0-9])/g, (_match, char: string) => char.toUpperCase());
}

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

    // Hydration data itself is always registered under the kebab-case clientComponentName
    // (mirrors PHP's HydrationRegistry) - converting here means every caller, direct or via
    // mount()/mountAll(), can consistently pass the same camelCase name used everywhere else
    // (static componentName, getElement() part names).
    const clientComponentName = toKebabCase(component);

    if (!hydrationData[clientComponentName]) {
        return null;
    }

    if (!id) {
        return hydrationData[clientComponentName];
    }

    return hydrationData[clientComponentName][id] || null;
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
 * Mounts every not-yet-mounted, uncontrolled hydration instance of `componentName`. Safe to call
 * more than once (e.g. after lazily-inserted DOM adds new instances) - already mounted instances
 * are skipped rather than re-instantiated.
 */
export function mountAll(
    componentName: string,
    callback: (
        data: ComponentHydrationData & { createHydrator: () => ComponentHydrator }
    ) => Component<unknown, unknown> | void
) {
    const hydrationInstances = getHydrationData(componentName);
    if (!hydrationInstances) return;

    // Keyed by the kebab form so two mountAll() calls for the same component (one camelCase, one
    // still kebab mid-migration) share one tracked-instance bucket instead of silently doubling up.
    const clientComponentName = toKebabCase(componentName);
    if (!window.FluidPrimitives.uncontrolledInstances[clientComponentName]) {
        window.FluidPrimitives.uncontrolledInstances[clientComponentName] = {};
    }
    const mountedInstances = window.FluidPrimitives.uncontrolledInstances[clientComponentName];

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
 * Destroys every mounted, uncontrolled component instance whose root element
 * is `root` itself or a descendant of it, and drops them from the tracked
 * instance registry. Intended for cleaning up before removing a subtree from
 * the DOM (e.g. a lazily-mounted recurring-field row).
 */
export function destroyComponentsWithin(root: Element | Document) {
    if (!window.FluidPrimitives) return;

    for (const instances of Object.values(window.FluidPrimitives.uncontrolledInstances)) {
        for (const [id, instance] of Object.entries(instances)) {
            // Ids are always generated from the kebab clientComponentName, not the canonical
            // componentName instance.getName() itself returns - see Component.getClientName().
            const clientComponentName = instance.getClientName();
            const componentPartsInNode = root.querySelectorAll(
                `[id^="${clientComponentName}:${id}"]`
            );
            const hasComponentPartsInNode = componentPartsInNode.length > 0;

            if (hasComponentPartsInNode) {
                console.log(`Destroying component instance: ${clientComponentName}:${id}`);
                instance.destroy();
                delete instances[id];
            }
        }
    }
}

/**
 * Gets one specific hydration instance of `componentName` by its `rootId` and hands it to
 * `callback`, regardless of whether it was rendered with `controlled="{true}"`. Unlike
 * {@see mountAll}, this does not track mounted state - calling it twice for the same `rootId`
 * runs the callback twice, and instances created this way are invisible to
 * {@see destroyComponentsWithin}.
 */
export function mount<T>(
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
    // Kebab form of componentName - what actually appears in data-scope, hydration ids, and the
    // ID_NAMESPACE_OVERRIDES/PART_SEGMENT_OVERRIDES maps below (kept in sync with
    // ComponentPartIdUtility's own kebab maps). Derived once here so nothing downstream converts
    // repeatedly.
    clientComponentName: string;
    doc: Document;
    rootId: string;
    ids: { [key: string]: string };

    constructor(
        componentName: string,
        rootId: string | undefined,
        ids: { [key: string]: string } = {},
        doc: Document = document
    ) {
        this.componentName = componentName;
        this.clientComponentName = toKebabCase(componentName);
        this.doc = doc;
        if (!rootId) {
            throw new Error(`Root ID is required for component hydration: ${componentName}`);
        }
        this.rootId = rootId;
        this.ids = ids;
    }

    private getIdNamespace(): string {
        return ID_NAMESPACE_OVERRIDES[this.clientComponentName] ?? this.clientComponentName;
    }

    private getPartConfig(part: string): {
        segment: string;
        valueSeparator: string;
        rootIdSeparator?: string;
    } {
        const override = PART_SEGMENT_OVERRIDES[this.clientComponentName]?.[part];
        if (!override) {
            return { segment: part, valueSeparator: ':', rootIdSeparator: ':' };
        }
        if (typeof override === 'string') {
            return { segment: override, valueSeparator: ':', rootIdSeparator: ':' };
        }

        return {
            segment: override.segment,
            valueSeparator: override.valueSeparator ?? ':',
            rootIdSeparator: override.rootIdSeparator ?? ':',
        };
    }

    private computePartId(part: string, value?: string): string {
        if (this.ids[part]) {
            return this.ids[part];
        }

        const idNamespace = this.getIdNamespace();
        const { segment: partSegment, valueSeparator, rootIdSeparator } = this.getPartConfig(part);

        if (part === 'root') {
            return `${idNamespace}${rootIdSeparator}${this.rootId}`;
        }

        if (value !== undefined && value !== '') {
            return `${idNamespace}${rootIdSeparator}${this.rootId}:${partSegment}${valueSeparator}${value}`;
        }

        return `${idNamespace}${rootIdSeparator}${this.rootId}:${partSegment}`;
    }

    private getValueSeparatorForPart(part: string): string {
        const { valueSeparator } = this.getPartConfig(part);
        return valueSeparator;
    }

    getElement<T extends Element>(part: string, parent: Element | Document = this.doc): T | null {
        const isDoc = parent === this.doc;
        const partId = this.computePartId(part);

        if (isDoc) {
            // Use getElementById (no CSS-escaping needed; IDs may contain colons)
            return this.doc.getElementById(partId) as T | null;
        }

        const dataPart = toKebabCase(part);
        const escapedPartId = CSS.escape(partId);

        return (parent as Element).querySelector<T>(
            `[id="${escapedPartId}"][data-part="${dataPart}"],[id^="${escapedPartId}"][data-part="${dataPart}"]`
        );
    }

    getElements<T extends Element>(part: string, parent?: Element | Document): T[] {
        const searchScope: Element | Document =
            parent !== undefined ? parent : this.getElement('root') || this.doc;

        const dataPart = toKebabCase(part);
        const escapedPartId = CSS.escape(this.computePartId(part));
        const escapedValueSeparator = CSS.escape(this.getValueSeparatorForPart(part));

        return Array.from(
            searchScope.querySelectorAll<T>(
                `[id="${escapedPartId}"][data-part="${dataPart}"],[id^="${escapedPartId + escapedValueSeparator}"][data-part="${dataPart}"]`
            )
        );
    }

    generateRefAttributesString(part: string, value?: string): string {
        const id = this.computePartId(part, value);
        return `id="${id}" data-scope="${this.clientComponentName}" data-part="${toKebabCase(part)}"${value !== undefined ? ` data-value="${value}"` : ''}`;
    }

    setRefAttributes(element: Element, part: string, value?: string): void {
        element.setAttribute('id', this.computePartId(part, value));
        element.setAttribute('data-scope', this.clientComponentName);
        element.setAttribute('data-part', toKebabCase(part));
        if (value !== undefined) {
            element.setAttribute('data-value', value);
        }
    }

    /**
     * Re-stamps every ref'd element within `root` (root included) for a new, real `value`.
     * Scoped to this component (`data-scope`) so a nested, unrelated component's own
     * value-scoped parts aren't touched.
     *
     * Use after cloning a `<template>` (see `Template`) to make the clone represent one real
     * item/row in a single call, instead of manually recomputing
     * `id`/`data-scope`/`data-part`/`data-value` for the root and separately for every nested
     * value-scoped part (e.g. a combobox item's own `item-text`/`item-indicator`). Fully generic -
     * not combobox-specific - so it applies unmodified to any future dynamic-item scenario
     * (file-upload item previews, recurring/array form-field rows, or another primitive's own
     * async items).
     *
     * `attributes`, if given, are additionally set on the same elements `value` is applied to - for
     * primitives whose `render()` reads plain per-element data attributes rather than resolving a
     * collection item (e.g. RadioGroup's `disabled`/`invalid`, NavigationMenu's `Link` `current`).
     * A boolean value toggles a bare `data-x` attribute (e.g. `{ disabled: true }` -> `data-disabled`,
     * present only when true); a string value sets `data-x="value"` directly.
     */
    restampValue(
        root: Element,
        value: string,
        attributes?: Record<string, boolean | string>
    ): void {
        const restamp = (el: Element) => {
            const rawPart = el.getAttribute('data-part');
            if (!rawPart) return;
            const part = toCamelCase(rawPart);
            this.setRefAttributes(el, part, value);
            for (const [name, attributeValue] of Object.entries(attributes ?? {})) {
                if (typeof attributeValue === 'string') {
                    el.setAttribute(`data-${name}`, attributeValue);
                } else {
                    el.toggleAttribute(`data-${name}`, attributeValue);
                }
            }
        };

        if (
            root.getAttribute('data-scope') === this.clientComponentName &&
            root.hasAttribute('id')
        ) {
            restamp(root);
        }
        root.querySelectorAll(`[data-scope="${this.clientComponentName}"][id]`).forEach(restamp);
    }

    destroy() {
        // No-op for now; if we ever need to clean up anything, do it here.
    }
}

export function getListCollectionFromHydrationData<T extends CollectionItem>(hydrationCollection: {
    items: T[];
    itemToValueKey?: string;
    itemToStringKey?: string;
    isItemDisabledKey?: string;
    groupByKey?: string;
    groupSort?: 'asc' | 'desc' | Array<string>;
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
        groupSort: hydrationCollection.groupSort,
    });
    return collection;
}
