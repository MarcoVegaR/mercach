/**
 * Validation configuration constants
 */

// Field length limits
export const MAX_NAME = 100;
export const MAX_EMAIL = 255;
export const MAX_DESCRIPTION = 500;
export const MAX_URL = 2048;
export const MIN_PASSWORD = 8;

// Common validation messages (Spanish by default)
export const MESSAGES = {
    required: (field: string) => `${field} es obligatorio`,
    max: (field: string, max: number) => `${field} no debe exceder ${max} caracteres`,
    min: (field: string, min: number) => `${field} debe tener al menos ${min} caracteres`,
    email: 'Debe ser un email válido',
    url: 'Debe ser una URL válida',
    integer: 'Debe ser un número entero',
    positive: 'Debe ser un número positivo',
    array: 'Debe ser una lista',
    date: 'Debe ser una fecha válida',
    enum: (field: string, values: string[]) => `${field} debe ser uno de: ${values.join(', ')}`,
    unique: (field: string) => `${field} ya está en uso`,
    confirmed: (field: string) => `La confirmación de ${field} no coincide`,
};

// Debounce delay for field validation
export const VALIDATION_DEBOUNCE_MS = 300;
