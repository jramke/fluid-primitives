import { afterEach, expect, test, vi } from 'vitest';
import { extbase } from '../../Resources/Private/Client/src/lib/extbase';

const ACTION_URL =
    'https://example.com/p?tx_docs_docs[action]=find&tx_docs_docs[controller]=City&cHash=abc';

function stubFetch(respond: () => Response) {
    const calls: { url: string; init: RequestInit }[] = [];
    vi.stubGlobal('fetch', async (url: URL | string, init: RequestInit) => {
        calls.push({ url: String(url), init });
        return respond();
    });
    return calls;
}

afterEach(() => {
    vi.unstubAllGlobals();
});

test('post sends flattened data as FormData under the URL namespace, unprefixed without one', async () => {
    const calls = stubFetch(() => new Response('{}'));
    await extbase.post(ACTION_URL, {
        q: 'x',
        demand: { city: 'Berlin' },
        ids: [1, 2],
        skip: null,
        file: new Blob(['f']),
    });
    await extbase.post('https://example.com/api', { q: 'x' });

    const body = calls[0].init.body as FormData;
    expect([...body.keys()]).toEqual([
        'tx_docs_docs[q]',
        'tx_docs_docs[demand][city]',
        'tx_docs_docs[ids][0]',
        'tx_docs_docs[ids][1]',
        'tx_docs_docs[file]',
    ]);
    expect(body.get('tx_docs_docs[file]')).toBeInstanceOf(Blob);
    expect([...(calls[1].init.body as FormData).keys()]).toEqual(['q']);
});

test('get appends data under the namespace and keeps the URL query', async () => {
    const calls = stubFetch(() => new Response('{}'));
    await extbase.get(ACTION_URL, { q: 'x' });

    const { searchParams } = new URL(calls[0].url);
    expect(searchParams.get('tx_docs_docs[q]')).toBe('x');
    expect(searchParams.get('cHash')).toBe('abc');
});

test('getArgumentPrefix reads a custom namespace from either key and ignores other URLs', () => {
    expect(extbase.getArgumentPrefix('/p?my_ns[controller]=C&my_ns[action]=a')).toBe('my_ns');
    expect(extbase.getArgumentPrefix('/p?my_ns[action]=a')).toBe('my_ns');
    expect(extbase.getArgumentPrefix('/api/search?q=1')).toBeNull();
});
