/* eslint-disable */
import * as React from "react"
import { useEffect, useId, useMemo, useRef, useState } from "react"
import { CheckIcon, ChevronsUpDownIcon, XIcon } from "lucide-react"

import { cn } from "@/lib/utils"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
  Popover,
  PopoverTrigger,
  PopoverContent,
} from "@/components/ui/popover"
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command"

export type Option = {
  value: string
  label: string
  disabled?: boolean
  group?: string
}

export type ComboboxProps = {
  id?: string
  options: Option[]
  value: string | string[]
  onChange: (v: string | string[]) => void
  multiple?: boolean
  placeholder?: string
  searchPlaceholder?: string
  emptyText?: string
  disabled?: boolean

  // Chips
  chipVariant?: "badge" | "pill"
  chipClassName?: string
  onRemoveChip?: (v: string) => void
  clearAllLabel?: string

  // UX
  autoFocus?: boolean
  closeOnSelect?: boolean
  maxPopoverHeight?: number

  // A11y
  ariaLabel?: string
  getOptionId?: (opt: Option) => string

  // Advanced
  allowCreate?: boolean
  renderOption?: (
    opt: Option,
    state: { active: boolean; selected: boolean }
  ) => React.ReactNode
  renderValue?: (selected: Option[]) => React.ReactNode

  className?: string
}

function useGroupedOptions(options: Option[]) {
  return useMemo(() => {
    const groups = new Map<string | undefined, Option[]>()
    for (const opt of options) {
      const key = opt.group
      const arr = groups.get(key) ?? []
      arr.push(opt)
      groups.set(key, arr)
    }
    return groups
  }, [options])
}

function normalizeValue(value: string | string[], multiple?: boolean): string[] {
  if (multiple) return Array.isArray(value) ? value : value ? [value] : []
  return typeof value === "string" && value ? [value] : []
}

function getLabelFor(options: Option[], value: string): string | undefined {
  return options.find((o) => o.value === value)?.label
}

function defaultGetOptionId(opt: Option) {
  return `opt-${opt.group ? opt.group + "-" : ""}${opt.value}`
}

export function Combobox({
  id,
  options,
  value,
  onChange,
  multiple = false,
  placeholder = "Selecciona…",
  searchPlaceholder = "Buscar…",
  emptyText = "Sin coincidencias",
  disabled,
  chipVariant = "pill",
  chipClassName,
  onRemoveChip,
  clearAllLabel = "Quitar todos",
  autoFocus,
  closeOnSelect,
  maxPopoverHeight = 280,
  ariaLabel,
  getOptionId = defaultGetOptionId,
  allowCreate,
  renderOption,
  renderValue,
  className,
}: ComboboxProps) {
  const reactId = useId()
  const baseId = id ?? `combobox-${reactId}`
  const listId = `${baseId}-listbox`

  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState("")
  const [activeId, setActiveId] = useState<string | undefined>(undefined)
  const listRef = useRef<HTMLDivElement | null>(null)

  // Debug helper
  const debug = (...args: any[]) => {
    // Only during development this will be useful; leave unconditional per request
    console.debug(`[Combobox:${baseId}]`, ...args)
  }

  const selectedValues = normalizeValue(value, multiple)
  const selectedOptions = useMemo(
    () => options.filter((o) => selectedValues.includes(o.value)),
    [options, selectedValues]
  )

  const grouped = useGroupedOptions(options)

  // Track the currently active option (as set by cmdk) for aria-activedescendant
  useEffect(() => {
    if (!open) {
      setActiveId(undefined)
      return
    }
    const el = listRef.current
    if (!el) return

    const updateActive = () => {
      const node = el.querySelector<HTMLElement>("[data-selected='true']")
      setActiveId(node?.id || undefined)
    }

    updateActive()
    const mo = new MutationObserver(updateActive)
    mo.observe(el, {
      attributes: true,
      childList: true,
      subtree: true,
      attributeFilter: ["aria-selected", "data-selected"],
    })

    return () => mo.disconnect()
  }, [open, query, options])

  const handleSelect = React.useCallback((value: string) => {
    debug('handleSelect', { value, multiple, selectedValues })
    if (multiple) {
      const exists = selectedValues.includes(value)
      const next = exists
        ? selectedValues.filter((v) => v !== value)
        : [...selectedValues, value]
      debug('onChange ->', next)
      onChange(next)
      if (exists && onRemoveChip) onRemoveChip(value)
      if ((closeOnSelect ?? false) === true) setOpen(false)
    } else {
      debug('onChange ->', value)
      onChange(value)
      if (closeOnSelect ?? true) setOpen(false)
    }
  }, [multiple, selectedValues, onChange, onRemoveChip, closeOnSelect])

  const handleCreate = () => {
    const newVal = query.trim()
    if (!newVal) return
    debug('handleCreate', { newVal, multiple })
    if (multiple) {
      if (!selectedValues.includes(newVal)) {
        const next = [...selectedValues, newVal]
        debug('onChange ->', next)
        onChange(next)
      }
      if ((closeOnSelect ?? false) === true) setOpen(false)
    } else {
      debug('onChange ->', newVal)
      onChange(newVal)
      if (closeOnSelect ?? true) setOpen(false)
    }
  }

  const removeChip = (val: string) => {
    if (!multiple) return
    debug('removeChip', val)
    const next = selectedValues.filter((v) => v !== val)
    debug('onChange ->', next)
    onChange(next)
    onRemoveChip?.(val)
  }

  const clearAll = () => {
    debug('clearAll')
    if (multiple) onChange([])
  }

  const isSelected = (val: string) => selectedValues.includes(val)

  const highlight = (label: string, q: string) => {
    if (!q) return label
    const idx = label.toLowerCase().indexOf(q.toLowerCase())
    if (idx === -1) return label
    const before = label.slice(0, idx)
    const match = label.slice(idx, idx + q.length)
    const after = label.slice(idx + q.length)
    return (
      <span>
        {before}
        <mark className="bg-transparent font-semibold underline decoration-accent underline-offset-2">
          {match}
        </mark>
        {after}
      </span>
    )
  }

  const renderChip = (opt: Option) => {
    if (chipVariant === "pill") {
      return (
        <span
          key={opt.value}
          className={cn(
            "inline-flex items-center gap-1.5 rounded-full border border-border/60 h-6 md:h-7 px-2 md:px-2.5 text-xs leading-none bg-muted text-muted-foreground hover:bg-accent/60 focus-within:outline-hidden focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2",
            chipClassName
          )}
          role="group"
          title={opt.label}
        >
          <span className="max-w-[12rem] truncate">{opt.label}</span>
          <span
            role="button"
            tabIndex={0}
            className="text-muted-foreground hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring rounded-sm cursor-pointer"
            aria-label={`Quitar ${opt.label}`}
            onClick={(e) => {
              e.stopPropagation()
              removeChip(opt.value)
            }}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault()
                e.stopPropagation()
                removeChip(opt.value)
              }
            }}
            onMouseDown={(e) => e.preventDefault()}
          >
            <XIcon className="size-3.5" />
          </span>
        </span>
      )
    }

    // badge by default
    return (
      <Badge
        key={opt.value}
        variant="secondary"
        className={cn(
          "inline-flex items-center gap-1.5 h-6 md:h-7 px-2 md:px-2.5 text-xs leading-none",
          chipClassName
        )}
        title={opt.label}
      >
        <span className="max-w-[12rem] truncate">{opt.label}</span>
        <span
          role="button"
          tabIndex={0}
          className="text-muted-foreground hover:text-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring rounded-sm cursor-pointer"
          aria-label={`Quitar ${opt.label}`}
          onClick={(e) => {
            e.stopPropagation()
            removeChip(opt.value)
          }}
          onKeyDown={(e) => {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault()
              e.stopPropagation()
              removeChip(opt.value)
            }
          }}
          onMouseDown={(e) => e.preventDefault()}
        >
          <XIcon className="size-3.5" />
        </span>
      </Badge>
    )
  }

  const displayValue = () => {
    if (renderValue) return renderValue(selectedOptions)
    if (multiple) {
      if (selectedOptions.length === 0) return (
        <span className="text-muted-foreground">{placeholder}</span>
      )
      return (
        <span className="flex flex-wrap gap-1.5">
          {selectedOptions.map(renderChip)}
        </span>
      )
    }
    const single = selectedOptions[0]
    return single ? (
      <span className="truncate">{single.label}</span>
    ) : (
      <span className="text-muted-foreground">{placeholder}</span>
    )
  }

  const maybeShowCreate =
    !!allowCreate && !!query.trim() && !options.some((o) => o.label.toLowerCase() === query.trim().toLowerCase())

  return (
    <Popover open={open} onOpenChange={(o) => {
      debug('onOpenChange', o)
      setOpen(o)
      if (!o) setQuery("")
    }}>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          role="combobox"
          aria-expanded={open}
          aria-controls={listId}
          aria-haspopup="listbox"
          aria-activedescendant={activeId}
          aria-label={ariaLabel}
          disabled={disabled}
          className={cn(
            "w-full justify-between",
            multiple && "min-h-9",
            className
          )}
          onClick={() => debug('trigger click')}
        >
          <span className="flex min-w-0 grow items-center gap-1.5 text-left">
            {displayValue()}
          </span>
          <ChevronsUpDownIcon className="ml-2 size-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className="p-0" align="start">
        <Command
          filter={(value, search) => {
            // default cmdk filter; return 1 for keep, 0 for hide
            if (!search) return 1
            return value.toLowerCase().includes(search.toLowerCase()) ? 1 : 0
          }}
          onValueChange={(val) => {
            debug('query change', val)
            setQuery(val)
          }}
        >
          <CommandInput
            placeholder={searchPlaceholder}
            aria-activedescendant={activeId}
            onKeyDown={(e) => {
              if (
                multiple &&
                (e.key === "Backspace" || e.key === "Delete") &&
                !query &&
                selectedValues.length > 0
              ) {
                e.preventDefault()
                debug('backspace/delete removes last chip', selectedValues[selectedValues.length - 1])
                removeChip(selectedValues[selectedValues.length - 1])
              }
            }}
            autoFocus={autoFocus}
          />
          <CommandList
            id={listId}
            role="listbox"
            ref={listRef}
            aria-multiselectable={multiple || undefined}
            style={{ maxHeight: maxPopoverHeight }}
          >
            {maybeShowCreate && (
              <CommandGroup>
                <CommandItem
                  value={`__create__:${query}`}
                  onSelect={handleCreate}
                >
                  Crear: {query}
                </CommandItem>
                <CommandSeparator />
              </CommandGroup>
            )}

            <CommandEmpty>{emptyText}</CommandEmpty>

            {[...grouped.entries()].map(([group, items]) => (
              <CommandGroup key={group ?? "__ungrouped__"} heading={group}>
                {items.map((opt) => {
                  const selected = isSelected(opt.value)
                  const itemId = getOptionId(opt)
                  return (
                    <CommandItem
                      key={opt.value}
                      id={itemId}
                      value={opt.label}
                      role="option"
                      aria-selected={selected}
                      disabled={opt.disabled}
                      onSelect={() => {
                        debug('item onSelect', { value: opt.value, label: opt.label })
                        handleSelect(opt.value)
                      }}
                    >
                      <CheckIcon
                        className={cn(
                          "mr-2 size-4",
                          selected ? "opacity-100" : "opacity-0"
                        )}
                      />
                      {renderOption
                        ? renderOption(opt, { active: activeId === itemId, selected })
                        : highlight(opt.label, query)}
                    </CommandItem>
                  )
                })}
              </CommandGroup>
            ))}
          </CommandList>

          {multiple && selectedValues.length > 0 && (
            <div className="flex items-center justify-end gap-2 border-t px-2 py-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => {
                  clearAll()
                }}
              >
                {clearAllLabel}
              </Button>
            </div>
          )}
        </Command>
      </PopoverContent>
    </Popover>
  )
}
