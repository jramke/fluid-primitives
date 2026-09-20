import { toKebabCase, type ComponentHydrator } from './hydration';

export interface TemplateOptions {
    /**
     * Restamps the clone's root and every nested value-scoped ref'd element (id/data-scope/
     * data-part/data-value) for this value, right after cloning, AND prepares any nested,
     * independent root component the clone happens to compose (e.g. a `Field`+`Input`) so it's
     * ready for `mountAll()` too - see `ComponentHydrator.restampValue`, and this class's own
     * `componentNames`. Omit for a template with no per-instance value/identity concept.
     */
    value?: string;
    /**
     * Data attributes to set alongside `value` - a boolean toggles a bare `data-x` attribute
     * (e.g. `{ disabled: true }` -> `data-disabled`, present only when true, matching the
     * `hasAttribute('data-x')` convention every primitive's `render()` loop already reads boolean
     * flags by - e.g. RadioGroup's `disabled`/`invalid`, NavigationMenu's `Link` `current`), while a
     * string sets `data-x="value"` directly. Not a fixed prop list - pass whatever attributes the
     * primitive/part you're targeting expects. Applied to the same elements `value` is, and only
     * takes effect when `value` is also given.
     */
    attributes?: Record<string, boolean | string>;
}

/**
 * Clones a `<template>` part's content, requiring it have exactly one root element - fails fast
 * with a clear error instead of silently dropping extra siblings or producing an unusable
 * instance. IS a DocumentFragment (not just a wrapper around one), so it's directly appendable;
 * `.root` stays a valid element reference even after insertion drains the fragment itself (the
 * DOM moves a fragment's children into their new parent on append, leaving the fragment empty).
 *
 * Not tied to Combobox or any Zag machine - reusable for any "clone a `<template>`, populate it
 * with data that doesn't exist yet at server-render time" use case (async search results,
 * file-upload item previews, recurring/array form-field rows).
 */
export class Template extends DocumentFragment {
    readonly root: HTMLElement;
    /**
     * Distinct client component names of any nested, independent root components found and
     * prepared inside this clone (see `ComponentHydrator.restampValue`) - empty when `options.value`
     * was omitted, or the template contains none (the common case, e.g. `FileUpload`'s own item
     * previews). Call the matching `mountAll()`s for each of these after inserting the clone into
     * the document - per `mountAll`'s own docblock, that's exactly what it's for ("after
     * lazily-inserted DOM adds new instances").
     */
    readonly componentNames: string[] = [];

    constructor(hydrator: ComponentHydrator, part: string, options: TemplateOptions = {}) {
        super();

        const templateEl = hydrator.getElement<HTMLTemplateElement>(part);
        if (!templateEl) {
            throw new Error(`Template: no <template> found for part "${part}".`);
        }

        const content = templateEl.content.cloneNode(true) as DocumentFragment;
        if (content.children.length !== 1) {
            throw new Error(
                `Template: "${part}" must have exactly one root element, found ${content.children.length}.`
            );
        }

        this.append(content);
        this.root = this.firstElementChild as HTMLElement;

        if (options.value !== undefined) {
            this.componentNames = hydrator.restampValue(
                this.root,
                options.value,
                templateEl.id,
                options.attributes
            );
        }
    }

    getElement<T extends Element>(part: string): T | null {
        return this.querySelector<T>(`[data-part="${toKebabCase(part)}"]`);
    }

    getElements<T extends Element>(part: string): T[] {
        return Array.from(this.querySelectorAll<T>(`[data-part="${toKebabCase(part)}"]`));
    }
}
