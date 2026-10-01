import * as navigationMenu from '@zag-js/navigation-menu';
import { Component, Machine, normalizeProps } from '../../Client';

export class NavigationMenu extends Component<navigationMenu.Props, navigationMenu.Api> {
    static componentName = 'navigationMenu';

    initMachine(props: navigationMenu.Props): Machine<any> {
        const [machineProps] = navigationMenu.splitProps(props);
        return new Machine(navigationMenu.machine, machineProps);
    }

    initApi() {
        return navigationMenu.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const listEl = this.hydrator.query('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        this.spreadPropsByValue('item', ({ el, value }) =>
            this.api.getItemProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('trigger', ({ el, value }) =>
            this.api.getTriggerProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('triggerProxy', ({ value }) =>
            this.api.getTriggerProxyProps({ value })
        );

        this.spreadPropsByValue('viewportProxy', ({ value }) =>
            this.api.getViewportProxyProps({ value })
        );

        this.spreadPropsByValue('content', ({ value }) => this.api.getContentProps({ value }));

        this.spreadPropsByValue('link', ({ el, value }) =>
            this.api.getLinkProps({ value, current: el.hasAttribute('data-current') })
        );

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

        const arrowEl = this.hydrator.query('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const viewportPositionerEl = this.hydrator.query('viewportPositioner');
        if (viewportPositionerEl) {
            const align = (viewportPositionerEl.dataset.align ||
                undefined) as navigationMenu.ViewportProps['align'];
            this.spreadProps(viewportPositionerEl, this.api.getViewportPositionerProps({ align }));
        }

        const viewportEl = this.hydrator.query('viewport');
        if (viewportEl) {
            const align = (viewportEl.dataset.align ||
                undefined) as navigationMenu.ViewportProps['align'];
            this.spreadProps(viewportEl, this.api.getViewportProps({ align }));
        }

        this.spreadPropsByValue('itemIndicator', ({ el, value }) =>
            this.api.getItemIndicatorProps({ value, disabled: el.hasAttribute('data-disabled') })
        );
    }
}
