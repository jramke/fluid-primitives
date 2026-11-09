// What makes `mountAll()` follow the page: one MutationObserver mounts the components of markup that
// arrives and destroys the instances whose markup left. The elements each instance rendered onto
// are tracked, so a removal looks at what was removed, not at every instance of the page.
import type { MountRecord } from '../types';
import type { Component } from './component';
import { readHydrationDataBlocks } from './hydration';
import { destroyRecords, mountArrived } from './mount';
import { removePortalsWithin } from './portal';

/** @internal Remembers that `instance` rendered onto `el`, see {@link instancesRenderedInside}. */
export function trackRenderedElement(el: Element, instance: Component<unknown, unknown>) {
    const renderers =
        window.FluidPrimitives && (window.FluidPrimitives.renderers ??= new WeakMap());
    if (!renderers) return;
    let instances = renderers.get(el);
    if (!instances) renderers.set(el, (instances = new Set()));
    instances.add(instance);
}

/** @internal */
export function untrackRenderedElement(el: Element, instance: Component<unknown, unknown>) {
    window.FluidPrimitives?.renderers?.get(el)?.delete(instance);
}

/** @internal The mounted instances that rendered onto `elements` or anything inside them. */
export function instancesRenderedInside(elements: Iterable<Element>): Set<MountRecord> {
    const found = new Set<MountRecord>();
    const renderers = window.FluidPrimitives?.renderers;
    const records = window.FluidPrimitives?.mountRecords;
    if (!renderers || !records) return found;
    for (const element of elements) {
        for (const el of [element, ...element.querySelectorAll('*')]) {
            for (const instance of renderers.get(el) ?? []) {
                const record = records.get(instance);
                if (record) found.add(record);
            }
        }
    }
    return found;
}

let pageObserver: MutationObserver | undefined;

/**
 * @internal
 *
 * Starts watching the page, once per copy of this module. It watches the whole document, not the
 * body: Turbo Drive replaces the body element, and merges the hydration data of the next page into
 * the head.
 */
export function observePage() {
    pageObserver ??= new MutationObserver(records => {
        const removed = new Set<Element>();
        const added = new Set<Element>();
        for (const record of records) {
            record.removedNodes.forEach(node => node instanceof Element && removed.add(node));
            record.addedNodes.forEach(node => node instanceof Element && added.add(node));
        }

        // A node that was moved, e.g. into a portal, is removed and added in the same batch.
        const gone = [...removed].filter(el => !el.isConnected);
        if (gone.length > 0) destroyLater(gone);

        const arrived = [...added].filter(el => el.isConnected);
        if (arrived.length > 0) {
            readHydrationDataBlocks();
            mountArrived(arrived);
        }
    });
    pageObserver.observe(document.documentElement, { childList: true, subtree: true });
}

/**
 * Destroys the instances that left the page with `gone` a task later, when they are still gone: an
 * element that is detached and put back across an `await`, as Turbo does with a
 * `data-turbo-permanent` element, keeps its instance and its state. The hydration data stays, the
 * markup can come back, e.g. from the cache of Turbo.
 */
function destroyLater(gone: Element[]) {
    const candidates = [...instancesRenderedInside(gone)].filter(({ instance }) =>
        instance.isRenderedOnlyInside()
    );
    const hasPortals = gone.some(el => el.querySelector('template[data-portal-origin]'));
    if (candidates.length === 0 && !hasPortals) return;

    setTimeout(() => {
        // the portals of an element still gone; removing them is a change the observer sees, so
        // a component with its content in a portal is destroyed with the next round
        gone.filter(el => !el.isConnected).forEach(removePortalsWithin);
        destroyRecords(
            candidates.filter(({ instance }) => instance.isRenderedOnlyInside()),
            { forgetHydrationData: false }
        );
    });
}
