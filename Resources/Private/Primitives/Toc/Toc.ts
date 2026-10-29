import * as toc from '@zag-js/toc';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';

// The scroll container is a CSS selector on the wire and the function Zag expects once converted.
const tocPropConverters = {
    scrollEl: (selector: string | undefined) =>
        selector ? () => document.querySelector<HTMLElement>(selector) : undefined,
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
        return new Machine(toc.machine, { ...machineProps, items: this.readItems() });
    }

    initApi() {
        return toc.connect(this.machine.service, normalizeProps);
    }

    // The items are the rendered `item` parts, so a template lists a heading only once.
    private readItems(): toc.TocItem[] {
        return this.hydrator.queryAll<HTMLElement>('item').flatMap(el => {
            const value = el.dataset.value;
            return value === undefined ? [] : [{ value, depth: Number(el.dataset.depth) }];
        });
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
