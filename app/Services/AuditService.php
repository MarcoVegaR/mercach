<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\AuditServiceInterface;
use App\Models\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Servicio para operaciones de auditoría.
 *
 * Implementa la lógica de negocio para consulta de registros
 * de auditoría, transformación de datos y exportación.
 */
class AuditService extends BaseService implements AuditServiceInterface
{
    /**
     * Transforma un modelo de auditoría en formato de fila para UI/Export.
     *
     * @return array<string, mixed>
     */
    public function toRow(Model $model): array
    {
        /** @var Audit $audit */
        $audit = $model;

        return [
            'id' => $audit->id,
            'created_at' => $audit->created_at?->toISOString(),
            'user_id' => $audit->user_id,
            // Do not trigger lazy loading; only include if relation is loaded
            'user_name' => $audit->relationLoaded('user') ? ($audit->user?->name) : null,
            'event' => $audit->event,
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'ip_address' => $audit->ip_address,
            'url' => $audit->url,
            'tags' => $audit->tags,
            'old_values' => $audit->old_values,
            'new_values' => $audit->new_values,
            'user_agent' => $audit->user_agent,
        ];
    }

    /**
     * Transforma un modelo en formato de item para Show.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        return $this->toRow($model);
    }

    /**
     * Columnas por defecto para exportación.
     * Devuelve una lista de keys visibles en el export, el Exporter mapeará por posición.
     *
     * @return array<string>
     */
    protected function defaultExportColumns(): array
    {
        // Return list of keys; exporters and BaseService will handle list appropriately
        return [
            'id',
            'created_at',
            'user_name',
            'event',
            'auditable_type',
            'auditable_id',
            'ip_address',
            'url',
        ];
    }

    /**
     * Nombre de archivo por defecto para exportación.
     */
    protected function defaultExportFilename(string $format, mixed $query = null): string
    {
        $timestamp = now()->format('Ymd_Hi');
        $extension = match ($format) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'pdf' => 'pdf',
            'json' => 'json',
            default => 'csv',
        };

        return "auditoria_export_{$timestamp}.{$extension}";
    }

    /**
     * Extras para la página de índice (stats dinámicos desde BD).
     *
     * @return array{stats: array<string, int>}
     */
    public function getIndexExtras(): array
    {
        $stats = [
            'total' => Audit::query()->count(),
            'last24h' => Audit::query()->where('created_at', '>=', now()->subDay())->count(),
        ];

        return [
            'stats' => $stats,
        ];
    }
}
