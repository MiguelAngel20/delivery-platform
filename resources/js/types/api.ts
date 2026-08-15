export type ApiResponse<T> = {
    data: T;
};

export type PaginatedMeta = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

export type PaginatedResponse<T> = {
    data: T[];
    meta: PaginatedMeta;
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};
