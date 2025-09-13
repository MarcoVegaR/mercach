import type { ReactNode } from 'react';
import { toast as baseToast } from 'sonner';

export const toast = baseToast;

export function success(message: ReactNode, opts?: Parameters<typeof baseToast.success>[1]) {
    return baseToast.success(message, opts);
}
export function info(message: ReactNode, opts?: Parameters<typeof baseToast.info>[1]) {
    return baseToast.info(message, opts);
}
export function warning(message: ReactNode, opts?: Parameters<typeof baseToast.warning>[1]) {
    return baseToast.warning(message, opts);
}
export function error(message: ReactNode, opts?: Parameters<typeof baseToast.error>[1]) {
    return baseToast.error(message, opts);
}

export function promise<T>(
    promise: Promise<T>,
    options: {
        loading: ReactNode;
        success: ReactNode | ((data: T) => ReactNode);
        error: ReactNode | ((err: unknown) => ReactNode);
    },
): unknown {
    // Delegate to sonner; relax typings to avoid inference issues between versions
    return (baseToast as unknown as { promise: (p: Promise<T>, opts: unknown) => unknown }).promise(promise, options as unknown);
}
