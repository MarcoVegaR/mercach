/**
 * Accessible form field wrapper component
 * Handles labels, hints, errors, and ARIA attributes
 */

import { Label } from '@/components/ui/label';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
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
    /** Small help tooltip rendered next to the label */
    tooltip?: React.ReactNode;
    /** Show tooltip icon (default: false for clean UI) */
    showTooltip?: boolean;
    /** Hide required indicator */
    hideRequired?: boolean;
    children: React.ReactNode;
}

export function Field({
    id,
    label,
    hint,
    error,
    required = false,
    className,
    tooltip,
    showTooltip: _showTooltip = false,
    hideRequired = false,
    children,
}: FieldProps) {
    const errorId = error ? `${id}-error` : undefined;
    const hintId = hint ? `${id}-hint` : undefined;
    const describedBy = [errorId, hintId].filter(Boolean).join(' ') || undefined;

    // Removed icon logic - icons now handled by input components

    return (
        <div className={cn('space-y-2', className)}>
            <Label htmlFor={id} className={cn('group flex items-center gap-1.5 text-sm font-medium', error && 'text-destructive')}>
                <span className="flex items-center gap-1">
                    {label}
                    {required && !hideRequired && <span className="text-destructive">*</span>}
                </span>
                {(tooltip || hint) && (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                type="button"
                                className="text-muted-foreground/50 hover:text-muted-foreground transition-colors focus:outline-none"
                                aria-label="Más información"
                            >
                                <Info className="h-3.5 w-3.5" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent side="top" className="max-w-xs">
                            <div className="text-xs leading-relaxed">{tooltip || hint}</div>
                        </TooltipContent>
                    </Tooltip>
                )}
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

            {error && (
                <div id={errorId} role="alert" aria-live="polite" className="text-destructive flex items-start gap-1.5 text-sm">
                    <span>{error}</span>
                </div>
            )}
        </div>
    );
}

export default Field;
