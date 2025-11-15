<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ConsultoriaJuridicaRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');

        $permNames = [
            // Dashboard (solo visualización)
            'dashboard.view',
            'dashboard.view.cards',
            'dashboard.view.charts',
            'dashboard.view.charts.contracts',
            'dashboard.view.charts.locals',
            'dashboard.view.charts.concessionaires',
            'dashboard.view.table',

            // Ajustes (perfil/seguridad)
            'settings.profile.view',
            'settings.profile.update',
            'settings.password.update',
            'settings.appearance.view',
            'settings.security.view',
            'settings.security.sessions.manage',
            'settings.security.2fa.manage',

            // Ver locales
            'catalogs.local.view',

            // Administración de concesionarios (CRUD)
            'catalogs.concessionaire.view',
            'catalogs.concessionaire.create',
            'catalogs.concessionaire.update',
            'catalogs.concessionaire.export',
            'catalogs.concessionaire.setActive',

            // Administración de contratos (CRUD)
            'catalogs.contract.view',
            'catalogs.contract.create',
            'catalogs.contract.update',
            'catalogs.contract.delete',
            'catalogs.contract.restore',
            'catalogs.contract.forceDelete',
            'catalogs.contract.export',
            'catalogs.contract.setActive',

            // Administración de rubros (CRUD)
            'catalogs.trade-category.view',
            'catalogs.trade-category.create',
            'catalogs.trade-category.update',
            'catalogs.trade-category.delete',
            'catalogs.trade-category.restore',
            'catalogs.trade-category.forceDelete',
            'catalogs.trade-category.export',
            'catalogs.trade-category.setActive',
        ];

        $perms = Permission::query()
            ->whereIn('name', $permNames)
            ->where('guard_name', $guard)
            ->get();

        $role = Role::firstOrCreate([
            'name' => 'consultoria-juridica',
            'guard_name' => $guard,
        ]);

        $role->syncPermissions($perms);
    }
}
