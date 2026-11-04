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

    initMachine(props: toast.Props): Machine<any> {
        return new Machine(toast.machine, props);
    }

    initApi() {
        return toast.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (!rootEl) return;

        this.spreadProps(rootEl, this.api.getRootProps());

        if (!this.ghosts) {
            this.ghosts = [this.doc.createElement('div'), this.doc.createElement('div')];
            rootEl.prepend(this.ghosts[0]);
            rootEl.append(this.ghosts[1]);
        }
        this.spreadProps(this.ghosts[0], this.api.getGhostBeforeProps());
        this.spreadProps(this.ghosts[1], this.api.getGhostAfterProps());

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
}
