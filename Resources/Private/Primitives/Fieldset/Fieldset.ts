import * as fieldset from '@zag-js/fieldset';
import { Component, Machine, normalizeProps } from '../../Client';

export class Fieldset extends Component<fieldset.Props, fieldset.Api> {
    static componentName = 'fieldset';

    initMachine(props: fieldset.Props): Machine<any> {
        const [machineProps] = fieldset.splitProps(props);
        return new Machine(fieldset.machine, machineProps);
    }

    initApi() {
        return fieldset.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const legendEl = this.hydrator.query('legend');
        if (legendEl) this.spreadProps(legendEl, this.api.getLegendProps());

        const helperTextEl = this.hydrator.query('helperText');
        if (helperTextEl) this.spreadProps(helperTextEl, this.api.getHelperTextProps());

        const errorTextEl = this.hydrator.query('errorText');
        if (errorTextEl) this.spreadProps(errorTextEl, this.api.getErrorTextProps());
    }
}
