import { Badge } from '@/components/ui/badge';
import { X } from 'lucide-react';
import React from 'react';

interface FilterBadge {
    key: string;
    label: string;
    icon?: React.ReactNode;
    onRemove: () => void;
}

interface FilterBadgesProps {
    badges: FilterBadge[];
}

export function FilterBadges({ badges }: FilterBadgesProps) {
    if (badges.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-2">
            {badges.map((badge) => (
                <Badge key={badge.key} variant="secondary" className="flex items-center gap-1">
                    {badge.icon}
                    {badge.label}
                    <button onClick={badge.onRemove} className="ml-1 hover:text-red-500">
                        <X className="h-3 w-3" />
                    </button>
                </Badge>
            ))}
        </div>
    );
}
