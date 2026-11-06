import { mount } from 'fluid-primitives';
import { Toaster } from 'fluid-primitives/toaster';

// The toaster your page layout renders as <ui:toaster rootId="toaster" />, mounted once.
const toaster = () => mount('ui:toaster', 'toaster', ({ props }) => new Toaster(props).init());

toaster();

/**
 * The store of the toaster, to create toasts from any script: `toast.success({ title: 'Saved' })`.
 * It finds the toaster on every call, so it still works after the toaster mounts again. On a page
 * without one it warns, and its methods do nothing and return `undefined`.
 */
export const toast = new Proxy({} as Toaster['store'], {
    get(_, key) {
        const store = toaster()?.store;
        if (!store) return () => undefined;
        const value = store[key as keyof typeof store];
        return typeof value === 'function' ? value.bind(store) : value;
    },
});
