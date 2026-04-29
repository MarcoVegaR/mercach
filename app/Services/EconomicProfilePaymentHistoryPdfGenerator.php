<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class EconomicProfilePaymentHistoryPdfGenerator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<int>  $selectedLocalIds
     * @return array{raw:string, filename:string}
     */
    public function render(array $data, string $scope, int $id, Carbon $at, array $selectedLocalIds = []): array
    {
        [$letterheadBase64, $letterheadMime] = $this->loadBrandingAsset('letterhead');
        [$logoBase64, $logoMime] = $this->loadBrandingAsset('logo');

        $header = (array) ($data['header'] ?? []);
        $payments = array_values((array) ($data['payments'] ?? []));
        $includedLocals = array_values((array) ($data['included_locals'] ?? []));

        $selectedSet = array_values(array_filter(array_unique($selectedLocalIds), static fn (int $value): bool => $value > 0));
        if (! empty($selectedSet)) {
            $selectedMap = array_fill_keys($selectedSet, true);
            $includedLocals = array_values(array_filter($includedLocals, fn ($local) => isset($selectedMap[(int) ($local['id'] ?? 0)])));
        }

        $includedLocalCodes = array_values(array_filter(array_map(fn ($local) => (string) ($local['code'] ?? ''), $includedLocals)));

        $totalAmount = array_sum(array_map(fn ($row) => (int) ($row['amount_bs_minor'] ?? 0), $payments));
        $totalApplied = array_sum(array_map(fn ($row) => (int) ($row['crossed_bs_minor'] ?? $row['applied_bs_minor'] ?? 0), $payments));
        $totalAvailable = array_sum(array_map(fn ($row) => (int) ($row['available_bs_minor'] ?? 0), $payments));

        $scopeLabel = $scope === 'local' ? 'LOCAL' : 'CONCESSIONAIRE';
        $filename = 'historico_pagos_'.$scopeLabel.'_'.$id.'_'.$at->format('Ymd').'.pdf';

        $html = view('pdf.economic_profile_payment_history', [
            'header' => $header,
            'payments' => $payments,
            'at' => $at->toDateString(),
            'scope' => $scope,
            'scope_label' => $scopeLabel,
            'scope_id' => $id,
            'included_local_codes' => $includedLocalCodes,
            'totals' => [
                'amount_bs_minor' => $totalAmount,
                'applied_bs_minor' => $totalApplied,
                'available_bs_minor' => $totalAvailable,
                'count' => count($payments),
            ],
            'letterhead_base64' => $letterheadBase64,
            'letterhead_mime' => $letterheadMime,
            'logo_base64' => $logoBase64,
            'logo_mime' => $logoMime,
        ])->render();

        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4');

            return [
                'raw' => (string) $pdf->output(),
                'filename' => $filename,
            ];
        }

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
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
