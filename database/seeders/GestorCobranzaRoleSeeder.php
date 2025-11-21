<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GestorCobranzaRoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('permissions.guard', 'web');

        $permNames = [
            'dashboard.view',
            'dashboard.view.cards',
            // Sin comodín de gráficas generales; se usan permisos por categoría
            // No incluir 'dashboard.view.charts.contracts' para que no vea gráficas de contratos
            'dashboard.view.charts.locals',
            'dashboard.view.charts.concessionaires',
            'dashboard.view.charts.debt',
            'dashboard.view.charts.payments',
            'dashboard.view.finance',
            'dashboard.view.table',

            'condo_period.view',
            'condo_period.create',
            'condo_period.update',
            'condo_period.export',
            'condo_period.setActive',
            'condo_period.finalize',
            'condo_period.reopen',

            'charges.view',
            'charges.run',
            'charges.export',
            'charges.extra.create',

            'catalogs.payment.view',
            'catalogs.payment.create',
            'catalogs.payment.update',
            'catalogs.payment.export',
            'catalogs.payment.setActive',

            'admin.economic_profile.view',
            'admin.economic_profile.export',

            'catalogs.concessionaire.view',

            'catalogs.local.view',

            'catalogs.expense-type.view',
            'catalogs.expense-type.create',
            'catalogs.expense-type.update',
            'catalogs.expense-type.export',
            'catalogs.expense-type.setActive',

            'catalogs.fx-rate.view',
            'catalogs.fx-rate.create',
            'catalogs.fx-rate.update',
            'catalogs.fx-rate.export',
            'catalogs.fx-rate.setActive',

            'settings.profile.view',
            'settings.profile.update',
            'settings.password.update',
            'settings.appearance.view',
            'settings.security.view',
            'settings.security.sessions.manage',
            'settings.security.2fa.manage',
        ];

        $perms = Permission::query()
            ->whereIn('name', $permNames)
            ->where('guard_name', $guard)
            ->get();

        $role = Role::firstOrCreate([
            'name' => 'gestor-cobranza',
            'guard_name' => $guard,
        ]);

        $role->syncPermissions($perms);
    }
}
