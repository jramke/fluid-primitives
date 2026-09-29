import * as tabs from '@zag-js/tabs';
import { Component, Machine, normalizeProps } from '../../Client';

export class Tabs extends Component<tabs.Props, tabs.Api> {
    static componentName = 'tabs';

    initMachine(props: tabs.Props): Machine<any> {
        const [machineProps] = tabs.splitProps(props);
        return new Machine(tabs.machine, machineProps);
    }

    initApi() {
        return tabs.connect(this.machine.service, normalizeProps);
    }

    render = () => {
        const rootEl = this.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const listEl = this.query('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        this.spreadPropsByValue('trigger', ({ el, value }) =>
            this.api.getTriggerProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('content', ({ value }) => this.api.getContentProps({ value }));

        const indicatorEl = this.query('indicator');
        if (indicatorEl) {
            this.spreadProps(indicatorEl, this.api.getIndicatorProps());
        }
    };
}
