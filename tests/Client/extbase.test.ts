// node --experimental-transform-types --test packages/fluid-primitives/tests/Client/extbase.test.ts
import assert from 'node:assert/strict';
import { test } from 'node:test';
import { extbase } from '../../Resources/Private/Client/src/lib/extbase.ts';

Object.assign(globalThis, { document: { baseURI: 'https://example.com/' } });

const ACTION_URL =
    'https://example.com/p?tx_docs_docs[action]=find&tx_docs_docs[controller]=City&cHash=abc';

function stubFetch(respond: () => Response) {
    const calls: { url: string; init: RequestInit }[] = [];
    globalThis.fetch = (async (url: URL | string, init: RequestInit) => {
        calls.push({ url: String(url), init });
        return respond();
    }) as typeof fetch;
    return calls;
}

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
    assert.deepEqual(
        [...body.keys()],
        [
            'tx_docs_docs[q]',
            'tx_docs_docs[demand][city]',
            'tx_docs_docs[ids][0]',
            'tx_docs_docs[ids][1]',
            'tx_docs_docs[file]',
        ]
    );
    assert.ok(body.get('tx_docs_docs[file]') instanceof Blob);
    assert.deepEqual([...(calls[1].init.body as FormData).keys()], ['q']);
});

test('get appends data under the namespace and keeps the URL query', async () => {
    const calls = stubFetch(() => new Response('{}'));
    await extbase.get(ACTION_URL, { q: 'x' });

    const { searchParams } = new URL(calls[0].url);
    assert.equal(searchParams.get('tx_docs_docs[q]'), 'x');
    assert.equal(searchParams.get('cHash'), 'abc');
});

test('getArgumentPrefix reads a custom namespace from either key and ignores other URLs', () => {
    assert.equal(extbase.getArgumentPrefix('/p?my_ns[controller]=C&my_ns[action]=a'), 'my_ns');
    assert.equal(extbase.getArgumentPrefix('/p?my_ns[action]=a'), 'my_ns');
    assert.equal(extbase.getArgumentPrefix('/api/search?q=1'), null);
});
