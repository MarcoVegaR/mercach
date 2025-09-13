<?php

return [

    'models' => [
        'permission' => App\Models\Permission::class,
        'role' => App\Models\Role::class,
    ],

    'table_names' => [
        'roles' => 'roles',
        'permissions' => 'permissions',
        'model_has_permissions' => 'model_has_permissions',
        'model_has_roles' => 'model_has_roles',
        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        'role_pivot_key' => null, // default 'role_id'
        'permission_pivot_key' => null, // default 'permission_id'
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    // Register the permission check on the Gate so `can('permission')` works
    'register_permission_check_method' => true,

    // Octane reset listener (rarely needed)
    'register_octane_reset_listener' => false,

    // Fire attach/detach events
    'events_enabled' => false,

    // Teams feature disabled
    'teams' => false,

    // Class used to resolve team id
    'team_resolver' => \Spatie\Permission\DefaultTeamResolver::class,

    // Do not use Passport Client Credentials
    'use_passport_client_credentials' => false,

    // Do not leak names in exceptions by default
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,

    // Wildcard permissions disabled
    'enable_wildcard_permission' => false,
    // 'wildcard_permission' => Spatie\Permission\WildcardPermission::class,

    'cache' => [
        'expiration_time' => \DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
