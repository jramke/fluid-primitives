/**
 * Standalone, machine-agnostic helper for cloning an `HTMLTemplateElement`'s content and finding
 * its `ui:ref`'d elements by `data-part`, scoped to that one clone (not `getElementById`, since a
 * not-yet-inserted - or intentionally id-less - clone has no page-wide-unique ids to look up by).
 *
 * Not tied to Combobox or any Zag machine - reusable for any "clone a `<template>`, populate it
 * with data that doesn't exist yet at server-render time" use case (async search results,
 * file-upload item previews, recurring/array form-field rows).
 */
export class TemplateFragment {
    constructor(private fragment: DocumentFragment) {}

    getElement<T extends Element>(part: string): T | null {
        return this.fragment.querySelector<T>(`[data-part="${part}"]`);
    }

    getElements<T extends Element>(part: string): T[] {
        return Array.from(this.fragment.querySelectorAll<T>(`[data-part="${part}"]`));
    }

    /** The template's single root element, if it has exactly one - typically the ui:ref'd item wrapper itself. */
    getRootElement<T extends Element>(): T | null {
        return this.fragment.firstElementChild as T | null;
    }

    toFragment(): DocumentFragment {
        return this.fragment;
    }
}

export function createTemplateInstance(templateEl: HTMLTemplateElement): TemplateFragment {
    return new TemplateFragment(templateEl.content.cloneNode(true) as DocumentFragment);
}
