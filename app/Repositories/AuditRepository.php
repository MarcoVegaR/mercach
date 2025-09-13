<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\AuditRepositoryInterface;
use App\Models\Audit;
use Illuminate\Database\Eloquent\Builder;

/**
 * Repositorio para operaciones de auditoría.
 *
 * Implementa funcionalidades de consulta, búsqueda y filtrado
 * específicas para los registros de auditoría del sistema.
 */
class AuditRepository extends BaseRepository implements AuditRepositoryInterface
{
    /**
     * Clase del modelo de auditoría.
     */
    protected string $modelClass = Audit::class;

    /**
     * Deshabilitamos el mecanismo genérico de búsqueda; usaremos una
     * implementación específica que solo busca por usuario e IP.
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return [];
    }

    /**
     * Columnas permitidas para ordenamiento.
     *
     * @return array<string>
     */
    protected function allowedSorts(): array
    {
        return [
            'id',
            'created_at',
            'user_id',
            'event',
            'auditable_type',
            'auditable_id',
            'ip_address',
            'url',
        ];
    }

    /**
     * Ordenamiento por defecto.
     *
     * @return array{string, string}
     */
    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    /**
     * Aplica relaciones eagerly al query.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function withRelations(Builder $builder): Builder
    {
        return $builder->with(['user']);
    }

    /**
     * Búsqueda global limitada a nombre de usuario e IP (como texto).
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function applySearch(Builder $builder, string $searchTerm): Builder
    {
        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            return $builder;
        }

        $needle = strtolower($searchTerm);

        return $builder->where(function (Builder $q) use ($needle) {
            // Buscar por nombre de usuario relacionado
            $q->orWhereHas('user', function (Builder $uq) use ($needle) {
                $uq->whereRaw('LOWER(name) LIKE ?', ["%{$needle}%"]);
            });

            // Buscar por IP casteada a texto (evita error de LOWER() sobre inet)
            $q->orWhereRaw('LOWER(CAST(ip_address AS TEXT)) LIKE ?', ["%{$needle}%"]);
        });
    }

    /**
     * Mapeo de filtros personalizados.
     *
     * @return array<string, callable>
     */
    protected function filterMap(): array
    {
        return [
            'user_id' => function ($query, $value) {
                return $query->where('user_id', $value);
            },

            'event' => function ($query, $value) {
                return $query->where('event', 'like', "%{$value}%");
            },

            'auditable_type' => function ($query, $value) {
                return $query->where('auditable_type', $value);
            },

            'auditable_id' => function ($query, $value) {
                return $query->where('auditable_id', $value);
            },

            'ip_address' => function ($query, $value) {
                return $query->where('ip_address', 'like', "%{$value}%");
            },

            'url' => function ($query, $value) {
                return $query->where('url', 'like', "%{$value}%");
            },

            'tags' => function ($query, $value) {
                return $query->where('tags', 'like', "%{$value}%");
            },

            'created_between' => function ($query, $value) {
                $from = $value['from'] ?? null;
                $to = $value['to'] ?? null;

                return $query
                    ->when($from, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                    ->when($to, fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
            },
        ];
    }
}
