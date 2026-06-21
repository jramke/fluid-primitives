import * as collapsible from '@zag-js/collapsible';
import { Component, Machine, normalizeProps } from '../../Client';

export class Collapsible extends Component<collapsible.Props, collapsible.Api> {
    static name = 'collapsible';

    initMachine(props: collapsible.Props): Machine<any> {
        return new Machine(collapsible.machine, props);
    }

    initApi() {
        return collapsible.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const triggerEls = this.getElements('trigger');
        triggerEls.forEach(triggerEl => {
            this.spreadProps(triggerEl, this.api.getTriggerProps());
        });

        const openIndicatorEls = this.getElements('indicator-open');
        openIndicatorEls.forEach(openIndicatorEl => {
            this.spreadProps(
                openIndicatorEl,
                normalizeProps.element({
                    hidden: !this.api.open,
                    'data-state': 'open',
                })
            );
        });

        const closedIndicatorEls = this.getElements('indicator-closed');
        closedIndicatorEls.forEach(closedIndicatorEl => {
            this.spreadProps(
                closedIndicatorEl,
                normalizeProps.element({
                    hidden: this.api.open,
                    'data-state': 'closed',
                })
            );
        });

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());
    }
}
