export interface Executive {
    id: string;
    name: string;
    expertise: string[];
    technical_summary: string[];
}

export interface Asset {
    id: string;
    name: string;
    commodities: string[];
    status: string | null;
    country: string | null;
    state_province: string | null;
    town: string | null;
    latitude: number | null;
    longitude: number | null;
}

export interface Company {
    id: string;
    name: string;
    created_at: string;
    executives?: Executive[];
    assets?: Asset[];
}

export interface PaginatedResponse<T> {
    current_page: number;
    data: T[];
    first_page_url: string;
    last_page: number;
    last_page_url: string;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number;
    total: number;
}