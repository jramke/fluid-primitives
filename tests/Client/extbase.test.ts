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
    // the prefix is an option of extbase, not something fetch should see
    expect(calls[0].init).not.toHaveProperty('prefix');
    expect(calls[0].init).not.toHaveProperty('objectName');
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

test('an objectName nests the data between the prefix and the keys, for data and for a FormData of fields', async () => {
    const calls = stubFetch(() => new Response('{}'));
    await extbase.post(
        ACTION_URL,
        { title: 'x', address: { city: 'Berlin' } },
        { objectName: 'event' }
    );
    await extbase.post('/save', { title: 'x' }, { objectName: 'event' });

    const form = new FormData();
    form.append('title', 'x');
    form.append('address.city', 'Berlin');
    form.append('attendees[0].name', 'Ann');
    form.append('tx_docs_docs[__trustedProperties]', 'signed');
    await extbase.post(ACTION_URL, form, { objectName: 'event' });
    await extbase.post('/save', form, { prefix: '', objectName: 'event' });
    await extbase.post('/save', form, { prefix: 'tx_docs_docs' });
    await extbase.get('/find', { q: 'x' }, { prefix: 'tx_docs_docs', objectName: 'demand' });

    const keys = (call: number) => [...(calls[call].init.body as FormData).keys()];
    expect(keys(0)).toEqual(['tx_docs_docs[event][title]', 'tx_docs_docs[event][address][city]']);
    expect(keys(1)).toEqual(['event[title]']);
    // a name that already starts with the prefix is one of Extbase's hidden fields
    expect(keys(2)).toEqual([
        'tx_docs_docs[event][title]',
        'tx_docs_docs[event][address][city]',
        'tx_docs_docs[event][attendees][0][name]',
        'tx_docs_docs[__trustedProperties]',
    ]);
    expect(keys(3)).toEqual([
        'event[title]',
        'event[address][city]',
        'event[attendees][0][name]',
        'event[tx_docs_docs][__trustedProperties]',
    ]);
    expect(keys(4)[0]).toBe('tx_docs_docs[title]');
    expect(
        new URL(calls[5].url, 'https://example.com').searchParams.get('tx_docs_docs[demand][q]')
    ).toBe('x');
    expect((calls[2].init.body as FormData).get('tx_docs_docs[__trustedProperties]')).toBe(
        'signed'
    );
});
