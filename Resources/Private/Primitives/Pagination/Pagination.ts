import * as pagination from '@zag-js/pagination';
import type { Attrs } from '@zag-js/vanilla';
import {
    Component,
    devWarn,
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

/** What {@link PaginationProps.loadPage} receives. */
export interface PageLoadDetails {
    /** The page to load. */
    page: number;
    /** The number of items per page. */
    pageSize: number;
    /** The `fetchUrl` of the root. */
    fetchUrl: string;
    /** Aborted once the page changes again, so a response of a page that is no longer current is dropped. Pass it to `fetch()`. */
    signal: AbortSignal;
}

/** The props of {@link Pagination}: the machine's, the URLs the server built and the page loading. */
export type PaginationProps = pagination.Props & {
    /**
     * The URL of every page, by page number. The server sends it for a root with a `fetchUrl`. It is
     * the default `getPageUrl`.
     */
    pageUrls?: Record<number, string>;
    /** The endpoint of the `fetchUrl` prop of the root. */
    fetchUrl?: string;
    /**
     * Loads and shows the content of a page, after every page change of a root with a `fetchUrl`. An
     * error after its signal aborted is dropped.
     */
    loadPage?: (details: PageLoadDetails) => unknown;
};

export class Pagination extends Component<PaginationProps, pagination.Api> {
    static componentName = 'pagination';

    #loading: AbortController | undefined;

    transformProps({ pageUrls, ...props }: PaginationProps): PaginationProps {
        const getPageUrl =
            props.getPageUrl ??
            (pageUrls ? ({ page }: { page: number }) => pageUrls[page] : undefined);
        if (!props.fetchUrl) return { ...props, getPageUrl };

        return {
            ...props,
            getPageUrl,
            onPageChange: details => {
                props.onPageChange?.(details);
                this.#loadPage(details);
            },
        };
    }

    init(): this {
        super.init();
        if (this.#syncsHistory()) {
            this.#replaceHistoryPage(this.api.page);
            this.hydrator.query('root')?.addEventListener('click', this.#keepLinkClick, true);
            window.addEventListener('popstate', this.#showHistoryPage);
        }
        return this;
    }

    destroy() {
        this.#loading?.abort();
        this.hydrator.query('root')?.removeEventListener('click', this.#keepLinkClick, true);
        window.removeEventListener('popstate', this.#showHistoryPage);
        super.destroy();
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
            this.hydrator.queryAll(part).forEach(el => this.spreadProps(el, getProps()));
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

        // Links load the page they lead to, buttons rely on the markup having every page they show
        if (this.userProps.type === 'link') return;
        const shown = new Set(this.hydrator.queryAll('item').map(el => Number(el.dataset.value)));
        const missing = this.api.pages.find(
            (page): page is { type: 'page'; value: number } =>
                page.type === 'page' && !shown.has(page.value)
        );
        if (missing) {
            devWarn(
                `Pagination "${this.machine.scope.id}" shows page ${missing.value}, which has no item. Add an itemTemplate and an ellipsisTemplate, so the client renders the pages it shows.`
            );
        }
    }

    #syncsHistory(): boolean {
        return Boolean(this.userProps.fetchUrl && this.userProps.type === 'link');
    }

    #loadPage({ page, pageSize }: pagination.PageChangeDetails) {
        this.#loading?.abort();
        const loading = new AbortController();
        this.#loading = loading;

        // Back and forward set the page of their entry, which then needs no new one
        if (this.#syncsHistory() && this.#historyPage() !== page) {
            const url = this.userProps.getPageUrl?.({ page, pageSize });
            if (url) history.pushState(this.#historyState(page), '', url);
        }

        const { loadPage, fetchUrl } = this.userProps;
        if (!loadPage || !fetchUrl) return;
        Promise.resolve()
            .then(() => loadPage({ page, pageSize, fetchUrl, signal: loading.signal }))
            .catch(error => {
                if (!loading.signal.aborted) throw error;
            });
    }

    // A link click changes the page through the machine instead of loading it. A click with a
    // modifier key opens the link elsewhere and leaves this page as it is.
    #keepLinkClick = (event: MouseEvent) => {
        if (!(event.target instanceof Element) || !event.target.closest('a[href]')) return;
        if (
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            event.stopPropagation();
            return;
        }
        event.preventDefault();
    };

    #showHistoryPage = () => {
        const page = this.#historyPage();
        if (page !== undefined && page !== this.api.page) this.api.setPage(page);
    };

    // Several paginations can share one history entry, each under its own key
    get #historyKey() {
        return `fluid-primitives:pagination:${this.machine.scope.id}`;
    }

    #historyPage(): number | undefined {
        const page = history.state?.[this.#historyKey];
        return typeof page === 'number' ? page : undefined;
    }

    #historyState(page: number) {
        return { ...(history.state ?? {}), [this.#historyKey]: page };
    }

    #replaceHistoryPage(page: number) {
        history.replaceState(this.#historyState(page), '');
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
