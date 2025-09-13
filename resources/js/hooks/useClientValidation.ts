/**
 * Client-side validation hook using Zod schemas
 * Provides debounced field validation and immediate submit validation
 */

import { VALIDATION_DEBOUNCE_MS } from '@/lib/validation/validation.config';
import { useCallback, useRef, useState } from 'react';
import type { ZodError, ZodSchema } from 'zod';

export interface UseClientValidationOptions {
    debounceMs?: number;
}

export function useClientValidation<T>(schema: ZodSchema<T>, getData: () => T, options?: UseClientValidationOptions) {
    const [errorsClient, setErrorsClient] = useState<Record<string, string | undefined>>({});
    const debounceTimers = useRef<Record<string, NodeJS.Timeout>>({});
    const debounceMs = options?.debounceMs ?? VALIDATION_DEBOUNCE_MS;

    /**
     * Parse Zod errors into a flat object
     */
    const parseZodErrors = (error: ZodError): Record<string, string> => {
        const errors: Record<string, string> = {};
        error.issues.forEach((issue) => {
            const path = issue.path.join('.');
            if (path && !errors[path]) {
                errors[path] = issue.message;
            }
        });
        return errors;
    };

    /**
     * Validate a single field with debounce
     */
    const validateOnBlur = useCallback(
        (field: string) => {
            // Clear existing timer for this field
            if (debounceTimers.current[field]) {
                clearTimeout(debounceTimers.current[field]);
            }

            // Set new debounce timer
            debounceTimers.current[field] = setTimeout(() => {
                const data = getData();
                const result = schema.safeParse(data);

                if (!result.success) {
                    const errors = parseZodErrors(result.error);
                    setErrorsClient((prev) => ({
                        ...prev,
                        [field]: errors[field],
                    }));
                } else {
                    // Clear error for this field if valid
                    setErrorsClient((prev) => {
                        const next = { ...prev };
                        delete next[field];
                        return next;
                    });
                }

                // Clean up timer reference
                delete debounceTimers.current[field];
            }, debounceMs);
        },
        [schema, getData, debounceMs],
    );

    /**
     * Validate all fields immediately (for submit)
     */
    const validateOnSubmit = useCallback((): boolean => {
        // Clear all pending debounce timers
        Object.values(debounceTimers.current).forEach(clearTimeout);
        debounceTimers.current = {};

        const data = getData();
        const result = schema.safeParse(data);

        if (!result.success) {
            const errors = parseZodErrors(result.error);
            setErrorsClient(errors);
            return false;
        }

        setErrorsClient({});
        return true;
    }, [schema, getData]);

    /**
     * Clear all client errors
     */
    const clearErrors = useCallback(() => {
        setErrorsClient({});
        Object.values(debounceTimers.current).forEach(clearTimeout);
        debounceTimers.current = {};
    }, []);

    /**
     * Merge server and client errors, preferring server errors
     */
    const mergeErrors = useCallback(
        (
            serverErrors: Record<string, string | string[] | undefined>,
            clientErrors: Record<string, string | undefined>,
        ): Record<string, string | undefined> => {
            const merged: Record<string, string | undefined> = {};

            // Add all client errors
            Object.keys(clientErrors).forEach((key) => {
                if (clientErrors[key]) {
                    merged[key] = clientErrors[key];
                }
            });

            // Override with server errors (they take precedence)
            Object.keys(serverErrors).forEach((key) => {
                const error = serverErrors[key];
                if (error) {
                    merged[key] = Array.isArray(error) ? error[0] : error;
                }
            });

            return merged;
        },
        [],
    );

    return {
        validateOnBlur,
        validateOnSubmit,
        errorsClient,
        clearErrors,
        mergeErrors,
    };
}
