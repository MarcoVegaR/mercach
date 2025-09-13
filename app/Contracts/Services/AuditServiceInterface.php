<?php

declare(strict_types=1);

namespace App\Contracts\Services;

/**
 * Contrato para el servicio de auditoría.
 *
 * Extiende el servicio base con funcionalidades específicas
 * para operaciones de auditoría.
 */
interface AuditServiceInterface extends ServiceInterface
{
    // Hereda todas las operaciones del servicio base
    // Puede añadir métodos específicos de auditoría aquí si es necesario

    /**
     * Datos extra para el índice de auditoría (estadísticas, etc.)
     *
     * @return array{stats: array<string, int>}
     */
    public function getIndexExtras(): array;
}
