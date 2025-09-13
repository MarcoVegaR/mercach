export function FormVersion({ updatedAt, version }: { updatedAt?: string | null; version?: string | number | null }) {
    if (version == null) return null;
    return (
        <>
            <input type="hidden" name="_version" value={String(version)} />
            <p className="text-muted-foreground mt-2 text-xs">
                Última actualización: {updatedAt ? new Date(updatedAt).toLocaleString('es-ES') : '—'}
            </p>
        </>
    );
}
