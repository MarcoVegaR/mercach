<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

class ActivateBulkRolesRequest extends FormRequest
{
    /** @var array<int,string> */
    protected array $skipped = [];

    public function authorize(): bool
    {
        return $this->user()?->can('bulk', [Role::class, 'setActive']) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:setActive'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:roles,id'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function active(): bool
    {
        return (bool) $this->input('active', true);
    }

    /**
     * Retrieve Role models for given ids.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        $ids = (array) $this->input('ids', []);
        if (empty($ids)) {
            return collect();
        }

        return Role::whereIn('id', $ids)->get();
    }

    /**
     * Partition roles into updatable and skipped (with reasons) based on validations.
     *
     * @return array{updatable: Collection<int, Role>, skipped: array<int,string>}
     */
    public function getUpdatableRolesAndSkipped(): array
    {
        $this->skipped = [];

        $protected = (array) config('permissions.roles.protected', []);
        $active = $this->active();
        $roles = $this->roles();

        $updatable = collect();

        foreach ($roles as $role) {
            // Protected by name - skip changing protected roles
            if (in_array($role->name, $protected, true)) {
                $this->skipped[$role->id] = 'Rol protegido';

                continue;
            }

            // Skip if already in the desired state
            $currentState = (bool) $role->getAttribute('is_active');
            if ($currentState === $active) {
                $actionWord = $active ? 'activo' : 'inactivo';
                $this->skipped[$role->id] = "Ya está {$actionWord}";

                continue;
            }

            // If deactivating, ensure role has no active users assigned
            if (! $active && $role->users()->exists()) {
                $this->skipped[$role->id] = 'Tiene usuarios activos asignados';

                continue;
            }

            $updatable->push($role);
        }

        return [
            'updatable' => $updatable,
            'skipped' => $this->skipped,
        ];
    }

    /**
     * Get skipped reasons map id => reason.
     *
     * @return array<int,string>
     */
    public function skippedReasons(): array
    {
        return $this->skipped;
    }
}
