<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\AuditServiceInterface;
use App\Http\Requests\AuditoriaIndexRequest;
use App\Models\Audit;
use Illuminate\Http\Request;

/**
 * Controlador para gestión de auditoría.
 *
 * Extiende BaseIndexController para reutilizar funcionalidad estándar
 * de listado, filtros, búsqueda, paginación y exportación.
 *
 * Maneja el listado y exportación de registros de auditoría
 * con soporte para búsqueda, filtros y paginación.
 */
class AuditoriaController extends BaseIndexController
{
    /**
     * Service typed as AuditServiceInterface to access module-specific extras.
     */
    private AuditServiceInterface $auditService;

    /**
     * Constructor que inyecta el servicio específico de auditoría.
     */
    public function __construct(AuditServiceInterface $service)
    {
        parent::__construct($service);
        $this->auditService = $service;
    }

    /**
     * Modelo para autorización con Policies.
     */
    protected function policyModel(): string
    {
        return Audit::class;
    }

    /**
     * Vista Inertia para renderizar el Index.
     */
    protected function view(): string
    {
        return 'auditoria/index';
    }

    /**
     * Display a listing of the resource including stats extras.
     */
    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);

        $extras = $this->auditService->getIndexExtras();
        $response->with('stats', $extras['stats']);

        return $response;
    }

    /**
     * Clase del FormRequest para operaciones de índice.
     */
    protected function indexRequestClass(): string
    {
        return AuditoriaIndexRequest::class;
    }

    /**
     * Relaciones a cargar eager.
     *
     * @return array<string>
     */
    protected function with(): array
    {
        return ['user'];
    }

    /**
     * Formatos de export permitidos.
     *
     * @return array<string>
     */
    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'pdf', 'json'];
    }

    protected function indexRouteName(): string
    {
        return 'auditoria.index';
    }
}
