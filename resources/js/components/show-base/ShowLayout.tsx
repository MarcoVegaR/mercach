import { cn } from '@/lib/utils';
import { ReactNode } from 'react';

interface ShowLayoutProps {
    header: ReactNode;
    actions?: ReactNode;
    children: ReactNode;
    aside?: ReactNode;
    className?: string;
}

export function ShowLayout({ header, actions, children, aside, className }: ShowLayoutProps) {
    return (
        <div className={cn('container mx-auto px-4 py-6 lg:px-8', className)}>
            {/* Skip to content link for accessibility (WCAG 2.4.1) */}
            <a
                href="#main-content"
                className="bg-background sr-only rounded-md border px-4 py-2 focus:not-sr-only focus:absolute focus:top-4 focus:left-4"
            >
                Skip to content
            </a>

            {/* Header with actions */}
            <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div className="mb-4 sm:mb-0">{header}</div>
                {actions && <div className="flex gap-2">{actions}</div>}
            </div>

            {/* Responsive grid layout */}
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                {/* Main content */}
                <main id="main-content" className={cn('lg:col-span-8', !aside && 'lg:col-span-12')}>
                    {children}
                </main>

                {/* Sticky aside for desktop */}
                {aside && (
                    <aside className="lg:col-span-4">
                        <div className="space-y-4 lg:sticky lg:top-6">{aside}</div>
                    </aside>
                )}
            </div>
        </div>
    );
}
