import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

interface FieldErrorProps extends HTMLAttributes<HTMLDivElement> {
    message?: string | string[];
}

export function FieldError({ message, className, ...props }: FieldErrorProps) {
    if (!message) return null;

    const messages = Array.isArray(message) ? message : [message];

    return (
        <div {...props} className={cn('space-y-1', className)}>
            {messages.map((msg, index) => (
                <p key={index} className="text-destructive text-sm">
                    {msg}
                </p>
            ))}
        </div>
    );
}
