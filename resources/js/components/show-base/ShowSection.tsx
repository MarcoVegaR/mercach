import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { ReactNode, useEffect } from 'react';

interface ShowSectionProps {
    id: string;
    title: string;
    children: ReactNode;
    className?: string;
    loading?: boolean;
    lazyKey?: string;
    onLazyLoad?: () => void;
}

export function ShowSection({ id, title, children, className, loading = false, lazyKey, onLazyLoad }: ShowSectionProps) {
    useEffect(() => {
        // Trigger lazy load when section becomes active
        if (lazyKey && onLazyLoad) {
            onLazyLoad();
        }
    }, [lazyKey, onLazyLoad]);

    return (
        <section id={id} className={cn('scroll-mt-6', className)}>
            <h2 className="mb-3 text-xl font-semibold tracking-tight">{title}</h2>

            {loading ? (
                <div className="space-y-3">
                    <Skeleton className="h-4 w-full" />
                    <Skeleton className="h-4 w-3/4" />
                    <Skeleton className="h-4 w-1/2" />
                </div>
            ) : (
                <div className="space-y-4 leading-relaxed">{children}</div>
            )}
        </section>
    );
}
