import { cn } from '@/lib/utils';
import { HTMLAttributes, ReactNode } from 'react';

interface FormSectionProps extends HTMLAttributes<HTMLDivElement> {
    title?: string;
    description?: string;
    children: ReactNode;
}

export function FormSection({ title, description, children, className, ...props }: FormSectionProps) {
    return (
        <div {...props} className={cn('space-y-6', className)}>
            {(title || description) && (
                <div className="space-y-1">
                    {title && <h2 className="text-foreground text-lg font-semibold">{title}</h2>}
                    {description && <p className="text-muted-foreground text-sm">{description}</p>}
                </div>
            )}
            <div className="space-y-6">{children}</div>
        </div>
    );
}
