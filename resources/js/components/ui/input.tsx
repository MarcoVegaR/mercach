import * as React from 'react'

import { cn } from '@/lib/utils'

export interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  leadingIcon?: React.ElementType;
  leadingIconClassName?: string;
  trailingIcon?: React.ElementType;
  trailingIconClassName?: string;
}

const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, type, leadingIcon, leadingIconClassName, trailingIcon, trailingIconClassName, ...props }, ref) => {
    const hasLeadingIcon = !!leadingIcon;
    const hasTrailingIcon = !!trailingIcon;

    if (!hasLeadingIcon && !hasTrailingIcon) {
      return (
        <input
          type={type}
          className={cn(
            'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
            className
          )}
          ref={ref}
          {...props}
        />
      );
    }

    const LeadingIcon = leadingIcon;
    const TrailingIcon = trailingIcon;

    return (
      <div className="relative">
        {LeadingIcon && (
          <div className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
            <LeadingIcon className={cn('h-4 w-4 text-muted-foreground', leadingIconClassName)} />
          </div>
        )}
        <input
          type={type}
          className={cn(
            'flex h-10 w-full rounded-md border border-input bg-background py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
            hasLeadingIcon ? 'pl-9' : 'pl-3',
            hasTrailingIcon ? 'pr-9' : 'pr-3',
            className
          )}
          ref={ref}
          {...props}
        />
        {TrailingIcon && (
          <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
            <TrailingIcon className={cn('h-4 w-4 text-muted-foreground', trailingIconClassName)} />
          </div>
        )}
      </div>
    );
  }
);

Input.displayName = 'Input';

export { Input };
