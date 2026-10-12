// A portal inside a `<template>` stencil is rendered in place, wrapped in `[data-portal="<name>"]`,
// because the page's portal buffer is flushed once at page load (see PortalViewHelper). A clone
// moves it to where the portal wants to go: after the `ui:portalContainer` of its name, else, for the
// default portal, to the end of `<body>`. A placeholder stays in the clone, which is how the portal
// is found and removed again with its row.
const wrappersByOrigin = new WeakMap<Element, Element>();

/**
 * @internal
 *
 * Moves every `[data-portal]` wrapper inside `root` to its destination. A wrapper whose container
 * does not exist stays where it is, unless it is the default portal, which goes to the end of the body.
 */
export function movePortals(root: Element | DocumentFragment, doc: Document = document): void {
    for (const wrapper of root.querySelectorAll<HTMLElement>('[data-portal]')) {
        const name = wrapper.dataset.portal || 'default';
        const container = Array.from(
            doc.querySelectorAll<HTMLElement>('template[data-portal-container]')
        ).find(el => el.dataset.portalContainer === name);
        if (!container && name !== 'default') continue;

        const origin = doc.createElement('template');
        origin.setAttribute('data-portal-origin', '');
        wrapper.replaceWith(origin);
        wrappersByOrigin.set(origin, wrapper);

        if (container) container.after(wrapper);
        else doc.body.append(wrapper);
    }
}

/**
 * @internal
 *
 * Removes the portals that `root` moved out of itself, call it before removing `root`.
 */
export function removePortalsWithin(root: Element): void {
    for (const origin of root.querySelectorAll('template[data-portal-origin]')) {
        const wrapper = wrappersByOrigin.get(origin);
        if (!wrapper) continue;

        // a portal inside a portal left its own placeholder in the wrapper
        removePortalsWithin(wrapper);
        wrapper.remove();
    }
}
