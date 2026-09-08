import * as tooltip from '@zag-js/tooltip';
import { Component, Machine, normalizeProps } from '../../Client';

export class Tooltip extends Component<tooltip.Props, tooltip.Api> {
    static name = 'tooltip';

    initMachine(props: tooltip.Props): Machine<any> {
        return new Machine(tooltip.machine, {
            interactive: true,
            ...props,
            positioning: {
                placement: 'top',
                gutter: 6,
                ...props.positioning,
            },
        });
    }

    initApi() {
        return tooltip.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.spreadPropsByOptionalValue('trigger', ({ value }) => this.api.getTriggerProps({ value }));

        const positionerEl = this.getElement('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.getElement('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.getElement('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());
    }
}
