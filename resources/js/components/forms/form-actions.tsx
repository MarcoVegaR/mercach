import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { HTMLAttributes, ReactNode } from 'react';

interface FormActionsProps extends HTMLAttributes<HTMLDivElement> {
    children?: ReactNode;
    onCancel?: () => void;
    cancelText?: string;
    submitText?: string;
    isSubmitting?: boolean;
    isDirty?: boolean;
    align?: 'left' | 'right' | 'center' | 'between';
}

export function FormActions({
    children,
    onCancel,
    cancelText = 'Cancelar',
    submitText = 'Guardar',
    isSubmitting = false,
    isDirty = true,
    align = 'right',
    className,
    ...props
}: FormActionsProps) {
    const alignClass = {
        left: 'justify-start',
        right: 'justify-end',
        center: 'justify-center',
        between: 'justify-between',
    }[align];

    const ariaDisabled = !isDirty || isSubmitting;

    return (
        <div {...props} className={cn('flex gap-3', alignClass, className)}>
            {children ? (
                children
            ) : (
                <>
                    {onCancel && (
                        <Button
                            type="button"
                            variant="outline"
                            onMouseDown={(e) => {
                                // Evita que el botón tome el foco y dispare onBlur en inputs (no mostrar validación al cancelar)
                                e.preventDefault();
                            }}
                            onClick={onCancel}
                            disabled={isSubmitting}
                        >
                            {cancelText}
                        </Button>
                    )}
                    <TooltipProvider>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <span>
                                    <Button
                                        type="submit"
                                        aria-disabled={ariaDisabled}
                                        onClick={(e) => {
                                            if (ariaDisabled) {
                                                e.preventDefault();
                                                return;
                                            }
                                        }}
                                        title={ariaDisabled ? 'No hay cambios para guardar' : undefined}
                                        disabled={isSubmitting}
                                    >
                                        {isSubmitting ? 'Guardando...' : submitText}
                                    </Button>
                                </span>
                            </TooltipTrigger>
                            {ariaDisabled && <TooltipContent>No hay cambios para guardar</TooltipContent>}
                        </Tooltip>
                    </TooltipProvider>
                </>
            )}
        </div>
    );
}
