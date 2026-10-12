import * as toggle from '@zag-js/toggle';
import type { CommonProperties } from '@zag-js/types';
import { Component, Machine, normalizeProps } from '../../Client';

// Zag's toggle declares no `id`, so its `splitProps()` would drop the one the parts are found by.
// Drop this and use `splitProps()` once https://github.com/chakra-ui/zag/pull/3438 is released.
export type ToggleProps = toggle.Props & CommonProperties;

export class Toggle extends Component<ToggleProps, toggle.Api> {
    static componentName = 'toggle';

    initMachine(props: ToggleProps): Machine<any> {
        return new Machine(toggle.machine, props);
    }

    initApi() {
        return toggle.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
