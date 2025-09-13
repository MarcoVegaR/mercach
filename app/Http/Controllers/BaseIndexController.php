<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ServiceInterface;
use App\Http\Controllers\Concerns\HandlesIndexAndExport;

/**
 * Clase abstracta opcional para controladores que manejan Index/Export.
 *
 * Proporciona una alternativa basada en herencia al trait HandlesIndexAndExport.
 * Útil cuando se prefiere herencia sobre composición o cuando se necesita
 * una base común con funcionalidad adicional.
 *
 * Los controladores concretos solo necesitan implementar los hooks abstractos:
 * - policyModel(): string
 * - view(): string
 * Y opcionalmente sobrescribir with(), withCount(), allowedExportFormats()
 *
 * @author Laravel Boilerplate
 */
abstract class BaseIndexController extends Controller
{
    use HandlesIndexAndExport;

    /**
     * Constructor que inyecta el servicio requerido por el trait.
     *
     * @param  ServiceInterface  $service  Servicio que maneja la lógica de negocio
     */
    public function __construct(protected ServiceInterface $service) {}

    /**
     * Get the request class for index operations.
     */
    abstract protected function indexRequestClass(): string;
}
