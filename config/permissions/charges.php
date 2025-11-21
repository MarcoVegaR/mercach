<?php

declare(strict_types=1);

return [
    'permissions' => [
        'charges.view',
        'charges.run',
        'charges.export',
        'charges.cancel',
        'charges.extra.create',
    ],
    'descriptions' => [
        'charges.view' => 'Ver cargos generados',
        'charges.run' => 'Ejecutar generación de cargos (Run now)',
        'charges.export' => 'Exportar cargos generados',
        'charges.cancel' => 'Anular cargos (marcarlos como CANCELED)',
        'charges.extra.create' => 'Crear cargos extraordinarios (multas, ajustes manuales)',
    ],
];
