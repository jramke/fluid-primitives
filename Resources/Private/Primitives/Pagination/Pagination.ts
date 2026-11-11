import * as pagination from '@zag-js/pagination';
import type { Attrs } from '@zag-js/vanilla';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    resolveTranslations,
    Template,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';

const paginationPropConverters = {
    // `itemLabel` is a function of the page in Zag, a text with `%page%` and `%totalPages%` on the wire.
    translations: (texts: Record<string, unknown> | undefined) =>
        resolveTranslations<pagination.IntlTranslations>(texts, {
            itemLabel: (details: pagination.ItemLabelDetails) => ({ ...details }),
        }),
    // PHP sends a map of page numbers, which this only tells TS
    pageUrls: (urls: Record<number, string> | undefined) => urls,
} satisfies ClientPropConverterMap;

registerClientPropConverters('pagination', paginationPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        pagination: ConverterMachineProps<typeof paginationPropConverters>;
    }
}

interface PageElement {
    /** The direct child of the list that holds the part, the part itself without a wrapper. */
    unit: Element;
    el: HTMLElement;
}

/** The props of {@link Pagination}: the machine's, and the URLs the server built. */
export type PaginationProps = pagination.Props & {
    /**
     * The URL of every page, by page number. The server sends it for links the client renders from
     * the `itemTemplate`. It is the default `getPageUrl`.
     */
    pageUrls?: Record<number, string>;
};

export class Pagination extends Component<PaginationProps, pagination.Api> {
    static componentName = 'pagination';

    transformProps({ pageUrls, ...props }: PaginationProps): PaginationProps {
        if (!pageUrls || props.getPageUrl) return props;
        return { ...props, getPageUrl: ({ page }) => pageUrls[page] };
    }

    initMachine(props: pagination.Props): Machine<any> {
        const [machineProps] = pagination.splitProps(props);
        return new Machine(pagination.machine, machineProps);
    }

    initApi() {
        return pagination.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const triggers: [string, () => Attrs][] = [
            ['firstTrigger', this.api.getFirstTriggerProps],
            ['prevTrigger', this.api.getPrevTriggerProps],
            ['nextTrigger', this.api.getNextTriggerProps],
            ['lastTrigger', this.api.getLastTriggerProps],
        ];
        for (const [part, getProps] of triggers) {
            const props = getProps();
            // Zag removes only what it set itself: the server disables the triggers when the count
            // comes from a client script.
            props['data-disabled'] ??= null;
            this.hydrator.queryAll(part).forEach(el => this.spreadProps(el, props));
        }

        const itemTemplateEl = this.hydrator.query<HTMLTemplateElement>('itemTemplate');
        if (itemTemplateEl) {
            this.renderPages(itemTemplateEl);
            return;
        }

        // Without templates the server markup is all there is: a page that is not in it stays out.
        this.spreadPropsByValue('item', ({ value }) =>
            this.api.getItemProps({ type: 'page', value: Number(value) })
        );
        this.hydrator.queryAll('ellipsis').forEach(el => {
            this.spreadProps(el, this.api.getEllipsisProps({ index: Number(el.dataset.index) }));
        });
    }

    // The pages go where the templates are. An item keeps its element while its page is shown, so a
    // clicked item keeps the focus: only new elements and ellipses move.
    private renderPages(itemTemplateEl: HTMLTemplateElement) {
        const listEl = itemTemplateEl.parentElement;
        if (!listEl) return;

        const ellipsisTemplateEl = this.hydrator.query<HTMLTemplateElement>('ellipsisTemplate');
        const anchorEl =
            ellipsisTemplateEl?.parentElement === listEl &&
            ellipsisTemplateEl.compareDocumentPosition(itemTemplateEl) &
                Node.DOCUMENT_POSITION_FOLLOWING
                ? ellipsisTemplateEl
                : itemTemplateEl;

        const inList = (part: string) =>
            this.hydrator
                .queryAll(part)
                .filter(el => listEl.contains(el))
                .map(el => ({ unit: this.unitOf(el, listEl), el }));

        const items = new Map(inList('item').map(entry => [Number(entry.el.dataset.value), entry]));
        const ellipses = inList('ellipsis');

        const entries = this.api.pages
            .map((page, index): PageElement | undefined => {
                if (page.type === 'page') {
                    const entry = items.get(page.value) ?? this.cloneItem(page.value);
                    items.delete(page.value);
                    this.spreadProps(entry.el, this.api.getItemProps(page));
                    return entry;
                }

                // Without an ellipsis template, the pages leave out the ellipses
                const entry =
                    ellipses.shift() ??
                    (ellipsisTemplateEl ? this.clone('ellipsisTemplate', 'ellipsis') : undefined);
                if (entry) this.spreadProps(entry.el, this.api.getEllipsisProps({ index }));
                return entry;
            })
            .filter(entry => entry !== undefined);

        [...items.values(), ...ellipses].forEach(({ unit }) => unit.remove());

        const units = entries.map(({ unit }) => unit);
        let cursor: Element | null =
            Array.from(listEl.children).find(child => units.includes(child)) ?? anchorEl;
        for (const unit of units) {
            if (unit === cursor) cursor = cursor.nextElementSibling;
            else if (cursor) cursor.before(unit);
            else listEl.append(unit);
        }
    }

    private cloneItem(page: number): PageElement {
        const entry = this.clone('itemTemplate', 'item', String(page));
        if (entry.el.children.length === 0 && !entry.el.textContent?.trim()) {
            entry.el.textContent = String(page);
        }
        return entry;
    }

    private clone(templatePart: string, part: string, value?: string): PageElement {
        const instance = new Template(this.hydrator, templatePart, { value });
        const el = instance.query(part);
        if (!el) throw new Error(`Pagination: "${templatePart}" has no ${part} in it.`);
        return { unit: instance.root, el };
    }

    private unitOf(el: Element, listEl: Element): Element {
        let unit = el;
        while (unit.parentElement && unit.parentElement !== listEl) unit = unit.parentElement;
        return unit;
    }
}
