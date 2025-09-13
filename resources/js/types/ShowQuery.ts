export interface ShowQuery {
    with?: string[];
    withCount?: string[];
    append?: string[];
    withTrashed?: boolean;
}

export interface ShowMeta {
    loaded_relations?: string[];
    loaded_counts?: string[];
    appended?: string[];
}

export interface ShowResponse<T = unknown> {
    item: T;
    meta: ShowMeta;
}
