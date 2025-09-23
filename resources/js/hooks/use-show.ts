import type { ShowMeta, ShowQuery } from '@/types/ShowQuery';
import type { RequestPayload } from '@inertiajs/core';
import { router, usePage, useRemember } from '@inertiajs/react';
import { useCallback, useState } from 'react';

interface UseShowOptions<T = unknown> {
    endpoint: string;
    initialItem: T;
    initialMeta: ShowMeta;
    initialQuery?: ShowQuery;
}

interface UseShowReturn<T = unknown> {
    item: T;
    meta: ShowMeta;
    loading: boolean;
    activeTab: string;
    setActiveTab: (tab: string) => void;
    loadPart: (query: ShowQuery) => void;
}

export function useShow<T = unknown>({ endpoint, initialItem, initialMeta }: UseShowOptions<T>): UseShowReturn<T> {
    // Always read latest props from Inertia so partial reloads (only: ['item', 'meta']) are reflected
    const {
        props: { item = initialItem, meta = initialMeta },
    } = usePage<{ item: T; meta: ShowMeta }>();
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useRemember<string>('overview', `show:${endpoint}:activeTab`);

    const loadPart = useCallback((query: ShowQuery = {}) => {
        setLoading(true);
        router.visit(window.location.pathname, {
            only: ['item', 'meta'],
            data: query as RequestPayload,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setLoading(false),
        });
    }, []);

    return {
        item,
        meta,
        loading,
        activeTab,
        setActiveTab,
        loadPart,
    };
}
