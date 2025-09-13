<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

/**
 * Contrato para el repositorio de auditoría.
 *
 * Extiende el repositorio base con funcionalidades específicas
 * para consulta de registros de auditoría.
 */
interface AuditRepositoryInterface extends RepositoryInterface
{
    // Hereda todas las operaciones del repositorio base
    // Puede añadir métodos específicos de auditoría aquí si es necesario
}
