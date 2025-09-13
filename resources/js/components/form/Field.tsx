/**
 * Accessible form field wrapper component
 * Handles labels, hints, errors, and ARIA attributes
 */

import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { Info } from 'lucide-react';
import React from 'react';

export interface FieldProps {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    required?: boolean;
    className?: string;
    children: React.ReactNode;
}

export function Field({ id, label, hint, error, required = false, className, children }: FieldProps) {
    const errorId = error ? `${id}-error` : undefined;
    const hintId = hint ? `${id}-hint` : undefined;
    const describedBy = [errorId, hintId].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={id} className={cn(error && 'text-destructive')}>
                {label}
                {required && <span className="text-destructive ml-1">*</span>}
            </Label>

            {/* Inject ARIA attributes into the child input */}
            <div>
                {React.isValidElement(children) &&
                    // eslint-disable-next-line @typescript-eslint/no-explicit-any
                    React.cloneElement(children as React.ReactElement<any>, {
                        id,
                        'aria-invalid': error ? 'true' : undefined,
                        'aria-describedby': describedBy,
                        'aria-required': required ? 'true' : undefined,
                    })}
            </div>

            {hint && !error && (
                <div id={hintId} className="text-muted-foreground flex items-start gap-1.5 text-sm">
                    <Info className="mt-0.5 h-3 w-3 flex-shrink-0" />
                    <span>{hint}</span>
                </div>
            )}

            {error && (
                <div id={errorId} role="alert" aria-live="polite" className="text-destructive flex items-start gap-1.5 text-sm">
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}

export default Field;
