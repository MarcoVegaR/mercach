<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Http\Requests\BaseStoreRequest;
use App\Http\Requests\BaseUpdateRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trait para controladores con funcionalidad Create/Edit usando Inertia.
 *
 * Proporciona métodos estándar para formularios integrando Policies,
 * Services e Inertia con redirects 303 para evitar reenvíos.
 *
 * El controlador que use este trait debe:
 * - Inyectar ServiceInterface en la propiedad protected $service
 * - Implementar los hooks abstract/protected requeridos
 * - Registrar las rutas apropiadas (GET create, POST store, GET edit, PUT update)
 */
trait HandlesForm
{
    use HandlesIndexAndExport;

    /**
     * Vista Inertia para renderizar el formulario.
     *
     * @param  string  $mode  'create' o 'edit'
     * @return string Nombre de la vista (ej: 'roles/form')
     */
    abstract protected function formView(string $mode): string;

    /**
     * Opciones adicionales para el formulario (selects, catálogos, etc).
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        return [];
    }

    /**
     * FormRequest class para store operations.
     *
     * @return class-string<BaseStoreRequest>
     */
    abstract protected function storeRequestClass(): string;

    /**
     * FormRequest class para update operations.
     *
     * @return class-string<BaseUpdateRequest>
     */
    abstract protected function updateRequestClass(): string;

    /**
     * Get empty model structure for create form.
     *
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [];
    }

    /**
     * GET /resource/create
     *
     * Muestra el formulario de creación.
     */
    public function create(): Response
    {
        $this->authorize('create', $this->policyModel());

        return Inertia::render($this->formView('create'), [
            'mode' => 'create',
            'model' => $this->getEmptyModel(),
            ...$this->formOptions(),
        ]);
    }

    /**
     * POST /resource
     *
     * Almacena un nuevo recurso.
     */
    public function store(\Illuminate\Http\Request $request): RedirectResponse
    {
        $this->authorize('create', $this->policyModel());

        // Resolve and validate the concrete request class
        $requestClass = $this->storeRequestClass();
        $validatedRequest = $requestClass::createFrom($request);
        $validatedRequest->setContainer(app());
        $validatedRequest->setRedirector(app('redirect'));
        $validatedRequest->validateResolved();

        try {
            $validated = $validatedRequest->validated();
            $model = $this->service->create($validated);

            return $this->ok(
                $this->indexRouteName(),
                [],
                $this->getSuccessMessage('created', $model)
            );
        } catch (\App\Exceptions\DomainActionException $e) {
            return $this->fail(
                $this->createRouteName(),
                [],
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return $this->fail(
                $this->createRouteName(),
                [],
                'Error al crear el registro. Por favor, intente nuevamente.'
            );
        }
    }

    /**
     * GET /resource/{id}/edit
     *
     * Muestra el formulario de edición.
     */
    public function edit(Model|int|string $modelOrId): Response
    {
        $model = $this->resolveModel($modelOrId);
        $this->authorize('update', $model);

        return Inertia::render($this->formView('edit'), [
            'mode' => 'edit',
            'model' => $this->service->toItem($model),
            ...$this->formOptions(),
        ]);
    }

    /**
     * PUT /resource/{id}
     *
     * Actualiza un recurso existente.
     */
    public function update(\Illuminate\Http\Request $request, Model|int|string $modelOrId): RedirectResponse
    {
        $model = $this->resolveModel($modelOrId);
        $this->authorize('update', $model);

        // Resolve and validate the concrete request class
        $requestClass = $this->updateRequestClass();
        $validatedRequest = $requestClass::createFrom($request);
        $validatedRequest->setContainer(app());
        $validatedRequest->setRedirector(app('redirect'));
        $validatedRequest->validateResolved();

        try {
            $validated = $validatedRequest->validated();
            $expectedUpdatedAt = $request->input('_version');

            $model = $this->service->update($model, $validated, $expectedUpdatedAt);

            return $this->ok(
                $this->indexRouteName(),
                [],
                $this->getSuccessMessage('updated', $model)
            );
        } catch (\App\Exceptions\DomainActionException $e) {
            return $this->fail(
                $this->editRouteName($model),
                $this->getRouteParameters($model),
                $e->getMessage()
            );
        } catch (\Exception $e) {
            return $this->fail(
                $this->editRouteName($model),
                $this->getRouteParameters($model),
                'Error al actualizar el registro. Por favor, intente nuevamente.'
            );
        }
    }

    /**
     * Resuelve el modelo desde un ID o instancia.
     */
    protected function resolveModel(Model|int|string $modelOrId): Model
    {
        if ($modelOrId instanceof Model) {
            return $modelOrId;
        }

        return $this->service->getOrFailById($modelOrId);
    }

    /**
     * Obtiene el mensaje de éxito para operaciones.
     *
     * @param  string  $action  'created' o 'updated'
     */
    protected function getSuccessMessage(string $action, Model $model): string
    {
        $messages = [
            'created' => 'Registro creado exitosamente.',
            'updated' => 'Registro actualizado exitosamente.',
        ];

        return $messages[$action] ?? 'Operación completada exitosamente.';
    }

    /**
     * Nombre de la ruta de creación.
     */
    protected function createRouteName(): string
    {
        $base = explode('.', $this->indexRouteName())[0];

        return $base.'.create';
    }

    /**
     * Get the edit route name.
     */
    protected function editRouteName(Model $model): string
    {
        $base = explode('.', $this->indexRouteName())[0];

        return $base.'.edit';
    }

    /**
     * Get route parameters for the model.
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        // Use the model's route key name (e.g., 'role' for Role model)
        $routeKey = strtolower(class_basename($model));

        return [$routeKey => $model->getKey()];
    }
}
