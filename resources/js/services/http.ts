export class ApiError extends Error {
    constructor(
        message: string,
        public readonly status: number,
        public readonly payload: unknown,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

const API_BASE = '/api/v1';

export async function apiClient<T>(
    path: string,
    init: RequestInit = {},
): Promise<T> {
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');

    if (!headers.has('Content-Type') && init.body) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(`${API_BASE}${path}`, {
        ...init,
        headers,
    });

    if (!response.ok) {
        throw new ApiError(
            response.statusText,
            response.status,
            await response.json().catch(() => null),
        );
    }

    return (await response.json()) as T;
}
