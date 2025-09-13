<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\RoleServiceInterface;
use App\DTO\ShowQuery;
use App\Http\Controllers\Concerns\HandlesForm;
use App\Http\Requests\ActivateBulkRolesRequest;
use App\Http\Requests\DeleteBulkRolesRequest;
use App\Http\Requests\DeleteRolesRequest;
use App\Http\Requests\RoleIndexRequest;
use App\Http\Requests\RoleShowRequest;
use App\Http\Requests\RoleStoreRequest;
use App\Http\Requests\RoleUpdateRequest;
use App\Http\Requests\SetRoleActiveRequest;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

/**
 * Controller for Role management operations.
 *
 * @author Laravel Boilerplate
 */
class RolesController extends BaseIndexController
{
    use HandlesForm;

    /**
     * Create a new controller instance.
     *
     * @param  RoleServiceInterface  $roleService
     */
    private RoleServiceInterface $roleService;

    public function __construct(RoleServiceInterface $roleService)
    {
        parent::__construct($roleService);
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the resource.
     *
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);

        $extras = $this->roleService->getIndexExtras();
        $response->with('stats', $extras['stats'] ?? []);
        $response->with('availablePermissions', $extras['availablePermissions'] ?? []);
        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('roles.edit'));

        return $response;
    }

    /**
     * Display the specified resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show(Request $request, Role $role): \Inertia\Response
    {
        // Authorize view action via policy
        $this->authorize('view', $role);

        // Convert request to ShowRequest for validation
        $showRequest = RoleShowRequest::createFrom($request);
        $showRequest->setContainer(app());
        $showRequest->setRedirector(app('redirect'));
        $showRequest->validateResolved();

        // Build ShowQuery from validated request
        $query = $showRequest->toShowQuery();

        // Get show data from service
        $data = $this->roleService->showById($role->id, $query);
        // Add flag for edit route availability
        $data['hasEditRoute'] = Route::has('roles.edit');

        // Return Inertia response
        return Inertia::render('roles/show', $data);
    }

    /**
     * Get the policy model class.
     */
    protected function policyModel(): string
    {
        return \Spatie\Permission\Models\Role::class;
    }

    /**
     * Get the view name for index.
     */
    protected function view(): string
    {
        return 'roles/index';
    }

    /**
     * Get additional data to include in the view.
     *
     * @return array<string>
     */
    protected function with(): array
    {
        return ['permissions'];
    }

    /**
     * Get relationships to count.
     *
     * @return array<string>
     */
    protected function withCount(): array
    {
        return [];
    }

    /**
     * Get the index route name.
     */
    protected function indexRouteName(): string
    {
        return 'roles.index';
    }

    /**
     * Get allowed export formats.
     *
     * @return array<string>
     */
    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    /**
     * Get the index request class.
     */
    protected function indexRequestClass(): string
    {
        return RoleIndexRequest::class;
    }

    /**
     * Get the form view name for create/edit.
     *
     * @param  string  $mode  'create' o 'edit'
     */
    protected function formView(string $mode): string
    {
        return 'roles/form';
    }

    /**
     * Get additional data for the form view.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        // Get all available permissions for the form
        $permissions = Permission::query()
            ->select(['id', 'name', 'description', 'guard_name'])
            ->orderBy('name')
            ->get()
            ->map(function (Permission $permission): array {
                return [
                    'value' => $permission->id,
                    'label' => (string) ($permission->getAttribute('description') ?? $permission->name),
                    'name' => $permission->name,
                    'guard' => $permission->guard_name,
                ];
            })
            ->toArray();

        return [
            'permissions' => $permissions,
            'guards' => [
                ['value' => 'web', 'label' => 'Web'],
            ],
        ];
    }

    /**
     * Get the store request class.
     */
    protected function storeRequestClass(): string
    {
        return RoleStoreRequest::class;
    }

    /**
     * Get the update request class.
     */
    protected function updateRequestClass(): string
    {
        return RoleUpdateRequest::class;
    }

    /**
     * Get empty model structure for create form.
     *
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'name' => null,
            'guard_name' => null,
            'is_active' => null,
            'permissions' => [],
        ];
    }

    /**
     * Delete the specified resource from storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(DeleteRolesRequest $request, Role $role)
    {
        $this->authorize('delete', $role);

        $roleName = $role->name;
        $this->roleService->deleteSafely($role);

        return redirect()->route('roles.index')
            ->with('success', "El rol '{$roleName}' ha sido eliminado correctamente.");
    }

    /**
     * Set the active state for a role.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function setActive(SetRoleActiveRequest $request, Role $role)
    {
        $this->authorize('setActive', $role);

        $desired = (bool) $request->boolean('active');

        $role->setAttribute('is_active', $desired);
        $role->save();

        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('roles.index')
            ->with('success', "El rol '{$role->name}' ha sido {$actionText} correctamente.");
    }

    /**
     * Handle bulk actions for roles.
     *
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function bulk(Request $request): \Illuminate\Http\RedirectResponse
    {
        $action = $request->input('action');

        // Handle bulk delete with custom validation
        if ($action === 'delete') {
            // Authorization for bulk delete
            $this->authorize('bulk', [Role::class, 'delete']);

            // Convert to FormRequest to run centralized validations
            $validatedRequest = DeleteBulkRolesRequest::createFrom($request);
            $validatedRequest->setContainer(app());
            $validatedRequest->setRedirector(app('redirect'));
            $validatedRequest->validateResolved();

            // Centralized validations in the request
            [$deletable, $skipped] = array_values($validatedRequest->getDeletableRolesAndSkipped());

            $deletedCount = 0;
            foreach ($deletable as $role) {
                $this->roleService->deleteSafely($role);
                $deletedCount++;
            }

            $skippedCount = count($skipped);

            if ($skippedCount > 0) {
                return redirect()->route('roles.index')
                    ->with('warning', "Se eliminaron {$deletedCount} rol(es). Se omitieron {$skippedCount} rol(es) por validaciones de eliminación.");
            }

            return redirect()->route('roles.index')
                ->with('success', "Se eliminaron {$deletedCount} rol(es) correctamente.");
        }

        // Handle bulk setActive with custom validation
        if ($action === 'setActive') {
            // Authorization for bulk setActive
            $this->authorize('bulk', [Role::class, 'setActive']);

            // Convert to FormRequest to run centralized validations
            $validatedRequest = ActivateBulkRolesRequest::createFrom($request);
            $validatedRequest->setContainer(app());
            $validatedRequest->setRedirector(app('redirect'));
            $validatedRequest->validateResolved();

            // Centralized validations in the request
            [$updatable, $skipped] = array_values($validatedRequest->getUpdatableRolesAndSkipped());

            $active = $validatedRequest->active();
            $updatedCount = 0;

            foreach ($updatable as $role) {
                $role->setAttribute('is_active', $active);
                $role->save();
                $updatedCount++;
            }

            $skippedCount = count($skipped);
            $actionText = $active ? 'activaron' : 'desactivaron';
            $actionTextSingle = $active ? 'activado' : 'desactivado';

            if ($skippedCount > 0) {
                return redirect()->route('roles.index')
                    ->with('warning', "Se {$actionText} {$updatedCount} rol(es). Se omitieron {$skippedCount} rol(es) por validaciones.");
            }

            if ($updatedCount === 0) {
                return redirect()->route('roles.index')
                    ->with('info', 'No se realizó ningún cambio. Todos los roles ya estaban en el estado solicitado.');
            }

            return redirect()->route('roles.index')
                ->with('success', "Se {$actionText} {$updatedCount} rol(es) correctamente.");
        }

        // Delegate other actions to the parent implementation
        return parent::bulk($request);
    }
}
