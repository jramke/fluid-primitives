import { toKebabCase, type ComponentHydrator } from './hydration';

export interface TemplateOptions {
    /**
     * Restamps the clone's root and every nested value-scoped ref'd element (id/data-scope/
     * data-part/data-value) for this value, right after cloning - see
     * `ComponentHydrator.restampValue`. Omit for a template with no per-instance value/identity
     * concept.
     */
    value?: string;
    /**
     * Boolean data attributes to set alongside `value` (e.g. `{ disabled: true }` -> a bare
     * `data-disabled` attribute, present only when true) - matches the `hasAttribute('data-x')`
     * convention every primitive's `render()` loop already reads flags by (e.g. RadioGroup's
     * `disabled`/`invalid`, NavigationMenu's `Link` `current`). Not a fixed prop list - pass
     * whatever flags the primitive/part you're targeting expects. Applied to the same elements
     * `value` is, and only takes effect when `value` is also given.
     */
    flags?: Record<string, boolean>;
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
            hydrator.restampValue(this.root, options.value, options.flags);
        }
    }

    getElement<T extends Element>(part: string): T | null {
        return this.querySelector<T>(`[data-part="${toKebabCase(part)}"]`);
    }

    getElements<T extends Element>(part: string): T[] {
        return Array.from(this.querySelectorAll<T>(`[data-part="${toKebabCase(part)}"]`));
    }
}
