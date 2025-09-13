<?php

return [
    'enabled' => env('AUDIT_ENABLED', true),

    'drivers' => [
        'database' => [
            'connection' => env('DB_CONNECTION'),
            'table' => env('AUDIT_TABLE', 'audits'),
        ],
    ],

    'driver' => env('AUDIT_DRIVER', 'database'),

    'user' => [
        'morph_prefix' => env('AUDIT_USER_MORPH_PREFIX', 'user'),
        'guards' => ['web'],
        'resolver' => OwenIt\Auditing\Resolvers\UserResolver::class,
    ],

    'events' => ['created', 'updated', 'deleted', 'restored'],

    'strict' => false,

    'queue' => false,

    'threshold' => 0,

    'console' => false,
];
