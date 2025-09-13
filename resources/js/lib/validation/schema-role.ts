/**
 * Zod validation schema for Role forms
 */

import { z } from 'zod';
import { MAX_NAME } from './validation.config';
import { enumValue, numericIdArray, requiredBoolean, stringRequired } from './zod-kit';

/**
 * Base role validation schema
 */
export const roleSchema = z.object({
    name: stringRequired('Nombre', MAX_NAME),
    guard_name: enumValue(['web'] as const, 'Guard'),
    is_active: requiredBoolean('Activo'),
    permissions_ids: numericIdArray('Permisos'),
    _version: z.string().nullable().optional(),
});

export type RoleFormData = z.infer<typeof roleSchema>;

/**
 * Creates a role schema with dynamic guards
 * @param guards - Array of available guard names
 */
export function makeRoleSchema(guards: readonly string[]) {
    if (!guards || guards.length === 0) {
        return roleSchema; // Fallback to default
    }

    return roleSchema.extend({
        guard_name: enumValue(guards, 'Guard'),
    });
}

/**
 * Validates a single field from the role form
 */
export function validateRoleField(field: keyof RoleFormData, value: unknown, guards?: readonly string[]) {
    const schema = guards ? makeRoleSchema(guards) : roleSchema;
    const fieldSchema = schema.shape[field as keyof typeof schema.shape];

    if (!fieldSchema) {
        return { success: true };
    }

    return fieldSchema.safeParse(value);
}
