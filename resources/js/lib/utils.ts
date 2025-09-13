import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * Normalize an array of numbers/strings into a deduplicated number[]
 */
export function sanitizeIds(value: Array<number | string | null | undefined>): number[] {
    const out = new Set<number>();
    for (const v of value ?? []) {
        const n = typeof v === 'string' ? parseInt(v, 10) : v;
        if (Number.isFinite(n as number)) out.add(n as number);
    }
    return [...out];
}
