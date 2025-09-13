<?php

return [
    'permissions' => [
        // Settings
        'settings.profile.view',
        'settings.profile.update',
        // 'settings.profile.delete', // disabled: users cannot delete their own account
        'settings.password.update',
        'settings.appearance.view',
        'settings.security.view',
        'settings.security.sessions.manage',
        'settings.security.2fa.manage',
    ],

    'descriptions' => [
        'settings.profile.view' => 'Ver perfil',
        'settings.profile.update' => 'Actualización de Perfil',
        // 'settings.profile.delete' => 'Eliminar cuenta', // disabled
        'settings.password.update' => 'Actualización de Password',
        'settings.appearance.view' => 'Ver Apariencia',
        'settings.security.view' => 'Ver Seguridad',
        'settings.security.sessions.manage' => 'Cerrar sesiones en otros dispositivos',
        'settings.security.2fa.manage' => 'Gestionar Autenticación de Dos Factores',
    ],
];
