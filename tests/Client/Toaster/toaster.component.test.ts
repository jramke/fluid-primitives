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

const defaultTemplate = `<template data-toaster-toast-template="t1"><div data-toast-root="stencil"><div data-toast-title="stencil"></div><div data-toast-description="stencil"></div><button data-toast-action-trigger="stencil"></button><button data-toast-close-trigger="stencil">x</button></div></template>`;

function mountToaster(templates = defaultTemplate) {
    document.body.innerHTML = `<div data-toaster-root="t1">${templates}</div>`;
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

const errorTemplate = `<template data-toaster-error-toast-template="t1"><div class="error-layout" role="alert" data-toast-root="error-stencil"><div data-toast-title="error-stencil">Something went wrong</div><a href="/support">Contact support</a><button data-toast-close-trigger="error-stencil">x</button></div></template>`;

test('takes the template of its type and swaps it in place when the type changes, and back', async () => {
    const { toasts, part } = mountToaster(
        defaultTemplate.replace(
            '<div data-toast-root="stencil">',
            '<div data-layout="default" data-toast-root="stencil">'
        ) + errorTemplate
    );
    const id = toaster!.store.create({ title: 'Sending', type: 'loading' });
    await frames();

    const [loadingToast] = toasts();
    expect(loadingToast.querySelector('a')).toBeNull();
    const machineRole = loadingToast.getAttribute('role');
    expect(machineRole).toBeTruthy();

    toaster!.store.update(id, { title: 'Sending failed', type: 'error' });
    await frames();

    const [toast] = toasts();
    expect(toast).toBe(loadingToast);
    expect(toast.classList).toContain('error-layout');
    expect(toast.hasAttribute('data-layout')).toBe(false);
    expect(toast.querySelector('a')?.textContent).toBe('Contact support');
    expect(toast.querySelector('[data-toast-description]')).toBeNull();
    expect(part(toast, 'title').textContent).toBe('Sending failed');
    expect(part(toast, 'close-trigger').getAttribute('aria-label')).toBe('Dismiss notification');
    // the machine wins over the template, as on a toast created with the type
    expect(toast.getAttribute('role')).toBe(machineRole);

    toaster!.store.update(id, { title: 'Sent', type: 'success' });
    await frames();

    expect(toast.classList).not.toContain('error-layout');
    expect(toast.dataset.layout).toBe('default');
    expect(toast.getAttribute('role')).toBe(machineRole);
    expect(toast.getAttribute('tabindex')).toBe('0');
    expect(toast.querySelectorAll(':scope > [data-ghost]')).toHaveLength(2);
    expect(part(toast, 'description')).not.toBeNull();
});

test('moves the focus to the same part of the new template, or to the toast', async () => {
    const { toasts, part } = mountToaster(defaultTemplate + errorTemplate);
    const id = toaster!.store.create({ title: 'Sending', type: 'info' });
    await frames();
    part(toasts()[0], 'close-trigger').focus();

    toaster!.store.update(id, { title: 'Sending failed', type: 'error' });
    await frames();
    expect(document.activeElement).toBe(part(toasts()[0], 'close-trigger'));

    toaster!.store.update(id, { type: 'info', action: { label: 'Retry', onClick: () => {} } });
    await frames();
    part(toasts()[0], 'action-trigger').focus();

    toaster!.store.update(id, { type: 'error' });
    await frames();
    expect(document.activeElement).toBe(toasts()[0]);
});

test('shows what the template put into a part while the toast has no text for it', async () => {
    const { toasts, part } = mountToaster(defaultTemplate + errorTemplate);
    toaster!.store.create({ type: 'error', description: 'The server did not answer' });
    await frames();

    const [toast] = toasts();
    const title = part(toast, 'title');
    expect(title.hidden).toBe(false);
    expect(title.textContent).toBe('Something went wrong');
    expect(toast.getAttribute('aria-labelledby')).toBe(title.id);

    toaster!.store.create({ type: 'error', title: 'Upload failed' });
    await frames();

    expect(part(toasts()[0], 'title').textContent).toBe('Upload failed');
});

test('takes the template without a type for a type the toaster knows no template for', async () => {
    const { toasts, part } = mountToaster(defaultTemplate + errorTemplate);
    // with a priority: @zag-js/toast 2.0.0-next.3 has none for a type of its own
    toaster!.store.create({ type: 'a="b"', title: 'Custom', priority: 4 });
    await frames();

    expect(toasts()).toHaveLength(1);
    expect(part(toasts()[0], 'title').textContent).toBe('Custom');
});

test('puts the newest toast first and in front', async () => {
    const { toasts, part } = mountToaster();
    toaster!.store.create({ title: 'First' });
    toaster!.store.create({ title: 'Second' });
    await frames();

    const [front, back] = toasts();
    expect(part(front, 'title').textContent).toBe('Second');
    expect(front.hasAttribute('data-first')).toBe(true);
    expect(part(back, 'title').textContent).toBe('First');
    expect(back.hasAttribute('data-first')).toBe(false);
});

test('labels the region and the close triggers with its translations', async () => {
    document.body.innerHTML = `<div data-toaster-root="t1">${defaultTemplate}</div>`;
    toaster = new Toaster({
        id: 't1',
        removeDelay: 0,
        translations: { groupLabel: 'Benachrichtigungen', closeTriggerLabel: 'Schließen' },
    }).init();
    toaster.store.create({ title: 'Gespeichert' });
    await frames();

    const region = document.querySelector('[data-toaster-root]')!;
    expect(region.getAttribute('aria-label')).toMatch(/^Benachrichtigungen,/);
    expect(document.querySelector('[data-toast-close-trigger]')!.getAttribute('aria-label')).toBe(
        'Schließen'
    );
});

test('shows the indicators of its type and hides the others', async () => {
    const { toasts } = mountToaster(
        `<template data-toaster-toast-template="t1"><div data-toast-root="stencil"><span data-toast-indicator="stencil" data-value="success"></span><span data-toast-indicator="stencil" data-value="loading"></span><div data-toast-title="stencil"></div></div></template>`
    );
    const shown = () =>
        Array.from(
            toasts()[0].querySelectorAll<HTMLElement>('[data-toast-indicator]:not([hidden])'),
            el => el.dataset.value
        );
    const id = toaster!.store.create({ title: 'Saving', type: 'loading' });
    await frames();

    expect(shown()).toEqual(['loading']);

    toaster!.store.update(id, { title: 'Saved', type: 'success' });
    await frames();

    expect(shown()).toEqual(['success']);
});
