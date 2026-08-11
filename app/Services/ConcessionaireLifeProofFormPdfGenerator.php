<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Concessionaire;
use App\Models\LifeProofSequence;
use App\Support\PdfAssetLoader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConcessionaireLifeProofFormPdfGenerator
{
    public function __construct(private PdfAssetLoader $assetLoader) {}

    /**
     * @param  Collection<int, Concessionaire>  $concessionaires
     * @return array{raw:string, filename:string}
     */
    public function render(Collection $concessionaires): array
    {
        $numbers = $this->reserveNumbers($concessionaires->count());
        $letterhead = $this->assetLoader->branding('letterhead');
        $logo = $this->assetLoader->branding('logo');
        $printedAt = Carbon::now((string) config('app.timezone', 'America/Caracas'));

        $forms = $concessionaires->values()->map(function (Concessionaire $concessionaire, int $index) use ($numbers): array {
            return [
                'concessionaire' => $concessionaire,
                'number' => str_pad((string) $numbers[$index], 6, '0', STR_PAD_LEFT),
                'photo' => $this->assetLoader->uploadedImage((string) ($concessionaire->getAttribute('photo_path') ?? '')),
            ];
        })->all();

        $html = view('pdf.concessionaire_life_proof_form', [
            'forms' => $forms,
            'printed_at' => $printedAt,
            'letterhead_base64' => $letterhead['base64'],
            'letterhead_mime' => $letterhead['mime'],
            'logo_base64' => $logo['base64'],
            'logo_mime' => $logo['mime'],
        ])->render();

        return [
            'raw' => $this->pdf($html),
            'filename' => 'planillas_fe_de_vida_'.str_pad((string) $numbers[0], 6, '0', STR_PAD_LEFT).'_'.str_pad((string) end($numbers), 6, '0', STR_PAD_LEFT).'_'.$printedAt->format('Ymd').'.pdf',
        ];
    }

    /** @return list<int> */
    private function reserveNumbers(int $count): array
    {
        return DB::transaction(function () use ($count): array {
            $sequence = LifeProofSequence::query()
                ->where('key', 'concessionaire-form')
                ->lockForUpdate()
                ->firstOrFail();
            $first = (int) $sequence->getAttribute('next_number');
            $sequence->update(['next_number' => $first + $count]);

            return range($first, $first + $count - 1);
        }, attempts: 5);
    }

    private function pdf(string $html): string
    {
        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            return (string) \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('A4')->output();
        }

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf;
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4');
            $dompdf->render();

            return (string) $dompdf->output();
        }

        throw new \RuntimeException('PDF library not installed.');
    }
}
