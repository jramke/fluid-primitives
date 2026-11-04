import { getComponentInstance, mount } from 'fluid-primitives';
import { Toaster } from 'fluid-primitives/toaster';

// The page layout renders <ui:toaster rootId="toaster" /> on every page. A second copy of this module, such as the
// one Vite's dev server loads through the symlinked path of an extension, takes the toaster that is already mounted.
export const toast = (
    getComponentInstance<Toaster>('ui:toaster', 'toaster') ??
    mount('ui:toaster', 'toaster', ({ props }) => new Toaster(props).init())!
).store;
