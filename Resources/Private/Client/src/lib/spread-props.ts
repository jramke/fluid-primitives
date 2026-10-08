import { spreadProps, type Attrs } from '@zag-js/vanilla';

export { spreadProps };

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
    scopeKey: string
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
