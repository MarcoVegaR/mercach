/**
 * Stable row ID helpers for DataTable selection persistence
 */

export function getRowIdByKey<TData extends Record<string, unknown>>(idField: keyof TData = 'id') {
    return (row: TData, index: number): string => {
        const id = row[idField];
        return id !== undefined ? String(id) : `row-${index}`;
    };
}

export function createCompositeRowIdGetter<TData extends Record<string, unknown>>(fields: Array<keyof TData>) {
    return (row: TData, index: number): string => {
        const parts = fields.map((field) => String(row[field] ?? ''));
        const composite = parts.filter(Boolean).join('-');
        return composite || `row-${index}`;
    };
}

export const defaultRowIdGetter = <TData extends Record<string, unknown>>(row: TData, index: number): string => {
    if ('id' in row && row.id != null) return String(row.id);
    if ('uuid' in row && row.uuid != null) return String(row.uuid);
    if ('key' in row && row.key != null) return String(row.key);
    return `row-${index}`;
};
