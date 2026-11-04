import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { Toaster } from '../../../Resources/Private/Primitives/Toaster/Toaster';

let toaster: Toaster | undefined;

beforeEach(() => {
    // the timers of Zag compare the time of a frame with performance.now(), which jsdom counts from another origin
    vi.stubGlobal('requestAnimationFrame', (callback: FrameRequestCallback) =>
        setTimeout(() => callback(performance.now()), 16)
    );
    vi.stubGlobal('cancelAnimationFrame', (id: number) => clearTimeout(id));
});

afterEach(() => {
    toaster?.destroy();
    toaster = undefined;
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

const frames = () => new Promise(resolve => setTimeout(resolve, 50));

function mountToaster() {
    document.body.innerHTML = `
        <div data-toaster-root="t1">
            <template data-toaster-toast-template="t1"><div data-toast-root="stencil"><div data-toast-title="stencil"></div><div data-toast-description="stencil"></div><button data-toast-action-trigger="stencil"></button><button data-toast-close-trigger="stencil">x</button></div></template>
        </div>`;
    toaster = new Toaster({ id: 't1', removeDelay: 0 }).init();
    return {
        toasts: () => Array.from(document.querySelectorAll<HTMLElement>('[data-toast-root]')),
        part: (toast: HTMLElement, part: string) =>
            toast.querySelector<HTMLElement>(`[data-toast-${part}]`)!,
    };
}

test('fills a clone of the toast template from the store and removes it once it is dismissed', async () => {
    const { toasts, part } = mountToaster();
    const onClick = vi.fn();
    const id = toaster!.store.create({ title: 'Deleted', action: { label: 'Undo', onClick } });
    await frames();

    const [toast] = toasts();
    expect(toast.dataset.toastRoot).toBe(id);
    expect(part(toast, 'title').textContent).toBe('Deleted');
    expect(toast.getAttribute('aria-labelledby')).toBe(part(toast, 'title').id);
    expect(part(toast, 'description').hidden).toBe(true);
    expect(part(toast, 'action-trigger').textContent).toBe('Undo');
    expect(part(toast, 'close-trigger').getAttribute('aria-label')).toBe('Dismiss notification');

    part(toast, 'action-trigger').click();
    await frames();

    expect(onClick).toHaveBeenCalledOnce();
    expect(toasts()).toHaveLength(0);
});

test('updates a loading toast in place and hides the triggers it has no use for', async () => {
    const { toasts, part } = mountToaster();
    const id = toaster!.store.create({
        title: 'Uploading your CV',
        type: 'loading',
        closable: false,
    });
    await frames();

    expect(part(toasts()[0], 'action-trigger').hidden).toBe(true);
    expect(part(toasts()[0], 'close-trigger').hidden).toBe(true);

    toaster!.store.update(id, { title: 'CV uploaded', type: 'success', closable: true });
    await frames();

    const [toast] = toasts();
    expect(toasts()).toHaveLength(1);
    expect(toast.dataset.type).toBe('success');
    expect(part(toast, 'title').textContent).toBe('CV uploaded');
    expect(part(toast, 'close-trigger').hidden).toBe(false);
});
