<?php

declare(strict_types=1);

return [
    'permissions' => [
        'reports.view',
        'reports.bank_validations.view',
        'reports.bank_validations.export',
        'reports.daily_bank_reconciliation.view',
        'reports.daily_bank_reconciliation.export',
        'reports.contracts_unsigned.view',
        'reports.contracts_unsigned.export',
        'reports.concessionaire_changes.view',
        'reports.concessionaire_changes.export',
        'reports.locals_recovered.view',
        'reports.locals_recovered.export',
        'reports.locals_financial_status.view',
        'reports.locals_financial_status.export',
    ],
    'descriptions' => [
        'reports.view' => 'Acceder al módulo de Reportes',
        'reports.bank_validations.view' => 'Ver reporte de Validaciones Bancarias',
        'reports.bank_validations.export' => 'Exportar reporte de Validaciones Bancarias (CSV/Excel)',
        'reports.daily_bank_reconciliation.view' => 'Ver reporte diario de conciliación por banco (tesorería)',
        'reports.daily_bank_reconciliation.export' => 'Exportar reporte diario de conciliación por banco (CSV/JSON)',
        'reports.contracts_unsigned.view' => 'Ver reporte de Contratos sin firma',
        'reports.contracts_unsigned.export' => 'Exportar reporte de Contratos sin firma (CSV/JSON)',
        'reports.concessionaire_changes.view' => 'Ver reporte de Cambios de cesionario por local',
        'reports.concessionaire_changes.export' => 'Exportar reporte de Cambios de cesionario (CSV/JSON)',
        'reports.locals_recovered.view' => 'Ver reporte de Locales recuperados',
        'reports.locals_recovered.export' => 'Exportar reporte de Locales recuperados (CSV/JSON)',
        'reports.locals_financial_status.view' => 'Ver reporte de Estado financiero de locales',
        'reports.locals_financial_status.export' => 'Exportar reporte de Estado financiero de locales (CSV/JSON/Excel)',
    ],
];
