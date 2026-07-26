import { Idiomorph, type IdiomorphOptions } from 'idiomorph';
import { destroyComponentsWithin } from './hydration';

export type { IdiomorphCallbacks, IdiomorphOptions } from 'idiomorph';

/**
 * Morphs `target` to match `html` using idiomorph
 * (https://github.com/bigskysoftware/idiomorph), automatically destroying any
 * Fluid Primitives component instances rooted in nodes idiomorph removes
 * along the way (via {@see destroyComponentsWithin}).
 *
 * Pass `hydrate` to (re)hydrate whatever ended up new in the DOM once the
 * morph is done - typically one or more `mount()` calls, which are safe to
 * call repeatedly and only instantiate components that are not already
 * mounted.
 *
 * This is the client-side building block for "insert/update a
 * server-rendered fragment" interactions - lazily-added recurring field rows,
 * combobox search results, file-upload previews, and similar cases. Pair it
 * with `Jramke\FluidPrimitives\Service\ComponentFragmentRenderer` (and
 * `ComponentFragmentTrait` for a one-line controller action) on the PHP side
 * to render the fragment in the first place.
 *
 * ## Example
 *
 * ```typescript
 * const response = await fetch(`/render-person-row?index=${index}`);
 * const { html, hydrationData } = await response.json();
 * mergeHydrationData(hydrationData);
 *
 * morph(placeholderEl, html, () => {
 *     mount('field', ({ props }) => { const f = new Field(props); f.init(); return f; });
 *     mount('number-input', ({ props }) => { const n = new NumberInput(props); n.init(); return n; });
 * });
 * ```
 */
export function morph(
    target: Element,
    html: string,
    hydrate?: () => void,
    options: IdiomorphOptions = {}
): void {
    Idiomorph.morph(target, html, {
        ...options,
        callbacks: {
            ...options.callbacks,
            beforeNodeRemoved(node) {
                if (node instanceof Element) {
                    destroyComponentsWithin(node);
                }
                return options.callbacks?.beforeNodeRemoved?.(node) ?? true;
            },
        },
    });

    hydrate?.();
}
