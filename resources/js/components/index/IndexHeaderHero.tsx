import { cn } from '@/lib/utils';
import React from 'react';

export type IndexHeaderHeroProps = {
    icon: React.ComponentType<{ className?: string }>;
    title: string;
    description?: string;
    actions?: React.ReactNode;
    className?: string;
};

export function IndexHeaderHero({ icon: Icon, title, description, actions, className }: IndexHeaderHeroProps) {
    return (
        <div className={cn('flex flex-col sm:flex-row sm:items-center sm:justify-between', className)}>
            <div className="flex items-center space-x-3">
                <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/30">
                    <Icon className="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                </div>
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{title}</h1>
                    {description ? <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{description}</p> : null}
                </div>
            </div>
            {actions ? <div className="mt-4 sm:mt-0">{actions}</div> : null}
        </div>
    );
}
