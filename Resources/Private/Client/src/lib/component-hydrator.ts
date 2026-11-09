import { partAttributePrefix, toKebabCase } from './component-name';
import { prepareNestedRootComponents } from './nested-components';

/**
 * Finds the parts of one component instance. Every part that `ui:ref` renders carries a
 * `data-<component>-<part>="<rootId>"` attribute, so a lookup needs no id and searches the whole
 * document by default. That is also what finds content that was portaled away from the root.
 */
export class ComponentHydrator {
    /** The name of the component, in camelCase. */
    componentName: string;
    /** The kebab-case form of `componentName`, as it appears in attribute names. */
    clientComponentName: string;
    /** The document the parts are searched in. */
    doc: Document;
    /** The `rootId` the component was rendered with. */
    rootId: string;

    /**
     * @param componentName - The name of the component, in camelCase.
     * @param rootId - The `rootId` the component was rendered with. Throws if it is missing.
     * @param doc - The document the parts are searched in.
     */
    constructor(componentName: string, rootId: string | undefined, doc: Document = document) {
        this.componentName = componentName;
        this.clientComponentName = toKebabCase(componentName);
        this.doc = doc;
        if (!rootId) {
            throw new Error(`Root ID is required for component hydration: ${componentName}`);
        }
        this.rootId = rootId;
    }

    private get attrPrefix(): string {
        return partAttributePrefix(this.clientComponentName);
    }

    // Keep in sync with: Classes/Utility/ComponentRefUtility.php getAttributeName()
    /** The name of the attribute that marks `part`, e.g. `data-accordion-item-trigger`. */
    attr(part: string): string {
        return this.attrPrefix + toKebabCase(part);
    }

    /** The CSS selector of every element of `part` that belongs to this instance. */
    selector(part: string): string {
        return `[${this.attr(part)}="${CSS.escape(this.rootId)}"]`;
    }

    // Keep in sync with: Classes/Utility/ComponentRefUtility.php getScopeKey()
    /** The key that identifies `part`, and optionally one `value` of it, across this instance. */
    scopeKey(part: string, value?: string): string {
        const key = `${this.clientComponentName}:${this.rootId}:${toKebabCase(part)}`;
        return value ? `${key}:${value}` : key;
    }

    /**
     * Finds the first element of `part`.
     *
     * @param part - The name of the part, in camelCase.
     * @param parent - Where to search, the whole document by default.
     * @returns The element, or `null` if there is none.
     */
    query<T extends Element = HTMLElement>(
        part: string,
        parent: Element | Document = this.doc
    ): T | null {
        return parent.querySelector<T>(this.selector(part));
    }

    /**
     * Finds every element of `part`.
     *
     * @param part - The name of the part, in camelCase.
     * @param parent - Where to search, the whole document by default.
     */
    queryAll<T extends Element = HTMLElement>(
        part: string,
        parent: Element | Document = this.doc
    ): T[] {
        return Array.from(parent.querySelectorAll<T>(this.selector(part)));
    }

    /**
     * Marks an element as `part` of this instance, like `ui:ref` does on the server. For elements
     * you create after hydration.
     *
     * @param element - The element to mark.
     * @param part - The name of the part, in camelCase.
     * @param value - Sets `data-value`, for a part that exists once per item.
     */
    stamp(element: Element, part: string, value?: string): void {
        element.setAttribute(this.attr(part), this.rootId);
        if (value !== undefined) {
            element.setAttribute('data-value', value);
        }
    }

    /** The part this component's own scope marks `el` as (kebab-case), if any. */
    private getOwnPart(el: Element): string | null {
        for (const { name, value } of el.attributes) {
            if (value === this.rootId && name.startsWith(this.attrPrefix)) {
                return name.slice(this.attrPrefix.length);
            }
        }
        return null;
    }

    /**
     * @internal
     *
     * Sets `data-value` on `root` and on every element inside it that is a part of *this*
     * component's own scope, e.g. the row of a FieldArray and its remove trigger.
     *
     * `attributes`, if given, are set on the same elements, for primitives whose `render()` reads
     * plain per-element data attributes (RadioGroup's `disabled`/`invalid`, NavigationMenu's `current`).
     * A boolean toggles a bare `data-x` attribute, a string sets `data-x="value"`.
     */
    setValue(root: Element, value: string, attributes?: Record<string, boolean | string>): void {
        const restamp = (el: Element) => {
            if (this.getOwnPart(el) === null) return;
            el.setAttribute('data-value', value);
            for (const [name, attributeValue] of Object.entries(attributes ?? {})) {
                if (typeof attributeValue === 'string') {
                    el.setAttribute(`data-${name}`, attributeValue);
                } else {
                    el.toggleAttribute(`data-${name}`, attributeValue);
                }
            }
        };

        restamp(root);
        root.querySelectorAll('*').forEach(restamp);
    }

    /**
     * @internal
     *
     * Makes a freshly cloned `<template>` item (see `Template`) one real item: {@see setValue}, then
     * gives every independent root component inside it, such as the `Field` and `Input` of a
     * FieldArray row, a root id of its own and a copy of its stencil's hydration data. Which
     * components those are comes from `window.FluidPrimitives.nestedComponents[stencilKey]`, recorded
     * by PHP, so it works for a component that renders no element of its own, such as `Dialog`'s root.
     *
     * A component keeps the id it gets here until it is destroyed. The id does not contain `value`:
     * `value` says where the item is now, and that changes (a FieldArray reindexes its rows) while a
     * Zag machine cannot change its id.
     * `mountAll()` mounts them once the clone is in the page.
     */
    restampValue(
        root: Element,
        value: string,
        stencilKey: string,
        attributes?: Record<string, boolean | string>
    ): void {
        this.setValue(root, value, attributes);
        prepareNestedRootComponents(stencilKey, root, value);
    }

    /** Nothing to release yet, it exists so every instance can be destroyed alike. */
    destroy() {
        // No-op for now; if we ever need to clean up anything, do it here.
    }
}
