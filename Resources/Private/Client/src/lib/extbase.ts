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

/**
 * Small, flat-key-only sibling of Form's `prefixFieldName`
 * (`Primitives/Form/src/form.path.ts`) - deliberately not shared with it, since that function also
 * carries dot-path parsing, `objectName` wrapping and array-append syntax this helper has no use
 * for and would silently inherit changes to.
 */
function prefixKey(key: string, prefix: string | null): string {
    return prefix ? `${prefix}[${key}]` : key;
}

function buildExtbaseBody(url: string, data?: Record<string, unknown>): FormData {
    const prefix = getExtbaseArgumentPrefix(url);
    const formData = new FormData();

    for (const [key, value] of Object.entries(data ?? {})) {
        if (value === null || value === undefined) continue;
        formData.append(prefixKey(key, prefix), String(value));
    }

    return formData;
}

/**
 * POSTs a flat key/value payload to an Extbase controller action URL, bracket-prefixing each key
 * under the URL's own Extbase argument namespace - same `prefix[key]` convention Form uses. The
 * prefix is read directly from `url`; nothing needs to be hydrated from the server. If `url`
 * doesn't look like an Extbase URL, `data` is sent unprefixed instead (never warns/throws) -
 * matching Form's own "no prefix available -> send unprefixed" behavior, so this degrades
 * gracefully into a plain "POST as FormData" helper for non-Extbase REST endpoints too.
 *
 * Always a real `multipart/form-data` FormData body (never JSON/urlencoded), matching Form.
 * `null`/`undefined` values in `data` are skipped. `init` is spread first so it can extend the
 * request (e.g. `{ signal }`) without overriding the method/body this function owns.
 */
async function postToExtbase(
    url: string,
    data?: Record<string, unknown>,
    init?: RequestInit
): Promise<Response> {
    return fetch(url, { ...init, method: 'POST', body: buildExtbaseBody(url, data) });
}

/** Header carrying the per-intent key the server dedupes retried requests on. */
export const IDEMPOTENCY_HEADER = 'X-Idempotency-Key';

export interface ExtbaseRequestOptions {
    signal?: AbortSignal;
    /** Sent as `X-Idempotency-Key`, so the server can replay the original response on a retry. */
    idempotencyKey?: string;
}

export type ExtbaseRequestResult<T = unknown> =
    | { ok: true; status: number; data: T | null }
    | {
          ok: false;
          /** HTTP status, or `0` for a network failure / aborted request. */
          status: number;
          /** Field -> messages map, only set for a 422 with a JSON body in that shape. */
          errors?: Record<string, string[]>;
          data?: unknown;
      };

function isErrorMap(value: unknown): value is Record<string, string[]> {
    return (
        typeof value === 'object' &&
        value !== null &&
        !Array.isArray(value) &&
        Object.values(value).every(
            messages => Array.isArray(messages) && messages.every(m => typeof m === 'string')
        )
    );
}

/**
 * Like `post`, but never throws for HTTP/network failures - it resolves to a result object, which
 * is the shape `OptimisticAction` treats as success/failure. Sends `Accept: application/json`,
 * parses a JSON body when the response has one, and maps a 422 body in the
 * `{ field: [messages] }` shape (what `AjaxValidationTrait` emits) to `errors`.
 * An aborted request resolves to `{ ok: false, status: 0 }` as well.
 */
async function requestExtbase<T = unknown>(
    url: string,
    data?: Record<string, unknown>,
    options: ExtbaseRequestOptions = {}
): Promise<ExtbaseRequestResult<T>> {
    const headers: Record<string, string> = { Accept: 'application/json' };
    if (options.idempotencyKey) headers[IDEMPOTENCY_HEADER] = options.idempotencyKey;

    let response: Response;
    try {
        response = await fetch(url, {
            method: 'POST',
            body: buildExtbaseBody(url, data),
            headers,
            signal: options.signal,
        });
    } catch {
        return { ok: false, status: 0 };
    }

    let body: unknown = null;
    if ((response.headers.get('content-type') ?? '').includes('json')) {
        try {
            body = await response.json();
        } catch {
            body = null;
        }
    }

    if (response.ok) return { ok: true, status: response.status, data: body as T | null };

    const result: ExtbaseRequestResult<T> = { ok: false, status: response.status };
    if (body !== null) result.data = body;
    if (response.status === 422 && isErrorMap(body)) result.errors = body;
    return result;
}

export const extbase = {
    post: postToExtbase,
    request: requestExtbase,
    getArgumentPrefix: getExtbaseArgumentPrefix,
};
