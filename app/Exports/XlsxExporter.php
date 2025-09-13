<?php

declare(strict_types=1);

namespace App\Exports;

use App\Contracts\Exports\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * XlsxExporter - Exportador de datos en formato CSV compatible con Excel
 * Genera un archivo CSV con codificación correcta que Excel puede abrir sin problemas
 */
class XlsxExporter implements ExporterInterface
{
    /**
     * Exporta datos en formato CSV compatible con Excel
     *
     * @param  iterable<array<string, mixed>>  $rows  Iterador de filas de datos
     * @param  array<string>  $columns  Columnas a incluir en la exportación
     */
    public function stream(iterable $rows, array $columns): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows, $columns) {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for better Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write header row with column names (not keys)
            fputcsv($handle, array_values($columns));

            // Write data rows
            foreach ($rows as $row) {
                $csvRow = [];
                foreach (array_keys($columns) as $key) {
                    $value = $row[$key] ?? '';
                    // Format boolean values for better readability
                    if (is_bool($value)) {
                        $value = $value ? 'Activo' : 'Inactivo';
                    }
                    $csvRow[] = $value;
                }
                fputcsv($handle, $csvRow);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="export.csv"');

        return $response;
    }
}
