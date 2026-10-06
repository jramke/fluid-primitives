/** Fallback signal only - see getExtbaseArgumentPrefix's doc comment for the primary, structural check. */
const DEFAULT_NAMESPACE_PATTERN = /^tx_[a-z0-9_]+$/i;

/**
 * Extracts the Extbase argument-namespace prefix (e.g. `tx_docs_docs`) that `f:uri.action`/
 * `f:uri.link` embed in every frontend Extbase action URL, so a payload can be bracket-prefixed
 * the way Extbase's argument mapper expects, without the server hydrating the prefix separately.
 *
 * Primarily looks for a query-parameter namespace carrying BOTH an `action` and a `controller`
 * sub-key - `UriBuilder::uriFor()` always nests those two together under one namespace key for
 * any frontend action URI, regardless of whether the namespace is the conventional
 * `tx_{extension}_{plugin}` or a custom one set via `plugin.tx_x.view.pluginNamespace`. Falls back
 * to the conventional `tx_..._...` shape if no such pair is found. Returns `null` for a URL with
 * neither - e.g. a plain REST endpoint - so callers can treat that as "not an Extbase URL".
 */
function getExtbaseArgumentPrefix(url: string): string | null {
    let query: URLSearchParams;
    try {
        query = new URL(url, window.location.origin).searchParams;
    } catch {
        return null;
    }

    const subKeysByPrefix = new Map<string, Set<string>>();
    for (const key of query.keys()) {
        const match = key.match(/^([^[\]]+)\[([^[\]]*)\]/);
        if (!match) continue;
        const [, prefix, subKey] = match;
        const subKeys = subKeysByPrefix.get(prefix) ?? new Set<string>();
        subKeys.add(subKey);
        subKeysByPrefix.set(prefix, subKeys);
    }

    for (const [prefix, subKeys] of subKeysByPrefix) {
        if (subKeys.has('action') && subKeys.has('controller')) return prefix;
    }
    for (const prefix of subKeysByPrefix.keys()) {
        if (DEFAULT_NAMESPACE_PATTERN.test(prefix)) return prefix;
    }
    return null;
}

/** Thrown for any non-2xx response; `response` still has its body unread, e.g. for a 422's JSON. */
export class ExtbaseHttpError extends Error {
    constructor(readonly response: Response) {
        super(`Request to ${response.url} failed with status ${response.status}`);
        this.name = 'ExtbaseHttpError';
    }

    get status(): number {
        return this.response.status;
    }
}

/**
 * Flattens `value` into bracket-notation entries under `key` (`demand[city]`, `ids[0]`), the way
 * Extbase's argument mapper expects them. Recurses into arrays and plain objects only, so a
 * `Blob`/`File` stays a leaf. `null`/`undefined` are skipped. Deliberately not shared with Form's
 * `prefixFieldName` (`Primitives/Form/src/form.path.ts`), which also carries dot-path parsing and
 * `objectName` wrapping this has no use for.
 */
function flatten(value: unknown, key: string, out: [string, unknown][] = []): [string, unknown][] {
    if (value === null || value === undefined) return out;
    if (Array.isArray(value)) {
        value.forEach((item, index) => flatten(item, `${key}[${index}]`, out));
    } else if (typeof value === 'object' && value.constructor === Object) {
        for (const [k, v] of Object.entries(value)) flatten(v, key ? `${key}[${k}]` : k, out);
    } else {
        out.push([key, value]);
    }
    return out;
}

async function request(url: string, init: RequestInit): Promise<Response> {
    const response = await fetch(url, init);
    if (!response.ok) throw new ExtbaseHttpError(response);
    return response;
}

/**
 * GETs an Extbase controller action URL, appending `data` as query parameters under the URL's own
 * Extbase argument namespace (read directly from `url`, nothing hydrated from the server). The
 * URL's existing query - including its `cHash` - is kept as-is, so the new parameters must be
 * excluded via `FE.cacheHash.excludedParameters` or the cHash check rejects the request.
 *
 * Rejects with `ExtbaseHttpError` on a non-2xx response. `init` is spread first so it can extend
 * the request (e.g. `{ signal }`) without overriding the method this function owns.
 */
async function getFromExtbase(
    url: string,
    data?: Record<string, unknown>,
    init?: RequestInit
): Promise<Response> {
    const target = new URL(url, window.location.origin);
    for (const [key, value] of flatten(data, getExtbaseArgumentPrefix(url) ?? '')) {
        target.searchParams.append(key, String(value));
    }

    return request(target.toString(), { ...init, method: 'GET' });
}

/**
 * POSTs `data` to an Extbase controller action URL, nested under the URL's own Extbase argument
 * namespace - same `prefix[key]` convention Form uses. If `url` doesn't look like an Extbase URL,
 * `data` is sent unprefixed instead (never warns/throws) - matching Form's own "no prefix
 * available -> send unprefixed" behavior, so this degrades gracefully into a plain POST helper for
 * non-Extbase REST endpoints too.
 *
 * The body is a real `multipart/form-data` FormData - what Extbase's argument mapper reads - unless
 * `init.headers` sets a JSON `Content-Type`, which sends `JSON.stringify` of the (prefix-nested)
 * `data` instead. Core doesn't decode JSON bodies, so that only reaches an Extbase action through
 * a body-parsing middleware. `init` is spread first so it can extend the request (e.g.
 * `{ signal }`) without overriding the method/body this function owns.
 *
 * Rejects with `ExtbaseHttpError` on a non-2xx response.
 */
async function postToExtbase(
    url: string,
    data: Record<string, unknown> = {},
    init?: RequestInit
): Promise<Response> {
    const prefix = getExtbaseArgumentPrefix(url);
    let body: BodyInit;

    if (new Headers(init?.headers).get('Content-Type')?.includes('application/json')) {
        body = JSON.stringify(prefix ? { [prefix]: data } : data);
    } else {
        const formData = new FormData();
        for (const [key, value] of flatten(data, prefix ?? '')) {
            formData.append(key, value instanceof Blob ? value : String(value));
        }
        body = formData;
    }

    return request(url, { ...init, method: 'POST', body });
}

export const extbase = {
    get: getFromExtbase,
    post: postToExtbase,
    getArgumentPrefix: getExtbaseArgumentPrefix,
};
