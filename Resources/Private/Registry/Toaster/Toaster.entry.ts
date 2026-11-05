import { mount } from 'fluid-primitives';
import { Toaster } from 'fluid-primitives/toaster';

// The page layout renders <ui:toaster rootId="toaster" /> on every page.
export const toast = mount('ui:toaster', 'toaster', ({ props }) =>
    new Toaster(props).init()
)!.store;
