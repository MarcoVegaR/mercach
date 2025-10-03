<?php

declare(strict_types=1);

return [
    'permissions' => [
        'condo_period.view',
        'condo_period.create',
        'condo_period.update',
        'condo_period.delete',
        'condo_period.restore',
        'condo_period.forceDelete',
        'condo_period.export',
        'condo_period.setActive',
        // Custom abilities
        'condo_period.finalize',
        'condo_period.reopen',
    ],
    'descriptions' => [
        'condo_period.view' => 'Ver Períodos de condominio',
        'condo_period.create' => 'Crear/auto-crear Período de condominio',
        'condo_period.update' => 'Actualizar Período de condominio (DRAFT)',
        'condo_period.delete' => 'Eliminar Período de condominio (DRAFT, sin cargos)',
        'condo_period.restore' => 'Restaurar Período de condominio',
        'condo_period.forceDelete' => 'Eliminar permanentemente Período de condominio',
        'condo_period.export' => 'Exportar Períodos de condominio',
        'condo_period.setActive' => 'Activar/desactivar Períodos (solo DRAFT sin cargos)',
        'condo_period.finalize' => 'Confirmar Período (DRAFT -> FINAL)',
        'condo_period.reopen' => 'Reabrir Período (FINAL -> DRAFT)',
    ],
];
