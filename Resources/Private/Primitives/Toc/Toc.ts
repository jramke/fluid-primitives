import * as toc from '@zag-js/toc';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';

const tocPropConverters = {
    // The scroll container is a CSS selector on the wire and the function Zag expects once converted.
    scrollEl: (selector: string | undefined) =>
        selector ? () => document.querySelector<HTMLElement>(selector) : undefined,
    // PHP can't distinguish a list of objects from any other array for a bare `type="array"` prop,
    // so this only tells TS what the wire value actually is.
    items: (items: toc.TocItem[]) => items,
} satisfies ClientPropConverterMap;

registerClientPropConverters('toc', tocPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        toc: ConverterMachineProps<typeof tocPropConverters>;
    }
}

export class Toc extends Component<toc.Props, toc.Api> {
    static componentName = 'toc';

    initMachine(props: toc.Props): Machine<any> {
        const [machineProps] = toc.splitProps(props);
        return new Machine(toc.machine, {
            ...machineProps,
            // the machine scrolls the list and the page with this behavior, so a visitor who
            // prefers reduced motion gets no animation whatever the prop says
            ...(window.matchMedia?.('(prefers-reduced-motion: reduce)').matches && {
                scrollBehavior: 'instant',
            }),
        });
    }

    initApi() {
        return toc.connect(this.machine.service, normalizeProps);
    }

    private itemOf(value: string): toc.TocItem | undefined {
        return this.api.items.find(item => item.value === value);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const titleEl = this.hydrator.query('title');
        if (titleEl) this.spreadProps(titleEl, this.api.getTitleProps());

        const listEl = this.hydrator.query('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        this.spreadPropsByValue('item', ({ value }) => {
            const item = this.itemOf(value);
            return item && this.api.getItemProps({ item });
        });

        this.spreadPropsByValue('link', ({ value }) => {
            const item = this.itemOf(value);
            return item && this.api.getLinkProps({ item });
        });

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    }
}
