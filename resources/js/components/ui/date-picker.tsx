/* eslint-disable @typescript-eslint/no-explicit-any */
import * as React from 'react'
import type { DateRange as RDPDateRange } from 'react-day-picker'

import { Button } from '@/components/ui/button'
import { Calendar, type CalendarProps } from '@/components/ui/calendar'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'

// Use DayPicker's DateRange to match v9 types exactly
export type DateRange = RDPDateRange
export type DatePickerValue = Date | DateRange | undefined

export type DatePickerPreset = {
  label: string
  getValue: () => DatePickerValue
}

export type DatePickerProps = {
  id?: string
  mode: 'single' | 'range'
  value: DatePickerValue
  onChange: (value: DatePickerValue) => void
  presets?: DatePickerPreset[]
  minDate?: Date
  maxDate?: Date
  disabled?: CalendarProps['disabled']
  numberOfMonths?: number
  placeholder?: string
  withTime?: boolean
  timezoneHint?: string
  className?: string
}

function formatDate(d: Date) {
  try {
    return new Intl.DateTimeFormat(undefined, {
      year: 'numeric',
      month: 'short',
      day: '2-digit',
    }).format(d)
  } catch {
    return d.toLocaleDateString()
  }
}

function formatRange(r?: DateRange) {
  if (!r?.from && !r?.to) return ''
  if (r?.from && r?.to) return `${formatDate(r.from)} – ${formatDate(r.to)}`
  if (r?.from) return `${formatDate(r.from)} – …`
  if (r?.to) return `… – ${formatDate(r.to)}`
  return ''
}

export function DatePicker({
  id,
  mode,
  value,
  onChange,
  presets,
  minDate,
  maxDate,
  disabled,
  numberOfMonths,
  placeholder,
  timezoneHint,
  className,
}: DatePickerProps) {
  const [open, setOpen] = React.useState(false)
  const liveRef = React.useRef<HTMLDivElement>(null)

  function isDateValue(v: DatePickerValue): v is Date {
    return v instanceof Date
  }

  function isRangeValue(v: DatePickerValue): v is DateRange {
    return !!v && typeof v === 'object' && !(v instanceof Date)
  }

  const disabledMatchers = React.useMemo(() => {
    const arr: any[] = []
    if (minDate) arr.push({ before: minDate })
    if (maxDate) arr.push({ after: maxDate })
    if (Array.isArray(disabled)) arr.push(...disabled as any)
    else if (disabled) arr.push(disabled as any)
    return arr.length ? (arr as CalendarProps['disabled']) : undefined
  }, [minDate, maxDate, disabled])

  const label = React.useMemo(() => {
    if (mode === 'single') return value instanceof Date ? formatDate(value) : ''
    return formatRange(value as DateRange)
  }, [mode, value])

  React.useEffect(() => {
    if (mode === 'range') {
      const r = value as DateRange | undefined
      if (r?.from && r?.to && liveRef.current) {
        liveRef.current.textContent = `Rango seleccionado: ${formatRange(r)}`
      }
    }
  }, [mode, value])

  return (
    <div className={cn('grid gap-2', className)}>
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            id={id}
            variant="outline"
            className={cn(
              'w-[260px] justify-start text-left font-normal',
              !label && 'text-muted-foreground'
            )}
            aria-haspopup="dialog"
            aria-expanded={open || undefined}
          >
            {label || placeholder || (mode === 'single' ? 'Selecciona una fecha' : 'Selecciona un rango')}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <div className="flex flex-col gap-3 p-3">
            {presets?.length ? (
              <div className="flex flex-wrap gap-2">
                {presets.map((p) => (
                  <Button
                    key={p.label}
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange(p.getValue())}
                    aria-label={`Preset: ${p.label}`}
                  >
                    {p.label}
                  </Button>
                ))}
                {value && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={() => onChange(undefined)}
                  >
                    Limpiar
                  </Button>
                )}
              </div>
            ) : null}

            {mode === 'single' ? (
              <Calendar
                mode="single"
                selected={isDateValue(value) ? value : undefined}
                onSelect={(v?: Date) => onChange(v)}
                numberOfMonths={numberOfMonths ?? 1}
                disabled={disabledMatchers}
              />
            ) : (
              <Calendar
                mode="range"
                selected={isRangeValue(value) ? value : undefined}
                onSelect={(v?: DateRange) => onChange(v)}
                numberOfMonths={numberOfMonths ?? 2}
                disabled={disabledMatchers}
              />
            )}

            {timezoneHint ? (
              <p className="px-1 text-xs text-muted-foreground">{timezoneHint}</p>
            ) : null}
          </div>
        </PopoverContent>
      </Popover>
      <div ref={liveRef} className="sr-only" aria-live="polite" />
    </div>
  )
}
