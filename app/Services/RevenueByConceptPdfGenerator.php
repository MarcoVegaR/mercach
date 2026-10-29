<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PdfAssetLoader;
use Barryvdh\DomPDF\Facade\Pdf;

class RevenueByConceptPdfGenerator
{
    public function __construct(private PdfAssetLoader $assets) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{raw: string, filename: string}
     */
    public function render(array $data): array
    {
        $pdf = Pdf::loadView('pdf.revenue_by_concept', [
            'data' => $data,
            'logo' => $this->assets->branding('logo'),
            'letterhead' => $this->assets->branding('letterhead'),
        ])->setPaper('A4', 'landscape');

        return [
            'raw' => (string) $pdf->output(),
            'filename' => 'recaudacion_por_concepto_'.$data['filters']['from'].'_'.$data['filters']['to'].'.pdf',
        ];
    }
}
