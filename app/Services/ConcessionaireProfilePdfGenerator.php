<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Concessionaire;
use App\Support\PdfAssetLoader;
use Illuminate\Support\Carbon;

class ConcessionaireProfilePdfGenerator
{
    public function __construct(private PdfAssetLoader $assetLoader) {}

    /** @return array{raw:string, filename:string} */
    public function render(Concessionaire $concessionaire): array
    {
        $letterhead = $this->assetLoader->branding('letterhead');
        $logo = $this->assetLoader->branding('logo');
        $photo = $this->assetLoader->uploadedImage((string) ($concessionaire->getAttribute('photo_path') ?? ''));
        $printedAt = Carbon::now((string) config('app.timezone', 'America/Caracas'));

        $html = view('pdf.concessionaire_profile', [
            'concessionaire' => $concessionaire,
            'printed_at' => $printedAt,
            'photo_base64' => $photo['base64'],
            'photo_mime' => $photo['mime'],
            'letterhead_base64' => $letterhead['base64'],
            'letterhead_mime' => $letterhead['mime'],
            'logo_base64' => $logo['base64'],
            'logo_mime' => $logo['mime'],
        ])->render();

        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $raw = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4')->output();
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
            'filename' => 'ficha_cesionario_'.$concessionaire->getKey().'_'.$printedAt->format('Ymd').'.pdf',
        ];
    }
}
