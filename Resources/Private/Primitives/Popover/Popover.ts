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
        return new Machine(popover.machine, {
            ...props,
            positioning: {
                gutter: 6,
                ...props.positioning,
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

        const positionerEl = this.getElement('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.getElement('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.getElement('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const titleEl = this.getElement('title');
        if (titleEl) this.spreadProps(titleEl, this.api.getTitleProps());

        const descriptionEl = this.getElement('description');
        if (descriptionEl) this.spreadProps(descriptionEl, this.api.getDescriptionProps());

        const closeTriggerEl = this.getElement('closeTrigger');
        if (closeTriggerEl) this.spreadProps(closeTriggerEl, this.api.getCloseTriggerProps());

        const indicatorEl = this.getElement('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
