<?php

declare(strict_types=1);

return [
    'permissions' => [
        // Users management
        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.restore',
        'users.forceDelete',
        'users.export',
        'users.setActive',
    ],

    'descriptions' => [
        'users.view' => 'Ver usuarios',
        'users.create' => 'Crear usuarios',
        'users.update' => 'Actualizar usuarios',
        'users.delete' => 'Eliminar usuarios',
        'users.restore' => 'Restaurar usuarios',
        'users.forceDelete' => 'Eliminar permanentemente usuarios',
        'users.export' => 'Exportar usuarios',
        'users.setActive' => 'Activar/desactivar usuarios',
    ],

    // Business rules for user activation/deactivation
    'users' => [
        'activation' => [
            // Prevent the authenticated user from deactivating themselves
            'block_self_deactivate' => true,
            // Optionally block deactivation if user is the last admin
            'block_deactivate_if_last_admin' => true,
            // Role name considered admin for last-admin checks
            'admin_role_name' => 'admin',
        ],
        'deletion' => [
            // Optionally require user to be inactive before deletion
            'require_inactive' => true,
            // Optionally block deleting the last admin user
            'block_if_last_admin' => true,
        ],
    ],
];
