/**
 * Hook to focus the first field with an error after validation failure
 */

import { useCallback, useRef } from 'react';

export function useFirstErrorFocus() {
    const lastFocusedField = useRef<string | null>(null);

    /**
     * Focus the first field with an error
     */
    const focusFirstError = useCallback((errors: Record<string, string | string[] | undefined>) => {
        // Get sorted list of fields with errors
        const errorFields = Object.keys(errors).filter((key) => errors[key]);

        if (errorFields.length === 0) {
            lastFocusedField.current = null;
            return;
        }

        // Try to find the field element and focus it
        const firstErrorField = errorFields[0];

        // Don't re-focus the same field multiple times
        if (lastFocusedField.current === firstErrorField) {
            return;
        }

        setTimeout(() => {
            // Try multiple selector strategies
            const selectors = [
                `[name="${firstErrorField}"]`,
                `#${firstErrorField}`,
                `[data-field="${firstErrorField}"]`,
                `[aria-label*="${firstErrorField}"]`,
            ];

            for (const selector of selectors) {
                try {
                    const element = document.querySelector(selector);
                    if (element instanceof HTMLElement) {
                        element.focus();
                        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        lastFocusedField.current = firstErrorField;

                        // If it's an input, select the text for easy correction
                        if (element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement) {
                            element.select();
                        }

                        break;
                    }
                } catch {
                    // noop: ignore selector errors
                }
            }
        }, 100); // Small delay to ensure DOM is updated
    }, []);

    /**
     * Reset the last focused field tracking
     */
    const resetFocus = useCallback(() => {
        lastFocusedField.current = null;
    }, []);

    return { focusFirstError, resetFocus };
}
