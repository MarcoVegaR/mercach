<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Helper for exporting data to CSV or JSON format.
 */
class CsvExportHelper
{
    /**
     * Export a collection to CSV or JSON response.
     *
     * @param  iterable<int, array<string, mixed>>  $data
     * @param  string  $filename  Base filename (without extension)
     * @param  string  $format  'csv' or 'json'
     */
    public function export(iterable $data, string $filename, string $format = 'csv'): SymfonyResponse
    {
        $collection = $data instanceof Collection ? $data : collect($data);

        if ($format === 'json') {
            return $this->exportJson($collection);
        }

        return $this->exportCsv($collection, $filename);
    }

    /**
     * Export to JSON response.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     */
    public function exportJson(Collection $data): \Illuminate\Http\JsonResponse
    {
        return response()->json($data);
    }

    /**
     * Export to CSV response with UTF-8 BOM for Excel compatibility.
     *
     * @param  Collection<int, array<string, mixed>>  $data
     * @param  string  $filename  Base filename (without extension)
     */
    public function exportCsv(Collection $data, string $filename): StreamedResponse
    {
        $fullFilename = $filename.'_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                return;
            }

            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($data->isNotEmpty()) {
                /** @var array<string, mixed> $first */
                $first = $data->first();
                fputcsv($file, array_keys($first));

                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }

            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fullFilename.'"',
        ]);
    }

    /**
     * Apply limit to collection (useful for large exports).
     *
     * @param  iterable<int, array<string, mixed>>  $data
     * @return Collection<int, array<string, mixed>>
     */
    public function limitForExport(iterable $data, int $limit = 5000): Collection
    {
        $collection = $data instanceof Collection ? $data : collect($data);

        if ($collection->count() > $limit) {
            return $collection->slice(0, $limit)->values();
        }

        return $collection;
    }
}
