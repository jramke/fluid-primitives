import * as toast from '@zag-js/toast';
import { Component, Machine, normalizeProps } from '../../Client';

// Set only on a change: the machine measures a toast again on every text mutation, which renders again.
function setText(el: HTMLElement, value: unknown) {
    const text = value == null ? '' : String(value);
    if (el.textContent !== text) el.textContent = text;
}

/**
 * One toast. A `Toaster` creates it for every toast of its store, from a clone of its toast template
 * whose parts carry the id of the toast.
 */
export class Toast extends Component<toast.Props, toast.Api> {
    static componentName = 'toast';

    // TODO: swipe to dismiss (swipeDirections, data-swiping, --swipe-x/--swipe-y) once @zag-js/toast releases it

    private ghosts?: [HTMLElement, HTMLElement];
    private templateAttributes?: string[];

    initMachine(props: toast.Props): Machine<any> {
        return new Machine(toast.machine, props);
    }

    initApi() {
        return toast.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (!rootEl) return;

        this.templateAttributes ??= rootEl.getAttributeNames();
        this.spreadProps(rootEl, this.api.getRootProps());

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

        const titleEl = this.hydrator.query('title');
        if (titleEl) {
            this.spreadProps(titleEl, this.api.getTitleProps());
            setText(titleEl, this.api.title);
            titleEl.hidden = !this.api.title;
        }

        const descriptionEl = this.hydrator.query('description');
        if (descriptionEl) {
            this.spreadProps(descriptionEl, this.api.getDescriptionProps());
            setText(descriptionEl, this.api.description);
            descriptionEl.hidden = !this.api.description;
        }

        const actionTriggerEl = this.hydrator.query('actionTrigger');
        if (actionTriggerEl) {
            const action = this.machine.service.prop('action');
            this.spreadProps(actionTriggerEl, this.api.getActionTriggerProps());
            setText(actionTriggerEl, action?.label);
            actionTriggerEl.hidden = !action;
        }

        const closeTriggerEl = this.hydrator.query('closeTrigger');
        if (closeTriggerEl) {
            this.spreadProps(closeTriggerEl, this.api.getCloseTriggerProps());
            closeTriggerEl.hidden = !this.api.closable;
        }
    }

    /**
     * Swaps the content of the toast for the one of another toast template, for a toast whose type
     * changes to a type with a template of its own. The toast keeps its root element and its state,
     * and takes the attributes and the children of `templateRoot`: the root of a clone of the
     * template whose parts carry the id of this toast.
     */
    replaceContent(templateRoot: HTMLElement) {
        const rootEl = this.hydrator.query('root');
        if (!rootEl) return;

        this.templateAttributes?.forEach(name => rootEl.removeAttribute(name));
        for (const { name, value } of templateRoot.attributes) rootEl.setAttribute(name, value);
        this.templateAttributes = templateRoot.getAttributeNames();

        const children = [...templateRoot.childNodes];
        if (this.ghosts) rootEl.replaceChildren(this.ghosts[0], ...children, this.ghosts[1]);
        else rootEl.replaceChildren(...children);

        this.render();
    }
}
