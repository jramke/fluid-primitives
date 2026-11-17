import { createMachine } from '@zag-js/core';
import { createLiveRegion } from '@zag-js/live-region';
import * as dom from './field-array.dom';
import type { FieldArraySchema } from './field-array.types';

// FieldArray has no upstream Zag.js package to wrap (nothing to port - this is a hand-rolled
// Component, unlike Field/Form). It still needs *some* machine, since `Component.init()`
// unconditionally calls `machine.subscribe()`/`machine.start()` - most of this trivial,
// state-less machine exists only to satisfy that contract; `Component.refresh()` (via
// `machine.notify()`) is what actually triggers a re-render. The one piece of real behavior it
// does own is the row announcer, via the same `refs`/`effects` lifecycle Textarea/Input's own
// `manageLiveRegion` effect uses. It speaks through Zag's shared polite live region (see
// `@zag-js/live-region`), which Zag keeps inside the root of the component - `machine.stop()` (called from
// `Component.destroy()`) runs this effect's cleanup automatically, so `FieldArray.ts` needs no
// `destroy()` override for it. Everything else (`append`/`remove`) operates directly on
// `Component`-level DOM helpers (`hydrator`) instead, since managing
// an open-ended collection of rows doesn't map onto a Zag machine's usual fixed set of known-id
// parts the way a single field's or form's own state does.
export const machine = createMachine<FieldArraySchema>({
    initialState() {
        return 'idle';
    },
    context() {
        return {};
    },
    refs() {
        return {
            liveRegion: null,
        };
    },
    effects: ['manageLiveRegion'],
    states: {
        idle: {},
    },
    implementations: {
        effects: {
            manageLiveRegion({ refs, scope }) {
                // inside the component, as a modal dialog around it would hide a region in the body from screen readers
                const liveRegion = createLiveRegion({
                    level: 'polite',
                    root: () => dom.getRootEl(scope),
                });
                refs.set('liveRegion', liveRegion);

                return () => {
                    liveRegion.destroy();
                };
            },
        },
    },
});
