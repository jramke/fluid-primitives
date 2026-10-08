import type { ComponentHydrator } from './hydration';

/** The options of a {@link Template}. */
export interface TemplateOptions {
    /**
     * Makes the clone represent one real item: sets `data-value` on its root and on every part
     * inside it that exists once per item, and prepares independent root components inside it, such
     * as a `Field` with an `Input`, so `mountAll()` can mount them. Leave it out for a template
     * without a per-item value.
     */
    value?: string;
    /**
     * Data attributes to set next to `value`, on the same elements. A boolean toggles a bare
     * `data-x` attribute, `{ disabled: true }` sets `data-disabled` and `false` removes it. A string
     * sets `data-x="value"`. Only has an effect together with `value`.
     */
    attributes?: Record<string, boolean | string>;
}

/**
 * Clones the content of a `<template>` part, which must have exactly one root element, and throws
 * otherwise. It is a `DocumentFragment`, so you can append it directly: `root` stays a valid
 * reference to the element after the fragment has been emptied by the insertion.
 *
 * It works for any "clone a `<template>` and fill it with data that doesn't exist at server render
 * time" case, such as async search results, file upload previews or the rows of a FieldArray.
 */
export class Template extends DocumentFragment {
    /** The one root element of the clone. */
    readonly root: HTMLElement;
    /**
     * The client names of the independent root components found inside the clone, empty without
     * `options.value` or when there are none. Call their `mountAll()` after you insert the clone into
     * the document.
     */
    readonly componentNames: string[] = [];

    /**
     * @param hydrator - The hydrator of the component that owns the `<template>`.
     * @param part - The name of the `<template>` part.
     * @param options - See `TemplateOptions`.
     */
    constructor(
        private readonly hydrator: ComponentHydrator,
        part: string,
        options: TemplateOptions = {}
    ) {
        super();

        const templateEl = hydrator.query<HTMLTemplateElement>(part);
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
                hydrator.scopeKey(part),
                options.attributes
            );
        }
    }

    /** Finds the first element of `part` inside the clone. */
    query<T extends Element = HTMLElement>(part: string): T | null {
        return this.querySelector<T>(this.hydrator.selector(part));
    }

    /** Finds every element of `part` inside the clone. */
    queryAll<T extends Element = HTMLElement>(part: string): T[] {
        return Array.from(this.querySelectorAll<T>(this.hydrator.selector(part)));
    }
}
