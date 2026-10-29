import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { applyClientPropConverters } from '../../../Resources/Private/Client';
import { Toc } from '../../../Resources/Private/Primitives/Toc/Toc';

type Observer = {
    callback: IntersectionObserverCallback;
    options?: IntersectionObserverInit;
    observed: Element[];
};
let observers: Observer[] = [];
let toc: Toc | undefined;

beforeEach(() => {
    observers = [];
    vi.stubGlobal(
        'IntersectionObserver',
        class {
            private readonly entry: Observer;
            constructor(
                callback: IntersectionObserverCallback,
                options?: IntersectionObserverInit
            ) {
                this.entry = { callback, options, observed: [] };
                observers.push(this.entry);
            }
            observe(el: Element) {
                this.entry.observed.push(el);
            }
            disconnect() {}
        }
    );
    // scrollIntoView and the ResizeObserver of the indicator don't exist in jsdom
    Element.prototype.scrollIntoView = () => {};
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        }
    );
});

afterEach(() => {
    toc?.destroy();
    toc = undefined;
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

function mountToc(props: Partial<ConstructorParameters<typeof Toc>[0]> = {}) {
    document.body.innerHTML = `
        <nav data-toc-root="t1">
            <ul data-toc-list="t1">
                <li data-toc-item="t1" data-value="intro" data-depth="2"><a data-toc-link="t1" data-value="intro" href="#intro">Intro</a></li>
                <li data-toc-item="t1" data-value="install" data-depth="3"><a data-toc-link="t1" data-value="install" href="#install">Install</a></li>
                <li data-toc-item="t1" data-value="usage" data-depth="2"><a data-toc-link="t1" data-value="usage" href="#usage">Usage</a></li>
            </ul>
        </nav>
        <div id="content">
            <h2 id="intro">Intro</h2><h3 id="install">Install</h3><h2 id="usage">Usage</h2><h2 id="unlisted">Unlisted</h2>
        </div>`;
    toc = new Toc({ id: 't1', ...props }).init();
    const item = (value: string) =>
        document.querySelector<HTMLElement>(`[data-toc-item][data-value="${value}"]`)!;
    const link = (value: string) =>
        document.querySelector<HTMLElement>(`[data-toc-link][data-value="${value}"]`)!;
    const see = (...ids: string[]) =>
        observers[0].callback(
            ['intro', 'install', 'usage'].map(id => ({
                target: document.getElementById(id)!,
                isIntersecting: ids.includes(id),
            })) as unknown as IntersectionObserverEntry[],
            {} as IntersectionObserver
        );
    return { item, link, see };
}

test('lists the rendered item parts and highlights the ones whose heading is visible', async () => {
    const { item, link, see } = mountToc();

    expect(toc!.api.items).toEqual([
        { value: 'intro', depth: 2 },
        { value: 'install', depth: 3 },
        { value: 'usage', depth: 2 },
    ]);
    // only the headings of the listed items are observed
    expect(observers[0].observed.map(el => el.id)).toEqual(['intro', 'install', 'usage']);
    expect(item('install').style.getPropertyValue('--depth')).toBe('3');

    see('install', 'usage');
    await expect.poll(() => toc!.api.activeIds).toEqual(['install', 'usage']);

    expect(item('intro').hasAttribute('data-active')).toBe(false);
    expect(link('intro').hasAttribute('aria-current')).toBe(false);
    expect(item('install').hasAttribute('data-first')).toBe(true);
    expect(item('usage').hasAttribute('data-last')).toBe(true);
    expect(link('usage').getAttribute('aria-current')).toBe('location');
});

test('observes inside the scroll container once its selector is converted', () => {
    const wire = applyClientPropConverters('toc', { id: 't1', scrollEl: '#content' });
    mountToc(wire);

    expect(observers[0].options?.root).toBe(document.getElementById('content'));
    expect(applyClientPropConverters('toc', { id: 't1' }).scrollEl).toBeUndefined();
});

test('runs on the items it is given instead of the rendered ones', () => {
    mountToc({ items: [{ value: 'usage', depth: 2 }] });

    expect(toc!.api.items).toEqual([{ value: 'usage', depth: 2 }]);
    expect(observers[0].observed.map(el => el.id)).toEqual(['usage']);
});
