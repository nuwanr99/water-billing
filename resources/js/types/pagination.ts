export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type DatatableFilters = {
    search: string;
    sort: string | null;
    direction: 'asc' | 'desc' | null;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};
