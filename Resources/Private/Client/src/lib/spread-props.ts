import { spreadProps as zagSpreadProps, type Attrs } from '@zag-js/vanilla';

/**
 * Applies the attributes and event handlers of a part's props to its element.
 *
 * It throws on a `children` prop: Zag would write it with `innerHTML`. Set `textContent` instead.
 *
 * @param node - The element to update.
 * @param attrs - The props of a part, as returned by a machine's `connect()`.
 * @param scopeKey - Keeps the cleanup of one component apart from another's on the same element.
 * @returns A function that removes what was applied.
 */
export function spreadProps(node: HTMLElement, attrs: Attrs, scopeKey?: string) {
    if ('children' in attrs) {
        throw new Error(
            'spreadProps does not support the "children" prop, it would be written with innerHTML. Set textContent instead.'
        );
    }

    return zagSpreadProps(node, attrs, scopeKey);
}

const lastValues = new WeakMap<HTMLElement, unknown>();

/**
 * Like `spreadProps` for a text control, but it does not assign a `value` the control already
 * shows. Any assignment, even of the same text, makes the browser treat the value as set by a
 * script, and it stops reporting `tooShort` (`minlength`) for such a value. These controls mirror
 * what the user typed into their machine, so that assignment would happen on every keystroke.
 *
 * As in `spreadProps`, a value is only considered when it changed since the last call: the control
 * can be one event ahead of the machine, and must not be reset to the machine's older value.
 */
export function spreadTextControlProps(
    node: HTMLInputElement | HTMLTextAreaElement,
    attrs: Attrs,
    scopeKey?: string
) {
    const { value, ...rest } = attrs;
    const cleanup = spreadProps(node, rest, scopeKey);

    if (!lastValues.has(node) || lastValues.get(node) !== value) {
        const next = value == null ? '' : String(value);
        if (node.value !== next) node.value = next;
    }
    lastValues.set(node, value);

    return cleanup;
}
