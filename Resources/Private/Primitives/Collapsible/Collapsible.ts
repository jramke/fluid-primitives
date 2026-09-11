import * as collapsible from '@zag-js/collapsible';
import { Component, Machine, normalizeProps } from '../../Client';

export class Collapsible extends Component<collapsible.Props, collapsible.Api> {
    static componentName = 'collapsible';

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

        this.spreadPropsByValue('indicator', ({ value }) => {
            const isActive = (value === 'open') === this.api.open;
            return normalizeProps.element({
                hidden: isActive ? undefined : true,
                'data-state': value,
            });
        });

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());
    }
}
