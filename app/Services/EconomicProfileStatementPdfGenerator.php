<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class EconomicProfileStatementPdfGenerator
{
    /**
     * @param  array<string, mixed>  $eco
     * @param  array<int, int|string>  $selectedLocalIds
     * @return array{raw:string, filename:string}
     */
    public function render(array $eco, string $scope, int $id, Carbon $at, array $selectedLocalIds = []): array
    {
        $letterheadBase64 = null;
        $letterheadMime = null;
        try {
            $diskLocal = Storage::disk('local');
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $p = 'branding/letterhead.'.$ext;
                if ($diskLocal->exists($p)) {
                    $bin = $diskLocal->get($p);
                    $letterheadBase64 = base64_encode($bin);
                    $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                    break;
                }
            }
        } catch (\Throwable $e) {
        }
        if (empty($letterheadBase64)) {
            try {
                $dir = storage_path('app/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'letterhead.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $letterheadBase64 = base64_encode($bin);
                            $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if (empty($letterheadBase64)) {
            try {
                $dir = storage_path('app/private/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'letterhead.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $letterheadBase64 = base64_encode($bin);
                            $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if (empty($letterheadBase64)) {
            try {
                $uploadsDisk = (string) config('filesystems.uploads_disk', 'public');
                $diskUploads = Storage::disk($uploadsDisk);
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $p = 'branding/letterhead.'.$ext;
                    if ($diskUploads->exists($p)) {
                        $bin = $diskUploads->get($p);
                        $letterheadBase64 = base64_encode($bin);
                        $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                        break;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if (empty($letterheadBase64)) {
            try {
                foreach ([public_path('branding'), public_path()] as $dir) {
                    foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                        $fp = $dir.DIRECTORY_SEPARATOR.'letterhead.'.$ext;
                        if (is_file($fp) && is_readable($fp)) {
                            $bin = @file_get_contents($fp);
                            if ($bin !== false) {
                                $letterheadBase64 = base64_encode($bin);
                                $letterheadMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                                break 2;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $logoBase64 = null;
        $logoMime = null;
        try {
            $diskLocal = Storage::disk('local');
            foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                $p = 'branding/logo.'.$ext;
                if ($diskLocal->exists($p)) {
                    $bin = $diskLocal->get($p);
                    $logoBase64 = base64_encode($bin);
                    $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                    break;
                }
            }
        } catch (\Throwable $e) {
        }
        if (empty($logoBase64)) {
            try {
                $dir = storage_path('app/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'logo.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $logoBase64 = base64_encode($bin);
                            $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if (empty($logoBase64)) {
            try {
                $dir = storage_path('app/private/branding');
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $fp = $dir.DIRECTORY_SEPARATOR.'logo.'.$ext;
                    if (is_file($fp) && is_readable($fp)) {
                        $bin = @file_get_contents($fp);
                        if ($bin !== false) {
                            $logoBase64 = base64_encode($bin);
                            $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if (empty($logoBase64)) {
            try {
                $uploadsDisk = (string) config('filesystems.uploads_disk', 'public');
                $diskUploads = Storage::disk($uploadsDisk);
                foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                    $p = 'branding/logo.'.$ext;
                    if ($diskUploads->exists($p)) {
                        $bin = $diskUploads->get($p);
                        $logoBase64 = base64_encode($bin);
                        $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                        break;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        if (empty($logoBase64)) {
            try {
                foreach ([public_path('branding'), public_path()] as $dir) {
                    foreach (['png', 'jpg', 'jpeg', 'svg'] as $ext) {
                        $fp = $dir.DIRECTORY_SEPARATOR.'logo.'.$ext;
                        if (is_file($fp) && is_readable($fp)) {
                            $bin = @file_get_contents($fp);
                            if ($bin !== false) {
                                $logoBase64 = base64_encode($bin);
                                $logoMime = $ext === 'svg' ? 'image/svg+xml' : ('image/'.($ext === 'jpg' ? 'jpeg' : $ext));
                                break 2;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $header = (array) ($eco['header'] ?? []);
        $summaryBs = (array) ($eco['summary_bs'] ?? []);
        $summaryFx = (array) ($eco['summary_fx'] ?? []);
        $byLocal = (array) ($eco['by_local'] ?? []);
        $charges = (array) data_get($eco, 'tables.charges_open', []);

        $selectedSet = array_values(array_unique(array_filter(array_map(fn ($v) => is_numeric($v) ? (int) $v : 0, $selectedLocalIds))));
        $selectedMap = [];
        foreach ($selectedSet as $lid) {
            $selectedMap[$lid] = true;
        }

        $includedByLocal = [];
        $includedLocalCodes = [];
        foreach ($byLocal as $row) {
            $lid = (int) ($row['local_id'] ?? 0);
            if ($lid <= 0) {
                continue;
            }
            if (! empty($selectedMap)) {
                if (! isset($selectedMap[$lid])) {
                    continue;
                }
            } elseif ($scope === 'concessionaire') {
                $openBsMinor = (int) ($row['open_bs_minor'] ?? 0);
                if ($openBsMinor <= 0) {
                    continue;
                }
            }
            $includedByLocal[] = $row;
            $code = (string) ($row['local_code'] ?? '');
            if ($code !== '') {
                $includedLocalCodes[] = $code;
            }
        }

        $scopeLabel = $scope === 'local' ? 'LOCAL' : 'CONCESSIONAIRE';
        $filename = 'estado_cuenta_'.$scopeLabel.'_'.$id.'_'.$at->format('Ymd').'.pdf';

        $html = view('pdf.economic_profile_statement', [
            'header' => $header,
            'summary_bs' => $summaryBs,
            'summary_fx' => $summaryFx,
            'by_local' => $includedByLocal,
            'charges' => $charges,
            'at' => $at->toDateString(),
            'scope' => $scope,
            'scope_label' => $scopeLabel,
            'scope_id' => $id,
            'letterhead_base64' => $letterheadBase64,
            'letterhead_mime' => $letterheadMime,
            'logo_base64' => $logoBase64,
            'logo_mime' => $logoMime,
            'included_local_codes' => $includedLocalCodes,
        ])->render();

        $raw = null;
        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4');
            $raw = $pdf->output();
        } elseif (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();
            $raw = $dompdf->output();
        } else {
            throw new \RuntimeException('PDF library not installed.');
        }

        return [
            'raw' => (string) $raw,
            'filename' => $filename,
        ];
    }
}
