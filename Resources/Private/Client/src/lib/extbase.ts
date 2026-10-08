/**
 * Reads the Extbase argument namespace (`tx_docs_docs`) of a frontend action URL, from its
 * `[controller]` or `[action]` query key. `UriBuilder::uriFor()` nests those under it, also for a
 * custom `pluginNamespace`.
 *
 * @param url - The URL of the action.
 * @returns The namespace, or `null` for a URL without such a key.
 */
function getArgumentPrefix(url: string): string | null {
    for (const key of new URL(url, document.baseURI).searchParams.keys()) {
        const prefix = key.match(/^([^[\]]+)\[(?:action|controller)\]$/)?.[1];
        if (prefix) return prefix;
    }
    return null;
}

/** Bracket notation (`demand[city]`, `ids[0]`) is what Extbase's argument mapper reads. */
function flatten(value: unknown, key: string): [string, string | Blob][] {
    if (value == null) return [];
    if (Array.isArray(value) || value.constructor === Object) {
        return Object.entries(value).flatMap(([k, v]) => flatten(v, key ? `${key}[${k}]` : k));
    }
    return [[key, value instanceof Blob ? value : String(value)]];
}

/**
 * Sends GET and POST requests to an Extbase controller action from your own code, e.g. from the
 * `load` callback of an async search. The data is nested under the Extbase argument namespace of the
 * URL, the way `f:uri.action` builds it.
 */
export const extbase = {
    /**
     * GETs `url` with `data` appended as query parameters under its Extbase argument namespace. The
     * URL's own query, `cHash` included, is kept - so the new parameters have to be listed in
     * `FE.cacheHash.excludedParameters`, or TYPO3 rejects the request.
     *
     * @param url - The URL of the action, absolute or relative.
     * @param data - The arguments. Nested objects and arrays are flattened into bracket notation.
     * @param init - More options for `fetch`, e.g. `{ signal }`.
     * @returns The native `Response`. A non-2xx status resolves normally, check `response.ok`.
     */
    async get(url: string, data?: Record<string, unknown>, init?: RequestInit): Promise<Response> {
        const target = new URL(url, document.baseURI);
        for (const [key, value] of flatten(data, getArgumentPrefix(url) ?? '')) {
            target.searchParams.append(key, String(value));
        }
        return fetch(target, { ...init, method: 'GET' });
    },

    /**
     * POSTs `data` as `multipart/form-data`, nested under the URL's Extbase argument namespace. A
     * URL without one gets `data` unprefixed.
     *
     * @param url - The URL of the action.
     * @param data - The arguments. Nested objects and arrays are flattened into bracket notation, `null`
     * and `undefined` are skipped, a `File` or `Blob` is sent as it is.
     * @param init - More options for `fetch`, e.g. `{ signal }`.
     * @returns The native `Response`. A non-2xx status resolves normally, check `response.ok`.
     */
    async post(url: string, data?: Record<string, unknown>, init?: RequestInit): Promise<Response> {
        const body = new FormData();
        for (const [key, value] of flatten(data, getArgumentPrefix(url) ?? '')) {
            body.append(key, value);
        }
        return fetch(url, { ...init, method: 'POST', body });
    },

    getArgumentPrefix,
};
