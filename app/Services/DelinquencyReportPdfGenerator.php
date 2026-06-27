<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DelinquencyReportPdfGenerator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{raw:string, filename:string}
     */
    public function render(array $data): array
    {
        [$letterheadBase64, $letterheadMime] = $this->loadBrandingAsset('letterhead');
        [$logoBase64, $logoMime] = $this->loadBrandingAsset('logo');

        $filters = (array) ($data['filters'] ?? []);
        $scope = (string) ($filters['scope'] ?? 'concessionaire');
        $debtType = (string) ($filters['debt_type'] ?? 'overdue');
        $cutoff = (string) ($filters['cutoff_date'] ?? date('Y-m-d'));
        $filename = 'reporte_morosidad_'.$scope.'_'.$debtType.'_'.$cutoff.'_'.date('His').'.pdf';

        $html = view('pdf.delinquency_report', [
            'data' => $data,
            'filters' => $filters,
            'rows' => (array) ($data['rows'] ?? []),
            'totals' => (array) ($data['totals'] ?? []),
            'letterhead_base64' => $letterheadBase64,
            'letterhead_mime' => $letterheadMime,
            'logo_base64' => $logoBase64,
            'logo_mime' => $logoMime,
        ])->render();

        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4', 'landscape');

            return [
                'raw' => (string) $pdf->output(),
                'filename' => $filename,
            ];
        }

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            return [
                'raw' => (string) $dompdf->output(),
                'filename' => $filename,
            ];
        }

        throw new \RuntimeException('PDF library not installed.');
    }

    /**
     * @return array{0:string|null, 1:string|null}
     */
    private function loadBrandingAsset(string $name): array
    {
        try {
            $diskLocal = Storage::disk('local');
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $path = 'branding/'.$name.'.'.$ext;
                if ($diskLocal->exists($path)) {
                    $bin = $diskLocal->get($path);

                    return [base64_encode($bin), $ext === 'svg' ? 'image/svg+xml' : 'image/'.($ext === 'jpg' ? 'jpeg' : $ext)];
                }
            }
        } catch (\Throwable) {
        }

        foreach ([storage_path('app/branding'), storage_path('app/private/branding')] as $dir) {
            try {
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $file = $dir.DIRECTORY_SEPARATOR.$name.'.'.$ext;
                    if (is_file($file) && is_readable($file)) {
                        $bin = @file_get_contents($file);
                        if ($bin !== false) {
                            return [base64_encode($bin), $ext === 'svg' ? 'image/svg+xml' : 'image/'.($ext === 'jpg' ? 'jpeg' : $ext)];
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        try {
            $uploadsDisk = (string) config('filesystems.uploads_disk', 'public');
            $diskUploads = Storage::disk($uploadsDisk);
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $path = 'branding/'.$name.'.'.$ext;
                if ($diskUploads->exists($path)) {
                    $bin = $diskUploads->get($path);

                    return [base64_encode($bin), $ext === 'svg' ? 'image/svg+xml' : 'image/'.($ext === 'jpg' ? 'jpeg' : $ext)];
                }
            }
        } catch (\Throwable) {
        }

        foreach ([public_path('branding'), public_path()] as $dir) {
            try {
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $file = $dir.DIRECTORY_SEPARATOR.$name.'.'.$ext;
                    if (is_file($file) && is_readable($file)) {
                        $bin = @file_get_contents($file);
                        if ($bin !== false) {
                            return [base64_encode($bin), $ext === 'svg' ? 'image/svg+xml' : 'image/'.($ext === 'jpg' ? 'jpeg' : $ext)];
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return [null, null];
    }
}
