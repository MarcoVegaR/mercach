/**
 * Zod validation helpers with pre-configured Spanish messages
 */

import { z } from 'zod';
import { MESSAGES } from './validation.config';

/**
 * Creates a required string field with trim and length validations
 */
export function stringRequired(label: string, max?: number) {
    const schema = z.string().trim().min(1, MESSAGES.required(label));

    if (max) {
        return schema.max(max, MESSAGES.max(label, max));
    }
    return schema;
}

/**
 * Creates an optional string field with trim and length validations
 */
export function stringOptional(label: string, max?: number) {
    let schema = z.string().trim().optional();

    if (max) {
        schema = z.string().trim().max(max, MESSAGES.max(label, max)).optional();
    }

    return schema;
}

/**
 * Creates a required boolean field
 */
export const requiredBoolean = (label = 'campo') => {
    return z.boolean().refine((val) => typeof val === 'boolean', { message: MESSAGES.required(label) });
};

/**
 * Creates an optional boolean field
 */
export function booleanOptional() {
    return z.boolean().optional();
}

/**
 * Creates an array of numeric IDs (for relationships)
 */
export function numericIdArray(_label: string) {
    return z.array(z.number().int(MESSAGES.integer).positive(MESSAGES.positive)).default([]);
}

/**
 * Creates an optional ISO date string field
 */
export const isoDate = () => {
    return z.string().datetime({ message: MESSAGES.date }).nullable().optional();
};

/**
 * Creates an enum field from string options
 */
export const enumValue = <T extends readonly string[]>(options: T, _label = 'opción') => {
    if (options.length === 0) {
        throw new Error('Enum options cannot be empty');
    }
    return z
        .enum([options[0], ...options.slice(1)] as [T[number], ...T[number][]])
        .refine((val) => options.includes(val), { message: 'Opción no válida' });
};

/**
 * Creates a required string field with trimming
 */
export const requiredString = (label = 'campo') => {
    return z
        .string()
        .min(1, MESSAGES.required(label))
        .transform((val) => val.trim());
};

/**
 * Creates a required email field
 */
export const email = (label = 'email') => {
    return z
        .string()
        .min(1, MESSAGES.required(label))
        .email(MESSAGES.email)
        .max(255, MESSAGES.max('email', 255))
        .transform((val) => val.toLowerCase().trim());
};

/**
 * Creates an optional email field
 */
export function emailOptional() {
    return z.string().trim().email(MESSAGES.email).optional().or(z.literal(''));
}

/**
 * Creates a required URL field
 */
export const url = (label = 'URL') => {
    return z.string().min(1, MESSAGES.required(label)).url(MESSAGES.url).max(2048, MESSAGES.max('URL', 2048));
};

/**
 * Creates an optional URL field
 */
export function urlOptional() {
    return z.string().trim().url(MESSAGES.url).optional().or(z.literal(''));
}
