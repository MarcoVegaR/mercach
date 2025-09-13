<?php

declare(strict_types=1);

namespace App\Contracts\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ExporterInterface - Contrato para exportadores de datos
 *
 * Define la interfaz común para exportar datos en diferentes formatos (CSV, XLSX, PDF)
 */
interface ExporterInterface
{
    /**
     * Exporta datos usando streaming para evitar problemas de memoria
     *
     * @param  iterable<array<string, mixed>>  $rows  Iterador de filas de datos
     * @param  array<string>  $columns  Columnas a incluir en la exportación
     */
    public function stream(iterable $rows, array $columns): StreamedResponse;
}
