<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\UserServiceInterface;
use App\Http\Requests\DeleteUsersRequest;
use App\Http\Requests\SetUserActiveRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Requests\UserShowRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class UsersController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private UserServiceInterface $userService;

    public function __construct(UserServiceInterface $userService)
    {
        parent::__construct($userService);
        $this->userService = $userService;
    }

    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);

        // Provide available roles for filter dropdown
        $availableRoles = Role::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name])
            ->toArray();

        $response->with('availableRoles', $availableRoles);
        $response->with('hasCreateRoute', Route::has('users.create'));

        // Stats: total, inactive, active users
        try {
            $total = User::query()->count();
            $inactive = User::query()->where('is_active', false)->count();
            $active = max(0, $total - $inactive);
            $response->with('stats', [
                'total' => $total,
                'inactive' => $inactive,
                'active' => $active,
            ]);
        } catch (\Throwable) {
            // If schema not ready (e.g., during some tests), skip stats to avoid breaking index
        }

        return $response;
    }

    protected function policyModel(): string
    {
        return User::class;
    }

    protected function view(): string
    {
        return 'users/index';
    }

    /**
     * No extra with()/withCount() required; repository adds roles_count via withRelations().
     */
    protected function with(): array
    {
        return [];
    }

    protected function withCount(): array
    {
        return [];
    }

    protected function indexRouteName(): string
    {
        return 'users.index';
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function indexRequestClass(): string
    {
        return UserIndexRequest::class;
    }

    /**
     * Form view name for create/edit.
     */
    protected function formView(string $mode): string
    {
        return 'users/form';
    }

    /**
     * Options for the users form (role options).
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $roleOptions = Role::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name])
            ->toArray();

        return [
            'options' => [
                'roleOptions' => $roleOptions,
            ],
        ];
    }

    /**
     * Store request class.
     */
    protected function storeRequestClass(): string
    {
        return UserStoreRequest::class;
    }

    /**
     * Update request class.
     */
    protected function updateRequestClass(): string
    {
        return UserUpdateRequest::class;
    }

    /**
     * Empty model for create form.
     *
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'name' => null,
            'email' => null,
            'is_active' => true,
            'roles_ids' => [],
        ];
    }

    /**
     * Display the specified resource.
     * Mirrors RolesController::show pattern.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function show(Request $request, User $user): \Inertia\Response
    {
        // Authorize view action via policy
        $this->authorize('view', $user);

        // Convert request to UserShowRequest for validation
        $showRequest = UserShowRequest::createFrom($request);
        $showRequest->setContainer(app());
        $showRequest->setRedirector(app('redirect'));
        $showRequest->validateResolved();

        // Build ShowQuery from validated request
        $query = $showRequest->toShowQuery();

        // Get show data from service
        $data = $this->userService->showById($user->id, $query);
        // Keep full item payload; relations/counts are additive when requested (
        // tests accept extra properties via ->etc()). Do not minimize to avoid
        // overwriting base fields (e.g., name/email) during partial reloads.
        $data['hasEditRoute'] = Route::has('users.edit');

        // Return Inertia response
        return Inertia::render('users/show', $data);
    }

    /**
     * Set the active state for a user.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function setActive(SetUserActiveRequest $request, User $user)
    {
        $this->authorize('setActive', $user);

        $desired = (bool) $request->boolean('active');

        $this->userService->setActive($user, $desired);

        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('users.index')
            ->with('success', "El usuario '{$user->name}' ha sido {$actionText} correctamente.");
    }

    /**
     * Delete the specified user from storage.
     *
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(DeleteUsersRequest $request, User $user)
    {
        $this->authorize('delete', $user);

        $userName = $user->name;
        $this->userService->delete($user);

        return redirect()->route('users.index')
            ->with('success', "El usuario '{$userName}' ha sido eliminado correctamente.");
    }
}
