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

        const positionerEl = this.hydrator.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.hydrator.query('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.hydrator.query('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const viewportEl = this.hydrator.query('viewport');
        if (viewportEl) this.spreadProps(viewportEl, this.api.getViewportProps());

        this.spreadPropsByOptionalValue('content', ({ value }) =>
            this.api.getContentProps({ value })
        );

        // a title and a description belong to the content around them, the one of their trigger
        const contentValueOf = (el: HTMLElement) =>
            el.closest<HTMLElement>(this.hydrator.selector('content'))?.dataset.value;
        this.hydrator
            .queryAll('title')
            .forEach(el =>
                this.spreadProps(el, this.api.getTitleProps({ value: contentValueOf(el) }))
            );
        this.hydrator
            .queryAll('description')
            .forEach(el =>
                this.spreadProps(el, this.api.getDescriptionProps({ value: contentValueOf(el) }))
            );

        this.hydrator.queryAll('closeTrigger').forEach(closeTriggerEl => {
            this.spreadProps(closeTriggerEl, this.api.getCloseTriggerProps());
        });

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
