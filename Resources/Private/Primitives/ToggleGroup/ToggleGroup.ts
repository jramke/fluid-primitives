import * as toggleGroup from '@zag-js/toggle-group';
import { Component, Machine, normalizeProps } from '../../Client';

export class ToggleGroup extends Component<toggleGroup.Props, toggleGroup.Api> {
    static componentName = 'toggleGroup';

    initMachine(props: toggleGroup.Props): Machine<any> {
        const [machineProps] = toggleGroup.splitProps(props);
        return new Machine(toggleGroup.machine, machineProps);
    }

    initApi() {
        return toggleGroup.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        this.spreadPropsByValue('item', ({ el, value }) =>
            this.api.getItemProps({ value, disabled: el.hasAttribute('data-disabled') })
        );
    }
}
