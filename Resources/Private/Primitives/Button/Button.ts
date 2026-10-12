import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/button.connect';
import { machine } from './src/button.machine';
import { splitProps } from './src/button.props';
import type { ButtonApi, ButtonProps, ButtonState } from './src/button.types';

export class Button extends Component<ButtonProps, ButtonApi> {
    static componentName = 'button';

    initMachine(props: ButtonProps): Machine<any> {
        const [machineProps] = splitProps(props);
        return new Machine(machine, machineProps);
    }

    initApi() {
        return connect(this.machine, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        this.spreadPropsByValue('indicator', ({ value }) =>
            this.api.getIndicatorProps({ state: value as ButtonState })
        );
    }
}
