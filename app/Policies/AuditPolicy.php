<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Policy para autorización de auditoría.
 *
 * Define las reglas de autorización para el módulo de auditoría
 * usando el sistema de permisos de Spatie.
 */
class AuditPolicy extends BaseResourcePolicy
{
    /**
     * Prefijo de habilidades para auditoría.
     */
    protected function abilityPrefix(): string
    {
        return 'auditoria';
    }
}
