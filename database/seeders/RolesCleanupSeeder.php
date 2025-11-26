<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RolesCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');
        $keep = ['admin', 'concesionario', 'gestor-cobranza', 'consultoria-juridica'];

        $roles = Role::query()
            ->where('guard_name', $guard)
            ->whereNotIn('name', $keep)
            ->get();

        foreach ($roles as $role) {
            DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
            DB::table('model_has_roles')->where('role_id', $role->id)->delete();
            $role->delete();
        }
    }
}
