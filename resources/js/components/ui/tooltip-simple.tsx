import * as React from 'react'
import {
  TooltipProvider,
  Tooltip as TooltipRoot,
  TooltipTrigger,
  TooltipContent,
} from '@/components/ui/tooltip'

export type SimpleTooltipProps = {
  content: React.ReactNode
  children: React.ReactElement
  side?: 'top' | 'right' | 'bottom' | 'left'
  delayDuration?: number
  disabled?: boolean
  className?: string
}

export function SimpleTooltip({
  content,
  children,
  side = 'top',
  delayDuration = 200,
  disabled,
  className,
}: SimpleTooltipProps) {
  if (disabled) return children

  return (
    <TooltipProvider delayDuration={delayDuration}>
      <TooltipRoot>
        <TooltipTrigger asChild>{children}</TooltipTrigger>
        <TooltipContent side={side} className={className}>
          {typeof content === 'string' ? <span>{content}</span> : content}
        </TooltipContent>
      </TooltipRoot>
    </TooltipProvider>
  )
}
