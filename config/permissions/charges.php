<?php

declare(strict_types=1);

return [
    'permissions' => [
        'charges.view',
        'charges.run',
        'charges.export',
        'charges.cancel',
        'charges.extra.create',
        'charges.collectibility.view',
        'charges.collectibility.mark',
        'charges.collectibility.restore',
    ],
    'descriptions' => [
        'charges.view' => 'Ver cargos generados',
        'charges.run' => 'Ejecutar generación de cargos (Run now)',
        'charges.export' => 'Exportar cargos generados',
        'charges.cancel' => 'Anular cargos (marcarlos como CANCELED)',
        'charges.extra.create' => 'Crear cargos extraordinarios (multas, ajustes manuales)',
        'charges.collectibility.view' => 'Ver clasificación de cargos incobrables',
        'charges.collectibility.mark' => 'Declarar cargos como incobrables',
        'charges.collectibility.restore' => 'Restaurar cargos incobrables como cobrables',
    ],
];
