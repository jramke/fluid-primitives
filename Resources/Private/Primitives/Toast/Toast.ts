import * as toast from '@zag-js/toast';
import { Component, Machine, normalizeProps } from '../../Client';

// Only on a change: the machine measures a toast again on every mutation of its content, which renders again.
function setText(el: HTMLElement, text: string) {
    if (el.textContent !== text || el.children.length > 0) el.textContent = text;
}

function setChildren(el: HTMLElement, nodes: Node[]) {
    const same =
        el.childNodes.length === nodes.length &&
        nodes.every((node, i) => el.childNodes[i] === node);
    if (!same) el.replaceChildren(...nodes);
}

function hasContent(nodes: Node[]) {
    return nodes.some(node => node.nodeType === Node.ELEMENT_NODE || node.textContent?.trim());
}

/**
 * One toast. A `Toaster` creates it for every toast of its store, from a clone of its toast template
 * whose parts carry the id of the toast.
 *
 * A type change can swap every part except the root for the ones of another template (see
 * `replaceContent()`), so a subclass queries its parts in `render()` and doesn't keep them.
 */
export class Toast extends Component<toast.Props, toast.Api> {
    static componentName = 'toast';

    // TODO: swipe to dismiss (swipeDirections, data-swiping, --swipe-x/--swipe-y) once @zag-js/toast releases it

    private ghosts?: [HTMLElement, HTMLElement];
    // what the template put into a part, shown while the toast has no value for it
    private readonly templateContent = new WeakMap<HTMLElement, Node[]>();

    initMachine(props: toast.Props): Machine<any> {
        return new Machine(toast.machine, props);
    }

    initApi() {
        return toast.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (!rootEl) return;

        const titleEl = this.hydrator.query('title');
        const hasTitle = !!titleEl && this.fill(titleEl, this.api.getTitleProps(), this.api.title);
        const descriptionEl = this.hydrator.query('description');
        const hasDescription =
            !!descriptionEl &&
            this.fill(descriptionEl, this.api.getDescriptionProps(), this.api.description);

        // Zag links the title and the description only when they come from the toast, not from the template
        this.spreadProps(rootEl, {
            ...this.api.getRootProps(),
            'aria-labelledby': hasTitle ? titleEl?.id : undefined,
            'aria-describedby': hasDescription ? descriptionEl?.id : undefined,
        });

        if (!this.ghosts) {
            this.ghosts = [this.doc.createElement('div'), this.doc.createElement('div')];
            rootEl.prepend(this.ghosts[0]);
            rootEl.append(this.ghosts[1]);
        }
        this.spreadProps(this.ghosts[0], this.api.getGhostBeforeProps());
        this.spreadProps(this.ghosts[1], this.api.getGhostAfterProps());

        this.spreadPropsByValue('indicator', ({ value }) => ({
            hidden: value === this.api.type ? undefined : true,
        }));

        const actionTriggerEl = this.hydrator.query('actionTrigger');
        if (actionTriggerEl) {
            const action = this.machine.service.prop('action');
            this.fill(actionTriggerEl, this.api.getActionTriggerProps(), action?.label);
            actionTriggerEl.hidden = !action;
        }

        const closeTriggerEl = this.hydrator.query('closeTrigger');
        if (closeTriggerEl) {
            this.spreadProps(closeTriggerEl, this.api.getCloseTriggerProps());
            closeTriggerEl.hidden = !this.api.closable;
        }
    }

    // Shows the text of the toast, or what the template put into the part while the toast has none.
    // Hides the part when both are empty, and returns whether it shows something.
    private fill(
        el: HTMLElement,
        props: Parameters<Toast['spreadProps']>[1],
        value: unknown
    ): boolean {
        this.spreadProps(el, props);
        if (!this.templateContent.has(el)) this.templateContent.set(el, [...el.childNodes]);
        const text = value == null ? '' : String(value);
        const fallback = this.templateContent.get(el)!;

        if (text) setText(el, text);
        else setChildren(el, fallback);

        el.hidden = !text && !hasContent(fallback);
        return !el.hidden;
    }

    /**
     * Swaps the content of the toast for the one of another toast template, for a toast whose type
     * changes to a type with a template of its own. The toast keeps its root element and its state,
     * and takes the attributes and the children of `templateRoot`: the root of a clone of the
     * template whose parts carry the id of this toast. Focus inside the toast moves to the same part
     * of the new content, or to the root.
     *
     * Every part except the root is new afterwards. Override it to animate the swap, and call
     * `super.replaceContent()` inside.
     */
    replaceContent(templateRoot: HTMLElement) {
        const rootEl = this.hydrator.query('root');
        if (!rootEl) return;

        const active = this.doc.activeElement;
        const hadFocus = active !== rootEl && rootEl.contains(active);
        const focusedPart = hadFocus
            ? (['actionTrigger', 'closeTrigger'] as const).find(part =>
                  this.hydrator.query(part)?.contains(active)
              )
            : undefined;

        // removes what the machine set and forgets it, so render() sets all of it again on top of the new template
        this.spreadProps(rootEl, {});
        for (const name of rootEl.getAttributeNames()) rootEl.removeAttribute(name);
        for (const { name, value } of templateRoot.attributes) rootEl.setAttribute(name, value);

        const children = [...templateRoot.childNodes];
        if (this.ghosts) rootEl.replaceChildren(this.ghosts[0], ...children, this.ghosts[1]);
        else rootEl.replaceChildren(...children);

        this.render();

        if (hadFocus) {
            const target = focusedPart && this.hydrator.query(focusedPart);
            (target && !target.hidden ? target : rootEl).focus();
        }
    }
}
