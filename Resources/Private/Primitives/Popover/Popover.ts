import * as popover from '@zag-js/popover';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';

// PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
// prop (see WireTypeResolver), so `positioning` resolves to `unknown` on the wire - this converter
// just tells TS what it actually is (a real @zag-js/popper PositioningOptions object) rather than
// transforming the value itself.
const popoverPropConverters = {
    positioning: (positioning: popover.Props['positioning']) => positioning,
} satisfies ClientPropConverterMap;

registerClientPropConverters('popover', popoverPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        popover: ConverterMachineProps<typeof popoverPropConverters>;
    }
}

export class Popover extends Component<popover.Props, popover.Api> {
    static componentName = 'popover';

    initMachine(props: popover.Props): Machine<any> {
        const [machineProps] = popover.splitProps(props);
        return new Machine(popover.machine, {
            ...machineProps,
            positioning: {
                gutter: 6,
                ...machineProps.positioning,
            },
        });
    }

    initApi() {
        return popover.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.spreadPropsByOptionalValue('trigger', ({ value }) =>
            this.api.getTriggerProps({ value })
        );

        const positionerEl = this.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.query('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.query('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const titleEl = this.query('title');
        if (titleEl) this.spreadProps(titleEl, this.api.getTitleProps());

        const descriptionEl = this.query('description');
        if (descriptionEl) this.spreadProps(descriptionEl, this.api.getDescriptionProps());

        const closeTriggerEl = this.getElement('closeTrigger');
        if (closeTriggerEl) this.spreadProps(closeTriggerEl, this.api.getCloseTriggerProps());

        const indicatorEl = this.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
