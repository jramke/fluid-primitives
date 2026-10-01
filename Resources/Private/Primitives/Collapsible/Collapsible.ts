import * as collapsible from '@zag-js/collapsible';
import { Component, Machine, normalizeProps } from '../../Client';

export class Collapsible extends Component<collapsible.Props, collapsible.Api> {
    static componentName = 'collapsible';

    initMachine(props: collapsible.Props): Machine<any> {
        const [machineProps] = collapsible.splitProps(props);
        return new Machine(collapsible.machine, machineProps);
    }

    initApi() {
        return collapsible.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const triggerEls = this.hydrator.queryAll('trigger');
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

        const contentEl = this.hydrator.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());
    }
}
