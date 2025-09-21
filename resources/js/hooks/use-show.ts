import type { ShowMeta, ShowQuery } from '@/types/ShowQuery';
import { useRemember } from '@inertiajs/react';
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
    // Use local state to manage item and meta data since we're using fetch for dynamic loading
    const [item, setItem] = useState<T>(initialItem);
    const [meta, setMeta] = useState<ShowMeta>(initialMeta);
    const [loading, setLoading] = useState(false);
    const [activeTab, setActiveTab] = useRemember<string>('overview', `show:${endpoint}:activeTab`);

    const loadPart = useCallback(
        async (query: ShowQuery = {}) => {
            setLoading(true);
            try {
                // Use the /data endpoint for dynamic loading with fetch
                const dataEndpoint = `${endpoint}/data`;
                const params = new URLSearchParams();

                // Add query parameters
                Object.entries(query).forEach(([key, value]) => {
                    if (Array.isArray(value)) {
                        value.forEach((v) => params.append(`${key}[]`, String(v)));
                    } else if (value !== undefined && value !== null) {
                        params.append(key, String(value));
                    }
                });

                const response = await fetch(`${dataEndpoint}?${params.toString()}`, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to load data');
                }

                const data = await response.json();

                // Update local state with new data
                setItem(data.item);
                setMeta(data.meta);
            } catch (error) {
                console.error('Error loading data:', error);
            } finally {
                setLoading(false);
            }
        },
        [endpoint],
    );

    return {
        item,
        meta,
        loading,
        activeTab,
        setActiveTab,
        loadPart,
    };
}
