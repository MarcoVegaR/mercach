export function resolveStoredFileUrl(remoteUrl?: string | null, path?: string | null): string | undefined {
    return remoteUrl || (path ? `/storage/${path}` : undefined);
}
