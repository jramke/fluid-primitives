import { afterEach, expect, test, vi } from 'vitest';
import { extbase, ExtbaseValidationError } from '../../Resources/Private/Client/src/lib/extbase';

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

    expect(calls.map(call => call.init.method)).toEqual(['POST', 'POST']);
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

    expect(calls[0].init.method).toBe('GET');
    const { searchParams } = new URL(calls[0].url);
    expect(searchParams.get('tx_docs_docs[q]')).toBe('x');
    expect(searchParams.get('cHash')).toBe('abc');
});

test('getArgumentPrefix reads a custom namespace from either key and ignores other URLs', () => {
    expect(extbase.getArgumentPrefix('/p?my_ns[controller]=C&my_ns[action]=a')).toBe('my_ns');
    expect(extbase.getArgumentPrefix('/p?my_ns[action]=a')).toBe('my_ns');
    expect(extbase.getArgumentPrefix('/api/search?q=1')).toBeNull();
});

test('an explicit prefix wins over the one of the URL and an empty one sends unprefixed', async () => {
    const calls = stubFetch(() => new Response('{}'));
    await extbase.post('/events/register', { q: 'x' }, { prefix: 'tx_docs_docs' });
    await extbase.post(ACTION_URL, { q: 'x' }, { prefix: '' });
    await extbase.get('/events/search', { q: 'x' }, { prefix: 'tx_docs_docs' });

    expect([...(calls[0].init.body as FormData).keys()]).toEqual(['tx_docs_docs[q]']);
    expect([...(calls[1].init.body as FormData).keys()]).toEqual(['q']);
    expect(new URL(calls[2].url, 'https://example.com').searchParams.get('tx_docs_docs[q]')).toBe(
        'x'
    );
    // the options of extbase are not something fetch should see
    expect(calls[0].init).not.toHaveProperty('prefix');
    expect(calls[0].init).not.toHaveProperty('objectName');
});

test('an objectName nests the data between the prefix and the keys, the prefix key of form values stays below the prefix', async () => {
    const calls = stubFetch(() => new Response('{}'));
    const values = {
        title: 'x',
        address: { city: 'Berlin' },
        tx_docs_docs: { __trustedProperties: 'signed', event: { __identity: '42' } },
    };
    await extbase.post(ACTION_URL, values, { objectName: 'event' });
    await extbase.post('/save', { title: 'x' }, { objectName: 'event' });
    await extbase.post('/save', values, { prefix: '', objectName: 'event' });
    await extbase.get('/find', { q: 'x' }, { prefix: 'tx_docs_docs', objectName: 'demand' });

    const keys = (call: number) => [...(calls[call].init.body as FormData).keys()];
    expect(keys(0)).toEqual([
        'tx_docs_docs[__trustedProperties]',
        'tx_docs_docs[event][__identity]',
        'tx_docs_docs[event][title]',
        'tx_docs_docs[event][address][city]',
    ]);
    expect(keys(1)).toEqual(['event[title]']);
    // without a prefix there is nothing to hoist
    expect(keys(2)).toContain('event[tx_docs_docs][__trustedProperties]');
    expect(
        new URL(calls[3].url, 'https://example.com').searchParams.get('tx_docs_docs[demand][q]')
    ).toBe('x');
    expect((calls[0].init.body as FormData).get('tx_docs_docs[__trustedProperties]')).toBe(
        'signed'
    );
});

test('a 422 with a JSON object throws the messages by property path, any other response resolves', async () => {
    const errors = { 'person.email': ['Invalid email'], person: ['Not allowed'] };
    stubFetch(() => new Response(JSON.stringify(errors), { status: 422 }));

    const thrown = await extbase.post(ACTION_URL, {}).catch((error: unknown) => error);
    expect(thrown).toBeInstanceOf(ExtbaseValidationError);
    expect((thrown as ExtbaseValidationError).errors).toEqual(errors);
    expect(await (thrown as ExtbaseValidationError).response.json()).toEqual(errors);
    await expect(extbase.get(ACTION_URL)).rejects.toBeInstanceOf(ExtbaseValidationError);

    for (const response of [
        new Response('<html>', { status: 422 }),
        new Response('["a"]', { status: 422 }),
        new Response('{"message":"no"}', { status: 500 }),
    ]) {
        vi.stubGlobal('fetch', async () => response);
        expect(await extbase.post(ACTION_URL, {})).toBe(response);
    }
});

test('post sends an empty list as an empty value, which Extbase maps to an empty array', async () => {
    const calls = stubFetch(() => new Response('{}'));
    await extbase.post('https://example.com/api', { tags: [], people: [{ tags: [] }] });

    const body = calls[0].init.body as FormData;
    expect([...body.entries()]).toEqual([
        ['tags', ''],
        ['people[0][tags]', ''],
    ]);
});
