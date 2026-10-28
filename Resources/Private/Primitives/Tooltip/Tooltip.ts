import * as tooltip from '@zag-js/tooltip';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';

// The server sends `positioning` as the JSON of its Positioning DTO, where `placement` and `strategy` are
// plain strings - this converter just tells TS what it actually is (a real @zag-js/popper
// PositioningOptions object) rather than transforming the value itself.
const tooltipPropConverters = {
    positioning: (positioning: tooltip.Props['positioning']) => positioning,
} satisfies ClientPropConverterMap;

registerClientPropConverters('tooltip', tooltipPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        tooltip: ConverterMachineProps<typeof tooltipPropConverters>;
    }
}

export class Tooltip extends Component<tooltip.Props, tooltip.Api> {
    static componentName = 'tooltip';

    initMachine(props: tooltip.Props): Machine<any> {
        const [machineProps] = tooltip.splitProps(props);
        return new Machine(tooltip.machine, {
            interactive: true,
            ...machineProps,
            positioning: {
                placement: 'top',
                gutter: 6,
                ...machineProps.positioning,
            },
        });
    }

    initApi() {
        return tooltip.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.spreadPropsByOptionalValue('trigger', ({ value }) =>
            this.api.getTriggerProps({ value })
        );

        const positionerEl = this.hydrator.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.hydrator.query('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.hydrator.query('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.hydrator.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());
    }
}
