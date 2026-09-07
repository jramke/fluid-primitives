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
    const prefix = getExtbaseArgumentPrefix(url);
    const formData = new FormData();

    for (const [key, value] of Object.entries(data ?? {})) {
        if (value === null || value === undefined) continue;
        formData.append(prefixKey(key, prefix), String(value));
    }

    return fetch(url, { ...init, method: 'POST', body: formData });
}

export const extbase = {
    post: postToExtbase,
    getArgumentPrefix: getExtbaseArgumentPrefix,
};
