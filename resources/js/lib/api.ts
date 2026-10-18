import { router } from '@inertiajs/vue3';

/**
 * Thin typed client for the v1 API. Requests are same-origin, so the Sanctum
 * session cookie travels automatically; the XSRF-TOKEN cookie Laravel sets on
 * every page response is echoed back in the X-XSRF-TOKEN header.
 */

export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

type Method = 'GET' | 'POST' | 'PATCH' | 'PUT' | 'DELETE';

type RequestOptions = {
    query?: Record<string, string | number | boolean | null | undefined>;
    body?: unknown;
    headers?: Record<string, string>;
    /** Set false for the auth bootstrap request itself. */
    redirectOnUnauthorized?: boolean;
};

const API_BASE = '/api/v1';

function readCookie(name: string): string | null {
    if (typeof document === 'undefined') {
        return null;
    }

    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

function buildUrl(path: string, query?: RequestOptions['query']): string {
    const url = new URL(`${API_BASE}${path}`, window.location.origin);

    Object.entries(query ?? {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, String(value));
        }
    });

    return `${url.pathname}${url.search}`;
}

async function request<T>(
    method: Method,
    path: string,
    options: RequestOptions = {},
): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...options.headers,
    };

    if (method !== 'GET') {
        const token = readCookie('XSRF-TOKEN');

        if (token) {
            headers['X-XSRF-TOKEN'] = token;
        }
    }

    let requestBody: BodyInit | undefined;

    if (typeof FormData !== 'undefined' && options.body instanceof FormData) {
        requestBody = options.body;
    } else if (options.body !== undefined) {
        headers['Content-Type'] = 'application/json';
        requestBody = JSON.stringify(options.body);
    }

    const response = await fetch(buildUrl(path, options.query), {
        method,
        headers,
        credentials: 'same-origin',
        body: requestBody,
    });

    if (response.status === 204) {
        return undefined as T;
    }

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        if (
            response.status === 401 &&
            (options.redirectOnUnauthorized ?? true)
        ) {
            router.visit('/login', { preserveState: false });
        }

        throw new ApiError(
            payload?.message ?? response.statusText,
            response.status,
            payload?.errors ?? {},
        );
    }

    return payload as T;
}

export const api = {
    get: <T>(path: string, options?: RequestOptions) =>
        request<T>('GET', path, options),
    post: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('POST', path, { ...options, body }),
    postFormData: <T>(path: string, body: FormData, options?: Omit<RequestOptions, 'body'>) =>
        request<T>('POST', path, { ...options, body }),
    patch: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('PATCH', path, { ...options, body }),
    put: <T>(path: string, body?: unknown, options?: RequestOptions) =>
        request<T>('PUT', path, { ...options, body }),
    delete: <T>(path: string, options?: RequestOptions) =>
        request<T>('DELETE', path, options),
};

/**
 * Issue a CSRF cookie for the current session. Laravel refreshes the session
 * token on login and logout, so call this before the first mutation after
 * either.
 */
export async function refreshCsrf(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });
}

export function errorMessage(error: unknown): string {
    if (error instanceof ApiError) {
        const first = Object.values(error.errors)[0]?.[0];

        return first ?? error.message;
    }

    return 'Something went wrong. Please try again.';
}
