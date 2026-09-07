import { spreadProps as zagSpreadProps, type Attrs } from '@zag-js/vanilla';

// TODO: change back to using @zag-js/vanilla's spreadProps once this PR is merged: https://github.com/chakra-ui/zag/pull/3328

/**
 * @zag-js/vanilla's `spreadProps` applies the `style` prop by serializing it to a single CSS
 * string and setting it via `node.setAttribute('style', ...)` whenever that string changes
 * between renders. That replaces the *entire* style attribute, which wipes out any inline styles
 * another piece of code set directly on the same element outside of Zag's prop system - e.g.
 * @zag-js/dismissable's layer-stack, which writes `--layer-index`/`--nested-layer-count`/
 * `--z-index` straight onto Dialog's positioner (and content) via `element.style.setProperty(...)`.
 *
 * Dialog's positioner is hit hardest because its `style` prop toggles `pointer-events: none` on
 * open/close, so the very same state transition that makes the layer-stack tag the element also
 * changes the serialized style string and triggers a full attribute overwrite right after.
 * Content/backdrop mostly escape this: content's style only depends on the static `modal` prop
 * (so the string rarely changes) and backdrop has no `style` prop at all.
 *
 * The fix: manage `style` at the CSS-property level instead of the attribute level - like the
 * React adapter does via the DOM's style object - so only the specific properties Zag computed
 * are ever added or removed, and anything else already on the element's inline style is left
 * alone. Filed upstream as a fix for @zag-js/vanilla; this is the local patch until that lands.
 */

const prevManagedStyles = new WeakMap<Element, Map<string, Set<string>>>();

function parseStyleString(style: string): Map<string, string> {
    const result = new Map<string, string>();
    for (const decl of style.split(';')) {
        const separator = decl.indexOf(':');
        if (separator === -1) continue;
        const prop = decl.slice(0, separator).trim();
        const value = decl.slice(separator + 1).trim();
        if (prop) result.set(prop, value);
    }
    return result;
}

function applyManagedStyle(node: Element, style: unknown, scopeKey: string): void {
    if (!('style' in node)) return;
    const declaration = (node as HTMLElement).style;

    const next = parseStyleString(typeof style === 'string' ? style : '');

    let machineMap = prevManagedStyles.get(node);
    if (!machineMap) {
        machineMap = new Map();
        prevManagedStyles.set(node, machineMap);
    }
    const prevManaged = machineMap.get(scopeKey) ?? new Set<string>();

    for (const prop of prevManaged) {
        if (!next.has(prop)) declaration.removeProperty(prop);
    }
    for (const [prop, value] of next) {
        declaration.setProperty(prop, value);
    }

    machineMap.set(scopeKey, new Set(next.keys()));
}

export function spreadProps(node: Element, attrs: Attrs, machineId?: string): () => void {
    if ('style' in attrs) {
        const { style, ...rest } = attrs;
        applyManagedStyle(node, style, machineId ?? 'default');
        return zagSpreadProps(node, rest, machineId);
    }
    return zagSpreadProps(node, attrs, machineId);
}
