<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Models\Audit as BaseAudit;

/**
 * Modelo para auditoría del sistema.
 *
 * Extiende el modelo base de owen-it/laravel-auditing para
 * proporcionar funcionalidades adicionales y relaciones.
 *
 * @property int $id
 * @property string|null $user_type
 * @property int|null $user_id
 * @property string $event
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $url
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $tags
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $user
 */
class Audit extends BaseAudit
{
    /**
     * Relación con el modelo User.
     *
     * Proporciona acceso directo al usuario que realizó la acción.
     */
    /**
     * @return BelongsTo<\App\Models\User, static>
     */
    public function user(): BelongsTo
    {
        /** @var BelongsTo<\App\Models\User, static> $relation */
        $relation = $this->belongsTo(User::class, 'user_id');

        return $relation;
    }

    /**
     * Scope para filtrar por evento específico.
     *
     * @param  Builder<\App\Models\Audit>  $query
     * @return Builder<\App\Models\Audit>
     */
    public function scopeByEvent(Builder $query, string $event): Builder
    {
        return $query->where('event', $event);
    }

    /**
     * Scope para filtrar por tipo de modelo auditado.
     *
     * @param  Builder<\App\Models\Audit>  $query
     * @return Builder<\App\Models\Audit>
     */
    public function scopeByAuditableType(Builder $query, string $type): Builder
    {
        return $query->where('auditable_type', $type);
    }

    /**
     * Scope para filtrar por rango de fechas.
     *
     * @param  Builder<\App\Models\Audit>  $query
     * @return Builder<\App\Models\Audit>
     */
    public function scopeCreatedBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }

    /**
     * Accessor para obtener el nombre del usuario de forma segura.
     */
    public function getUserNameAttribute(): ?string
    {
        return $this->user?->name;
    }
}
