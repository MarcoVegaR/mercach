<?php

declare(strict_types=1);

use App\Services\ReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

it('exports common expenses column headings', function () {
    $response = app(ReportService::class)->exportLocalsFinancialStatus([], '', 'csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)
        ->toContain('Último mes pagado (Gastos comunes)')
        ->toContain('Moneda (Gastos comunes)')
        ->toContain('Deuda (Gastos comunes)')
        ->toContain('Deuda (Gastos comunes) Bs')
        ->not->toContain('Condominio');
});
