import { parseFieldPath, stringifyFieldPathAsBrackets } from './field-path';

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

/** The name every key of the data is nested under: `tx_docs_docs[event]`, `event` or `''`. */
function baseName(prefix: string, objectName: string | undefined): string {
    return stringifyFieldPathAsBrackets([prefix, objectName ?? ''].filter(Boolean));
}

/**
 * Names the fields of a form the way Extbase expects them: `address.city` becomes
 * `tx_docs_docs[event][address][city]`. A name that already starts with the prefix is Extbase's own
 * hidden field (`__trustedProperties`, `__identity`) and is kept.
 */
function nameFormData(data: FormData, prefix: string, objectName: string | undefined): FormData {
    const body = new FormData();
    const head = [prefix, objectName ?? ''].filter(Boolean);
    for (const [key, value] of data.entries()) {
        const named =
            key === '' || (prefix && key.startsWith(`${prefix}[`))
                ? key
                : stringifyFieldPathAsBrackets([...head, ...parseFieldPath(key)]);
        body.append(named, value);
    }
    return body;
}

/**
 * Thrown by {@link extbase} when the action answers `422` with a JSON object, the response
 * `JsonValidationErrorsTrait` sends for failed validation. Any other status resolves as a plain `Response`.
 */
export class ExtbaseValidationError extends Error {
    /**
     * @param errors - The messages by property path, e.g. `{ 'person.email': ['Invalid email'] }`.
     * @param response - The `422` response, its body still unread.
     */
    constructor(
        public errors: Record<string, string[]>,
        public response: Response
    ) {
        super('Extbase validation failed');
        this.name = 'ExtbaseValidationError';
    }
}

/** The options of {@link extbase}'s requests: everything `fetch` takes, plus the names to send the data under. */
export type ExtbaseRequestInit = RequestInit & {
    /**
     * The Extbase argument namespace (`tx_docs_docs`) the data is nested under. Read from the URL when
     * omitted, which finds none for a URL without an `[action]` or `[controller]` key, e.g. one a route
     * enhancer built: pass it then. `''` sends the data unprefixed.
     */
    prefix?: string;
    /** The name of the object the action takes, nested between the prefix and the keys: `tx_docs_docs[event][title]`. */
    objectName?: string;
};

async function send(input: URL | string, init: RequestInit): Promise<Response> {
    const response = await fetch(input, init);
    if (response.status === 422) {
        const errors: unknown = await response
            .clone()
            .json()
            .catch(() => null);
        if (errors !== null && typeof errors === 'object' && !Array.isArray(errors)) {
            throw new ExtbaseValidationError(errors as Record<string, string[]>, response);
        }
    }
    return response;
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
     * @param options - More options for `fetch`, e.g. `{ signal }`, and the `prefix` and `objectName` to nest the data under.
     * @returns The native `Response`. A non-2xx status resolves normally, check `response.ok`, except a
     * `422` with a JSON object as body, which throws an {@link ExtbaseValidationError}.
     */
    async get(
        url: string,
        data?: Record<string, unknown>,
        options?: ExtbaseRequestInit
    ): Promise<Response> {
        const { prefix = getArgumentPrefix(url) ?? '', objectName, ...init } = options ?? {};
        const target = new URL(url, document.baseURI);
        for (const [key, value] of flatten(data, baseName(prefix, objectName))) {
            target.searchParams.append(key, String(value));
        }
        return send(target, { ...init, method: 'GET' });
    },

    /**
     * POSTs `data` as `multipart/form-data`, nested under the URL's Extbase argument namespace. A
     * URL without one gets `data` unprefixed.
     *
     * @param url - The URL of the action.
     * @param data - The arguments. Nested objects and arrays are flattened into bracket notation, `null`
     * and `undefined` are skipped, a `File` or `Blob` is sent as it is. The names of a `FormData` (dot or
     * bracket notation) are nested the same way, except those that already start with the prefix.
     * @param options - More options for `fetch`, e.g. `{ signal }`, and the `prefix` and `objectName` to nest the data under.
     * @returns The native `Response`. A non-2xx status resolves normally, check `response.ok`, except a
     * `422` with a JSON object as body, which throws an {@link ExtbaseValidationError}.
     */
    async post(
        url: string,
        data?: Record<string, unknown> | FormData,
        options?: ExtbaseRequestInit
    ): Promise<Response> {
        const { prefix = getArgumentPrefix(url) ?? '', objectName, ...init } = options ?? {};
        let body: FormData;
        if (data instanceof FormData) {
            body = nameFormData(data, prefix, objectName);
        } else {
            body = new FormData();
            for (const [key, value] of flatten(data, baseName(prefix, objectName))) {
                body.append(key, value);
            }
        }
        return send(url, { ...init, method: 'POST', body });
    },

    getArgumentPrefix,
};
