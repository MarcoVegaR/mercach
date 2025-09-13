<?php

declare(strict_types=1);

namespace App\Exports;

use App\Contracts\Exports\ExporterInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * JsonExporter - Exportador de datos en formato JSON
 */
class JsonExporter implements ExporterInterface
{
    /**
     * Exporta datos en formato JSON usando streaming
     *
     * @param  iterable<array<string, mixed>>  $rows  Iterador de filas de datos
     * @param  array<string>  $columns  Columnas a incluir en la exportación
     */
    public function stream(iterable $rows, array $columns): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows, $columns) {
            $handle = fopen('php://output', 'w');

            // Start JSON array
            fwrite($handle, '[');

            $first = true;
            foreach ($rows as $row) {
                if (! $first) {
                    fwrite($handle, ',');
                }

                // Map row data to use display names instead of raw keys
                $mappedRow = [];
                foreach ($columns as $key => $label) {
                    $value = $row[$key] ?? '';
                    // Format boolean values for better readability
                    if (is_bool($value)) {
                        $value = $value ? 'Activo' : 'Inactivo';
                    }
                    $mappedRow[$label] = $value;
                }

                fwrite($handle, json_encode($mappedRow, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $first = false;
            }

            // End JSON array
            fwrite($handle, ']');

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Disposition', 'attachment; filename="export.json"');

        return $response;
    }
}
