import * as accordion from '@zag-js/accordion';
import { Component, Machine, normalizeProps } from '../../Client';

export class Accordion extends Component<accordion.Props, accordion.Api> {
    static componentName = 'accordion';

    initMachine(props: accordion.Props): Machine<any> {
        return new Machine(accordion.machine, {
            ...props,
        });
    }

    initApi() {
        return accordion.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.getElement('root');
        if (rootEl) {
            this.spreadProps(rootEl, this.api.getRootProps());
        }

        this.spreadPropsByValue('item', ({ el, value }) =>
            this.api.getItemProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('itemTrigger', ({ el, value }) =>
            this.api.getItemTriggerProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('itemContent', ({ el, value }) =>
            this.api.getItemContentProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('itemIndicator', ({ el, value }) =>
            this.api.getItemIndicatorProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        // just so they are hydrated (data-attributes removed)
        this.getElements('itemHeader');
    }
}
