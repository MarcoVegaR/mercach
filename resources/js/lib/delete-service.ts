import { toast } from '@/lib/toast';
import { router } from '@inertiajs/react';

export interface DeleteOptions<T = unknown> {
    endpoint: string;
    method?: 'delete' | 'post';
    headers?: Record<string, string>;
    onSuccess?: (data?: T) => void;
    onError?: (error: Error) => void;
    preserveState?: boolean;
    preserveScroll?: boolean;
}

export interface BulkDeleteOptions<T = unknown> extends DeleteOptions<T> {
    selectedIds: string[];
    reason?: string;
}

/**
 * Delete a single resource with toast feedback
 */
export async function deleteSingle<T = unknown>(id: string | number, options: DeleteOptions<T>): Promise<void> {
    const { endpoint, method = 'delete', headers = {}, onSuccess, onError, preserveState = false, preserveScroll = true } = options;

    void toast.promise(
        new Promise<void>((resolve, reject) => {
            const url = endpoint.replace(':id', String(id));

            if (method.toLowerCase() === 'delete') {
                router.delete(url, {
                    preserveState,
                    preserveScroll,
                    onSuccess: (data) => {
                        resolve();
                        onSuccess?.(data as T);
                    },
                    onError: (errors) => {
                        const error = new Error(
                            typeof errors === 'object' && errors !== null ? (Object.values(errors)[0] as string) : 'Error al eliminar',
                        );
                        reject(error);
                        onError?.(error);
                    },
                });
            } else {
                router.visit(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        ...headers,
                    },
                    preserveState,
                    preserveScroll,
                    onSuccess: (data) => {
                        resolve();
                        onSuccess?.(data as T);
                    },
                    onError: (errors) => {
                        const error = new Error(
                            typeof errors === 'object' && errors !== null ? (Object.values(errors)[0] as string) : 'Error al eliminar',
                        );
                        reject(error);
                        onError?.(error);
                    },
                });
            }
        }),
        {
            loading: 'Eliminando...',
            success: 'Elemento eliminado exitosamente',
            error: 'Error al eliminar elemento',
        },
    );
}

/**
 * Delete multiple resources with toast feedback
 */
export async function deleteBulk<T = unknown>(options: BulkDeleteOptions<T>): Promise<void> {
    const {
        endpoint,
        selectedIds,
        reason,
        method = 'delete',
        headers = {},
        onSuccess,
        onError,
        preserveState = false,
        preserveScroll = true,
    } = options;

    if (selectedIds.length === 0) {
        throw new Error('No items selected for deletion');
    }

    void toast.promise(
        new Promise<void>((resolve, reject) => {
            const requestData = {
                ids: selectedIds,
                ...(reason && { reason }),
            };

            // Use FormData to satisfy Inertia's RequestPayload typing
            const formData = new FormData();
            requestData.ids.forEach((id) => formData.append('ids[]', id));
            if (requestData.reason) {
                formData.append('reason', requestData.reason);
            }

            router.visit(endpoint, {
                method,
                data: formData,
                headers,
                preserveState,
                preserveScroll,
                onSuccess: (responseData) => {
                    resolve();
                    onSuccess?.(responseData as unknown as T);
                },
                onError: (errors) => {
                    const error = new Error(
                        typeof errors === 'object' && errors !== null ? (Object.values(errors)[0] as string) : 'Error al eliminar elementos',
                    );
                    reject(error);
                    onError?.(error);
                },
            });
        }),
        {
            loading: `Eliminando ${selectedIds.length} elemento${selectedIds.length !== 1 ? 's' : ''}...`,
            success: `${selectedIds.length} elemento${selectedIds.length !== 1 ? 's' : ''} eliminado${selectedIds.length !== 1 ? 's' : ''} exitosamente`,
            error: 'Error al eliminar elementos',
        },
    );
}

/**
 * Delete with confirmation dialog integration
 */
export async function deleteWithConfirmation<T = unknown>(
    options: {
        type: 'single' | 'bulk';
        id?: string | number;
        selectedIds?: string[];
        title?: string;
        description?: string;
        requireReason?: boolean;
        destructiveLabel?: string;
    } & DeleteOptions<T>,
): Promise<void> {
    const { type, id, selectedIds = [], title, description, requireReason = false, destructiveLabel: _destructiveLabel, ...deleteOptions } = options;

    // This would integrate with your ConfirmAlert/ConfirmWithReasonDialog
    // For now, we'll simulate the confirmation with a simple confirm
    const confirmed = window.confirm(
        title ||
            (type === 'single'
                ? '¿Estás seguro de que deseas eliminar este elemento?'
                : `¿Estás seguro de que deseas eliminar ${selectedIds.length} elementos?`),
    );

    if (!confirmed) {
        return;
    }

    let reason: string | undefined;
    if (requireReason) {
        reason = window.prompt('Por favor, proporciona una razón para la eliminación:') || undefined;
        if (!reason) {
            throw new Error('Razón requerida para la eliminación');
        }
    }

    if (type === 'single' && id !== undefined) {
        return deleteSingle(id, deleteOptions);
    } else if (type === 'bulk' && selectedIds.length > 0) {
        return deleteBulk({ ...deleteOptions, selectedIds, reason });
    } else {
        throw new Error('Invalid delete configuration');
    }
}

// Convenience functions for common patterns

/**
 * Quick single delete for DataTable row actions
 */
export const deleteRow = (id: string | number, endpoint: string, onSuccess?: () => void) =>
    deleteWithConfirmation({
        type: 'single',
        id,
        endpoint: endpoint.replace(':id', String(id)),
        onSuccess,
    });

/**
 * Quick bulk delete for DataTable bulk actions
 */
export const deleteSelected = (selectedIds: string[], endpoint: string, options: Partial<BulkDeleteOptions<unknown>> = {}) =>
    deleteWithConfirmation({
        type: 'bulk',
        selectedIds,
        endpoint,
        ...options,
    });

/**
 * Delete with soft delete restoration option
 */
export async function softDelete(id: string | number, options: DeleteOptions & { restoreEndpoint?: string }): Promise<void> {
    const { restoreEndpoint, ...deleteOptions } = options;

    await deleteSingle(id, {
        ...deleteOptions,
        onSuccess: (data) => {
            // Show additional toast with restore option if available
            if (restoreEndpoint) {
                toast.success('Elemento eliminado', {
                    action: {
                        label: 'Deshacer',
                        onClick: () => {
                            router.post(restoreEndpoint.replace(':id', String(id)));
                        },
                    },
                });
            }
            options.onSuccess?.(data);
        },
    });
}
