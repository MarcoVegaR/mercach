<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Contracts\Services\ServiceInterface;
use App\DTO\ListQuery;
use App\Exceptions\DomainActionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse as HttpStreamedResponse;

/**
 * Trait reusable para controladores con funcionalidad Index/Export usando Inertia.
 *
 * Proporciona métodos estándar para listado, export, operaciones masivas y
 * selecciones filtradas, integrando Policies, Services e Inertia con
 * optimización para partial reloads y TanStack Table v8 server-side.
 *
 * El controlador que use este trait debe:
 * - Inyectar ServiceInterface en la propiedad protected $service
 * - Implementar los hooks abstract/protected requeridos
 * - Registrar las rutas apropiadas (GET index, GET export, POST bulk, GET selected)
 *
 * @author Laravel Boilerplate
 *
 * @requires ServiceInterface $service Servicio inyectado en el constructor del controlador
 */
trait HandlesIndexAndExport
{
    /**
     * Devuelve el modelo para autorización con Policies.
     *
     * @return string Fully qualified class name del modelo (ej: \App\Models\User::class)
     */
    abstract protected function policyModel(): string;

    /**
     * Vista Inertia para renderizar el Index.
     *
     * @return string Nombre de la vista (ej: 'Roles/Index')
     */
    abstract protected function view(): string;

    /**
     * Relaciones a cargar eager para evitar N+1.
     *
     * @return array<string> Array de relaciones
     */
    protected function with(): array
    {
        return [];
    }

    /**
     * Conteos de relaciones a incluir para evitar N+1.
     *
     * @return array<string> Array de conteos de relaciones
     */
    protected function withCount(): array
    {
        return [];
    }

    /**
     * Formatos de export permitidos para este recurso.
     *
     * @return array<string> Formatos soportados
     */
    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'pdf'];
    }

    /**
     * GET /resource
     *
     * Lista recursos paginados con soporte completo de filtros, búsqueda y orden.
     * Compatible con partial reloads de Inertia.js y TanStack Table v8.
     *
     * @param  \Illuminate\Http\Request  $request  Request con parámetros de índice
     */
    public function index(\Illuminate\Http\Request $request): \Inertia\Response
    {
        $modelClass = $this->policyModel();

        // Ensure we have a valid class string
        if (! class_exists($modelClass)) {
            throw new \RuntimeException('policyModel() must return a valid class string, got: '.var_export($modelClass, true));
        }

        $this->authorize('viewAny', $modelClass);

        // Resolve and validate the concrete request class
        $requestClass = $this->indexRequestClass();
        $validatedRequest = $requestClass::createFrom($request);
        $validatedRequest->setContainer(app());
        $validatedRequest->setRedirector(app('redirect'));
        $validatedRequest->validateResolved();

        $dto = $validatedRequest->toListQuery();
        $result = $this->service->list($dto, $this->with(), $this->withCount());

        return Inertia::render($this->view(), [
            'rows' => $result['rows'],
            'meta' => $result['meta'],
        ]);
    }

    /**
     * GET /resource/export?format=csv|xlsx|pdf
     *
     * Exporta recursos con formato especificado usando ExporterInterface.
     * Si la exportación falla, redirige al index con flash error.
     *
     * @param  \Illuminate\Http\Request  $request  Request con parámetros de índice
     */
    public function export(\Illuminate\Http\Request $request): HttpStreamedResponse|RedirectResponse
    {
        // Check permission directly since class-based authorization is problematic
        $user = $request->user();
        $permission = $this->exportPermission();

        // Debug output for testing
        if (app()->environment('testing')) {
            \Log::info('Export auth debug', [
                'user_id' => $user?->id,
                'permission' => $permission,
                'has_permission' => $user?->can($permission),
                'user_permissions' => $user?->getAllPermissions()->pluck('name')->toArray(),
            ]);
        }

        if (! $user || ! $user->can($permission)) {
            abort(403, 'Unauthorized to export');
        }

        try {
            // Create a simple ListQuery DTO directly for export to avoid validation issues
            // Aceptar tanto per_page como perPage por compatibilidad con frontend
            $perPage = (int) ($request->get('per_page') ?? $request->get('perPage') ?? 15);

            $dto = new \App\DTO\ListQuery(
                q: $request->get('q'),
                page: (int) $request->get('page', 1),
                perPage: $perPage,
                sort: $request->get('sort', 'id'),
                dir: $request->get('dir', 'asc'),
                filters: $request->get('filters', [])
            );
            $format = strtolower((string) ($request->query('format', 'csv')));

            if (! in_array($format, $this->allowedExportFormats(), true)) {
                $format = 'csv';
            }

            return $this->service->export($dto, $format);
        } catch (DomainActionException $e) {
            // Debug in testing
            if (app()->environment('testing')) {
                \Log::error('Export DomainActionException', ['message' => $e->getMessage()]);
            }

            return $this->fail($this->indexRouteName(), [], $e->getMessage());
        } catch (\Exception $e) {
            // Debug in testing
            if (app()->environment('testing')) {
                \Log::error('Export Exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return $this->fail($this->indexRouteName(), [], 'Error durante la exportación. Inténtelo nuevamente.');
        }
    }

    /**
     * POST /resource/bulk (operaciones masivas comunes del Index)
     *
     * Ejecuta operaciones masivas: delete, restore, forceDelete, setActive.
     * Soporta tanto IDs como UUIDs para flexibilidad del modelo.
     * Redirige con mensaje flash de éxito/error.
     *
     * Expected body: {
     *   action: 'delete'|'restore'|'forceDelete'|'setActive',
     *   ids?: number[],
     *   uuids?: string[],
     *   active?: boolean
     * }
     */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate([
            'action' => 'required|in:delete,restore,forceDelete,setActive',
            'ids' => 'array|nullable',
            'ids.*' => 'integer|min:1',
            'uuids' => 'array|nullable',
            'uuids.*' => 'string|uuid',
            'active' => 'boolean|nullable',
        ]);

        $action = $request->string('action')->value();
        $this->authorize('bulk', [$this->policyModel(), $action]);
        $ids = $request->input('ids', []);
        $uuids = $request->input('uuids', []);
        $active = $request->boolean('active', true);

        if (empty($ids) && empty($uuids)) {
            return $this->fail($this->indexRouteName(), [], 'Se requieren IDs o UUIDs para la operación');
        }

        try {
            // Ejecutar operación bulk correspondiente
            $count = match ($action) {
                'delete' => $this->service->bulkDeleteByIds($ids) + $this->service->bulkDeleteByUuids($uuids),
                'restore' => $this->service->bulkRestoreByIds($ids) + $this->service->bulkRestoreByUuids($uuids),
                'forceDelete' => $this->service->bulkForceDeleteByIds($ids) + $this->service->bulkForceDeleteByUuids($uuids),
                'setActive' => $this->service->bulkSetActiveByIds($ids, $active) + $this->service->bulkSetActiveByUuids($uuids, $active),
                default => throw new \InvalidArgumentException("Invalid action: {$action}")
            };

            $actionMessages = [
                'delete' => 'eliminados',
                'restore' => 'restaurados',
                'forceDelete' => 'eliminados permanentemente',
                'setActive' => $active ? 'activados' : 'desactivados',
            ];

            $message = sprintf('%d registro(s) %s exitosamente', $count, $actionMessages[$action]);

            return $this->ok($this->indexRouteName(), [], $message);
        } catch (DomainActionException $e) {
            return $this->fail($this->indexRouteName(), [], $e->getMessage());
        } catch (\Exception $e) {
            return $this->fail($this->indexRouteName(), [], 'Error durante la operación masiva. Inténtelo nuevamente.');
        }
    }

    /**
     * GET /resource/selected?ids[]=1&ids[]=2
     *
     * Devuelve registros específicos del index por sus IDs.
     * Útil para checkboxes y selección múltiple en datatables.
     * Responde con estructura JSON compatible con select2, datatables, etc.
     *
     * Example response: {
     *   rows: [...],
     *   total: 2
     * }
     */
    public function selected(Request $request): JsonResponse
    {
        $this->authorize('viewSelected', $this->policyModel());

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $ids = $validated['ids'];
        $perPage = (int) ($validated['perPage'] ?? 15);

        $result = $this->service->listByIdsDesc($ids, $perPage, $this->with(), $this->withCount());

        return response()->json([
            'rows' => $result['rows'],
            'total' => count($result['rows']),
        ]);
    }

    // Helper methods para redirects con flash messages

    /**
     * Redirigir con mensaje de éxito
     *
     * @param  string  $routeName  Nombre de la ruta de destino
     * @param  array<string, mixed>  $params  Parámetros de la ruta
     * @param  string|null  $message  Mensaje flash de éxito
     */
    protected function ok(string $routeName, array $params = [], ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route($routeName, $params);

        if ($message !== null) {
            $redirect->with('success', $message);
        }

        return $redirect;
    }

    /**
     * Redirigir con mensaje de error
     *
     * @param  string  $routeName  Nombre de la ruta de destino
     * @param  array<string, mixed>  $params  Parámetros de la ruta
     * @param  string|null  $message  Mensaje flash de error
     */
    protected function fail(string $routeName, array $params = [], ?string $message = null): RedirectResponse
    {
        $redirect = redirect()->route($routeName, $params);

        if ($message !== null) {
            $redirect->with('error', $message);
        }

        return $redirect;
    }

    /**
     * Nombre de la ruta del index para redirects
     *
     * @return string Nombre de la ruta (ej: 'users.index')
     */
    abstract protected function indexRouteName(): string;

    /**
     * Get the export permission name.
     * Override this to customize the export permission name.
     *
     * @return string The export permission name
     */
    protected function exportPermission(): string
    {
        // Extract the base name from the route (e.g., 'roles' from 'roles.index')
        $routeName = $this->indexRouteName();
        $baseName = explode('.', $routeName)[0];

        return $baseName.'.export';
    }
}
