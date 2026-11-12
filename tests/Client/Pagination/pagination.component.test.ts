import { afterEach, expect, test, vi } from 'vitest';
import {
    Pagination,
    type PaginationProps,
} from '../../../Resources/Private/Primitives/Pagination/Pagination';

let pagination: Pagination | undefined;

afterEach(() => {
    pagination?.destroy();
    pagination = undefined;
    document.body.innerHTML = '';
});

const item = (page: number, selected = false) =>
    `<li><button type="button" data-pagination-item="p" data-value="${page}"${selected ? ' data-selected' : ''}>${page}</button></li>`;
const ellipsis = (index: number) =>
    `<li><span data-pagination-ellipsis="p" data-index="${index}">…</span></li>`;

// The server markup of page 1 of 12, the pages wrapped in a list item each. Without ellipses, the
// template leaves them out.
function mount({ ellipses = true } = {}) {
    document.body.innerHTML = `
        <nav data-pagination-root="p">
            <ul>
                <li><button type="button" disabled data-disabled data-pagination-prev-trigger="p">Previous</button></li>
                ${[1, 2, 3, 4, 5].map(page => item(page, page === 1)).join('')}${ellipses ? ellipsis(5) : ''}${item(12)}
                <template data-pagination-item-template="p"><li><button type="button" data-pagination-item="p"></button></li></template>
                ${ellipses ? '<template data-pagination-ellipsis-template="p"><li><span data-pagination-ellipsis="p">…</span></li></template>' : ''}
                <li><button type="button" data-pagination-next-trigger="p">Next</button></li>
            </ul>
        </nav>`;
    pagination = new Pagination({ id: 'p', count: 120 }).init();
}

const shown = () =>
    [...document.querySelectorAll('ul > li')]
        .slice(1, -1)
        .map(li => {
            const el = li.firstElementChild!;
            if (el.matches('[data-pagination-ellipsis]')) return '…';
            return el.hasAttribute('data-selected') ? `[${el.textContent}]` : el.textContent;
        })
        .join(' ');

const prevEl = () => document.querySelector<HTMLElement>('[data-pagination-prev-trigger]')!;
const itemEl = (page: number) =>
    document.querySelector<HTMLElement>(`[data-pagination-item][data-value="${page}"]`)!;

test('renders the pages it shows from the templates, in the place of the server markup', async () => {
    mount();
    expect(shown()).toBe('[1] 2 3 4 5 … 12');

    itemEl(5).click();
    await expect.poll(shown).toBe('1 … 4 [5] 6 … 12');
    // a new item gets its page number and the props of the machine
    expect(itemEl(6).getAttribute('aria-label')).toBe('page 6');

    document.querySelector<HTMLElement>('[data-pagination-next-trigger]')!.click();
    await expect.poll(shown).toBe('1 … 5 [6] 7 … 12');
    expect(
        document.querySelector('[data-pagination-prev-trigger]')!.hasAttribute('data-disabled')
    ).toBe(false);
});

test('leaves out the ellipses without an ellipsis template', async () => {
    mount({ ellipses: false });
    expect(shown()).toBe('[1] 2 3 4 5 12');

    itemEl(5).click();
    await expect.poll(shown).toBe('1 4 [5] 6 12');
});

test('keeps the element of a page while it is shown, so a clicked item keeps the focus', async () => {
    mount();
    const five = itemEl(5);
    five.focus();
    five.click();

    await expect.poll(shown).toBe('1 … 4 [5] 6 … 12');
    expect(itemEl(5)).toBe(five);
    expect(document.activeElement).toBe(five);
});

test('gives the links it renders on the client the URLs the server sent', async () => {
    document.body.innerHTML = `
        <nav data-pagination-root="p">
            ${[1, 2, 3, 4, 5].map(page => `<a href="/events?page=${page}" data-pagination-item="p" data-value="${page}">${page}</a>`).join('')}
            <span data-pagination-ellipsis="p" data-index="5">…</span>
            <a href="/events?page=12" data-pagination-item="p" data-value="12">12</a>
            <a data-pagination-prev-trigger="p"></a>
            <template data-pagination-item-template="p"><a data-pagination-item="p"></a></template>
            <template data-pagination-ellipsis-template="p"><span data-pagination-ellipsis="p">…</span></template>
        </nav>`;
    const pageUrls = Object.fromEntries(
        Array.from({ length: 12 }, (_, i) => [i + 1, `/events?page=${i + 1}`])
    );
    pagination = new Pagination({ id: 'p', count: 120, type: 'link', pageUrls }).init();

    itemEl(5).click();
    await expect.poll(() => itemEl(6)?.getAttribute('href')).toBe('/events?page=6');
    expect(prevEl().getAttribute('href')).toBe('/events?page=4');

    itemEl(1).click();
    await expect.poll(() => prevEl().hasAttribute('href')).toBe(false);
});

function mountWithFetchUrl(loadPage: PaginationProps['loadPage']) {
    document.body.innerHTML = `
        <nav data-pagination-root="h">
            ${[1, 2, 3, 4, 5].map(page => `<a href="/events?page=${page}" data-pagination-item="h" data-value="${page}">${page}</a>`).join('')}
            <span data-pagination-ellipsis="h" data-index="5">…</span>
            <a href="/events?page=12" data-pagination-item="h" data-value="12">12</a>
            <template data-pagination-item-template="h"><a data-pagination-item="h"></a></template>
            <template data-pagination-ellipsis-template="h"><span data-pagination-ellipsis="h">…</span></template>
        </nav>`;
    const pageUrls = Object.fromEntries(
        Array.from({ length: 12 }, (_, i) => [i + 1, `/events?page=${i + 1}`])
    );
    pagination = new Pagination({
        id: 'h',
        count: 120,
        type: 'link',
        pageUrls,
        fetchUrl: '/events.json',
        loadPage,
    }).init();
}

const linkEl = (page: number) =>
    document.querySelector<HTMLAnchorElement>(`[data-pagination-item="h"][data-value="${page}"]`)!;

const click = (el: HTMLElement, init: MouseEventInit = {}) => {
    const event = new MouseEvent('click', { bubbles: true, cancelable: true, ...init });
    el.dispatchEvent(event);
    return event;
};

test('with a fetchUrl, a link click loads the page through loadPage, and the URL and the history follow the page', async () => {
    const loadPage = vi.fn();
    mountWithFetchUrl(loadPage);

    // a click with a modifier key opens the link elsewhere and leaves the page as it is
    expect(click(linkEl(5), { ctrlKey: true }).defaultPrevented).toBe(false);
    expect(pagination!.api.page).toBe(1);

    expect(click(linkEl(5)).defaultPrevented).toBe(true);
    await expect.poll(() => loadPage.mock.calls.length).toBe(1);
    expect(loadPage.mock.calls[0][0]).toMatchObject({ page: 5, fetchUrl: '/events.json' });
    expect(location.search).toBe('?page=5');

    history.back();
    await expect.poll(() => pagination!.api.page).toBe(1);
    await expect.poll(() => loadPage.mock.calls.length).toBe(2);
    expect(loadPage.mock.calls[1][0]).toMatchObject({ page: 1 });

    // back set the page of its entry and pushed none: forward still leads to page 5
    history.forward();
    await expect.poll(() => pagination!.api.page).toBe(5);
});

test('aborts the signal of a page that is no longer current and drops its error', async () => {
    const signals: AbortSignal[] = [];
    mountWithFetchUrl(
        ({ signal }) =>
            new Promise((_, reject) => {
                signals.push(signal);
                signal.addEventListener('abort', () => reject(signal.reason));
            })
    );

    click(linkEl(5));
    await expect.poll(() => signals.length).toBe(1);
    click(linkEl(6));
    await expect.poll(() => signals.length).toBe(2);

    expect(signals.map(signal => signal.aborted)).toEqual([true, false]);
});
