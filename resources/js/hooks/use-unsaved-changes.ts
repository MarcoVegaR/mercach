import type { GlobalEvent, PendingVisit, VisitOptions } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

/**
 * Hook to detect and warn about unsaved changes in forms
 */
export function useUnsavedChanges<T extends Record<string, unknown>>(
    currentData: T,
    initialData: T,
    enabled = true,
    options?: {
        excludeKeys?: Array<keyof T> | string[];
        ignoreUnderscored?: boolean; // ignore keys starting with '_'
        confirmMessage?: string;
        // If provided, this will be used instead of window.confirm for Inertia navigations.
        // The hook will call event.preventDefault() and pass a resume() function to continue.
        onConfirm?: (resume: () => void, cancel: () => void, event: GlobalEvent<'before'>) => void;
    },
) {
    const hasChangesRef = useRef(false);

    const { excludeKeys = [], ignoreUnderscored = true } = options ?? {};
    const confirmMessage = options?.confirmMessage ?? 'Tienes cambios sin guardar. ¿Deseas salir de todas formas?';

    // Keep the latest onConfirm handler without re-subscribing listeners on every render
    const onConfirmRef = useRef(options?.onConfirm);
    useEffect(() => {
        onConfirmRef.current = options?.onConfirm;
    }, [options?.onConfirm]);

    // Sanitize and stabilize objects for comparison
    const sanitize = useCallback(
        (value: unknown): unknown => {
            if (Array.isArray(value)) {
                return value.map((v) => sanitize(v));
            }
            if (value && typeof value === 'object') {
                const result: Record<string, unknown> = {};
                Object.keys(value as Record<string, unknown>)
                    .filter((k) => !excludeKeys.includes(k))
                    .filter((k) => (ignoreUnderscored ? !k.startsWith('_') : true))
                    .sort()
                    .forEach((k) => {
                        result[k] = sanitize((value as Record<string, unknown>)[k]);
                    });
                return result;
            }
            return value;
        },
        [excludeKeys, ignoreUnderscored],
    );

    // Check if data has changed
    const isDirty = useCallback(() => {
        try {
            return JSON.stringify(sanitize(currentData)) !== JSON.stringify(sanitize(initialData));
        } catch {
            // Fallback to previous behavior if something unexpected happens
            return JSON.stringify(currentData) !== JSON.stringify(initialData);
        }
    }, [currentData, initialData, sanitize]);

    useEffect(() => {
        if (!enabled) return;

        hasChangesRef.current = isDirty();

        // Helper: normalize and compare URLs (ignore hash)
        const isSameUrl = (target: string | URL) => {
            try {
                const current = new URL(window.location.href);
                const next = new URL(target instanceof URL ? target.toString() : target, window.location.origin);
                return current.pathname === next.pathname && current.search === next.search;
            } catch {
                return false;
            }
        };

        // Handle browser navigation
        const handleBeforeUnload = (e: BeforeUnloadEvent) => {
            if (hasChangesRef.current) {
                e.preventDefault();
                e.returnValue = '';
            }
        };

        // Handle Inertia navigation
        const removeInertiaListener = router.on('before', (event: GlobalEvent<'before'>) => {
            if (!hasChangesRef.current) return;

            const visit = event.detail?.visit as PendingVisit | undefined;
            if (!visit) return;

            // Best practices:
            // - Do NOT warn on non-GET methods (POST/PUT/PATCH/DELETE are form submits)
            // - Do NOT warn on same-URL GET reloads (e.g., router.reload / partial reloads)
            // - Do NOT warn on prefetch/async intent (hover prefetch should not block)
            const isGet = (visit.method ?? 'get').toLowerCase() === 'get';
            const sameUrl = isSameUrl(visit.url);
            const visitMeta = visit as unknown as Record<string, unknown>;
            const isPrefetch = Boolean(visitMeta['prefetch']);
            const isAsync = Boolean(visitMeta['async']);
            if (!isGet || sameUrl || isPrefetch || isAsync) {
                return; // allow
            }

            // If a custom confirm handler is provided, delegate to it
            const handler = onConfirmRef.current;
            event.preventDefault();
            if (handler) {
                const resume = () => {
                    try {
                        if (visit) {
                            // Mark as clean to avoid re-blocking on resumed navigation
                            hasChangesRef.current = false;
                            const opts: VisitOptions = {
                                method: visit.method,
                                data: visit.data,
                                replace: visit.replace,
                                preserveScroll: visit.preserveScroll,
                                preserveState: visit.preserveState,
                                only: visit.only,
                                except: visit.except,
                                headers: visit.headers,
                                errorBag: visit.errorBag,
                                forceFormData: visit.forceFormData,
                                queryStringArrayFormat: visit.queryStringArrayFormat,
                                async: visit.async,
                                showProgress: visit.showProgress,
                                prefetch: visit.prefetch,
                                fresh: visit.fresh,
                                reset: visit.reset,
                                preserveUrl: visit.preserveUrl,
                            };
                            router.visit(visit.url, opts);
                        }
                    } catch {
                        // no-op
                    }
                };
                const cancel = () => {
                    /* no-op */
                };
                handler(resume, cancel, event);
                return;
            }

            // Fallback to native confirm
            const confirmed = window.confirm(confirmMessage);
            if (!confirmed) {
                event.preventDefault();
                return;
            }
            // If confirmed, mark as clean and allow the navigation
            hasChangesRef.current = false;
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            removeInertiaListener();
        };
    }, [enabled, confirmMessage, isDirty]);

    return {
        hasUnsavedChanges: isDirty(),
        clearUnsavedChanges: () => {
            hasChangesRef.current = false;
        },
    };
}
