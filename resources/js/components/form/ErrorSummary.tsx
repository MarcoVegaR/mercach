/**
 * Error summary component for forms
 * Lists all errors with links to the corresponding fields
 */

import { cn } from '@/lib/utils';
import { AlertCircle } from 'lucide-react';
import React from 'react';

export interface ErrorSummaryProps {
    errors: Record<string, string | undefined>;
    title?: string;
    className?: string;
}

export function ErrorSummary({ errors, title = 'Por favor, corrige los siguientes errores:', className }: ErrorSummaryProps) {
    const errorEntries = Object.entries(errors).filter(([_, error]) => error);

    if (errorEntries.length === 0) {
        return null;
    }

    const handleErrorClick = (fieldName: string) => (e: React.MouseEvent) => {
        e.preventDefault();

        // Try to find and focus the field
        const selectors = [`[name="${fieldName}"]`, `#${fieldName}`, `[data-field="${fieldName}"]`];

        for (const selector of selectors) {
            try {
                const element = document.querySelector(selector);
                if (element instanceof HTMLElement) {
                    element.focus();
                    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    break;
                }
            } catch {
                // Ignore selector errors
            }
        }
    };

    return (
        <div role="alert" aria-live="assertive" className={cn('border-destructive/50 bg-destructive/10 rounded-lg border p-4', className)}>
            <div className="flex gap-2">
                <AlertCircle className="text-destructive h-5 w-5 flex-shrink-0" />
                <div className="flex-1 space-y-2">
                    <p className="text-destructive font-medium">{title}</p>
                    <ul className="space-y-1 text-sm">
                        {errorEntries.map(([fieldName, error]) => (
                            <li key={fieldName}>
                                <a
                                    href={`#${fieldName}`}
                                    onClick={handleErrorClick(fieldName)}
                                    className="text-destructive underline-offset-2 hover:underline"
                                >
                                    {error}
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default ErrorSummary;
