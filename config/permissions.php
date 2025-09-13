<?php

$guard = 'web';

$permissions = [];
$descriptions = [];

$moduleDir = __DIR__.'/permissions';
if (is_dir($moduleDir)) {
    foreach (glob($moduleDir.'/*.php') as $file) {
        $data = require $file;
        if (! is_array($data)) {
            continue;
        }
        $permissions = array_merge($permissions, $data['permissions'] ?? []);
        $descriptions = array_merge($descriptions, $data['descriptions'] ?? []);
    }
}

$permissions = array_values(array_unique($permissions));

return [
    // Single guard for the SPA
    'guard' => $guard,

    // Flat list of permission names (contract)
    'permissions' => $permissions,

    // Descriptions per permission (used by seeders/UI)
    'descriptions' => $descriptions,
];
