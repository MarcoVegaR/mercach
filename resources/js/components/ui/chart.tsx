import * as React from 'react';
import { cn } from '@/lib/utils';
import { Tooltip as RechartsTooltip } from 'recharts';

export type ChartConfig = Record<string, { label?: string; color?: string } & Record<string, unknown>>;

type ChartContainerProps = React.HTMLAttributes<HTMLDivElement> & {
  config?: ChartConfig;
};

export function ChartContainer({ config, className, style, ...props }: ChartContainerProps) {
  const cssVars: Record<string, string> = {};
  if (config) {
    for (const [key, value] of Object.entries(config)) {
      if (value?.color) {
        // Expose CSS vars like --color-<key> for consumers
        cssVars[`--color-${key}`] = value.color;
      }
    }
  }
  return <div className={cn('w-full', className)} style={{ ...cssVars, ...style }} {...props} />;
}

// Tooltip wrapper to align with shadcn/ui charts examples
export function ChartTooltip(props: React.ComponentProps<typeof RechartsTooltip>) {
  return <RechartsTooltip {...props} />;
}

export function ChartTooltipContent({ active, payload, hideLabel, suffix, locale = 'es-VE' }: { active?: boolean; payload?: Array<{ name?: string; value?: number | string; payload?: { label?: string; name?: string; fill?: string }; color?: string }>; hideLabel?: boolean; suffix?: string; locale?: string }) {
  if (!active || !payload || payload.length === 0) return null;
  const p = payload[0];
  const name = (p?.name as string | undefined) ?? (p?.payload?.label as string | undefined) ?? (p?.payload?.name as string | undefined) ?? '';
  const raw = p?.value ?? 0;
  const value = typeof raw === 'number' ? raw.toLocaleString(locale) : String(raw);
  // Try to infer color from payload (recharts passes .payload.fill or .color)
  const color: string | undefined = (p?.payload?.fill as string | undefined) ?? (p?.color as string | undefined);
  return (
    <div className="rounded-md border bg-popover px-3 py-2 text-popover-foreground shadow-sm">
      {!hideLabel && (
        <div className="flex items-center gap-2 text-xs text-muted-foreground">
          {color ? <span className="inline-block size-2.5 rounded-sm" style={{ backgroundColor: color }} /> : null}
          <span>{name}</span>
        </div>
      )}
      <div className="text-sm font-medium">{value}{suffix ? ` ${suffix}` : ''}</div>
    </div>
  );
}

// Legend wrapper (optional usage)
export function ChartLegend({ items }: { items: { label: string; color: string }[] }) {
  return (
    <div className="flex flex-wrap gap-3 text-xs text-muted-foreground">
      {items.map((it, idx) => (
        <div className="flex items-center gap-2" key={idx}>
          <span className="inline-block size-3 rounded-sm" style={{ backgroundColor: it.color }} />
          <span>{it.label}</span>
        </div>
      ))}
    </div>
  );
}
