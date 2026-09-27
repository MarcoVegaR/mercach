<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DomainActionException;
use App\Models\Concessionaire;
use App\Services\Reports\DelinquencyReportQuery;
use App\Support\PdfAssetLoader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ConcessionaireCollectionNoticePdfGenerator
{
    public function __construct(
        private PdfAssetLoader $assetLoader,
        private DelinquencyReportQuery $delinquencyReportQuery,
    ) {}

    /**
     * @param  Collection<int, Concessionaire>  $concessionaires
     * @return array{raw:string, filename:string}
     */
    public function render(Collection $concessionaires, string $noticeType): array
    {
        $printedAt = Carbon::now((string) config('app.timezone', 'America/Caracas'));
        $localCodesByConcessionaire = $this->delinquencyReportQuery
            ->overdueLocalCodesForConcessionaires(array_map('intval', $concessionaires->modelKeys()));
        $withoutOverdueLocals = $concessionaires
            ->filter(fn (Concessionaire $concessionaire): bool => ($localCodesByConcessionaire[(int) $concessionaire->getKey()] ?? []) === [])
            ->pluck('full_name')
            ->all();

        if ($withoutOverdueLocals !== []) {
            throw new DomainActionException(
                'No se generaron los avisos. Los siguientes cesionarios no tienen puestos con deuda vencida cobrable: '.implode(', ', $withoutOverdueLocals).'.',
            );
        }

        $letterhead = $this->assetLoader->branding('letterhead');
        $logo = $this->assetLoader->branding('logo');
        $notices = $concessionaires->map(fn (Concessionaire $concessionaire): array => [
            'concessionaire' => $concessionaire,
            'local_codes' => $localCodesByConcessionaire[(int) $concessionaire->getKey()],
        ])->all();
        $html = view('pdf.concessionaire_collection_notice', [
            'notices' => $notices,
            'notice_type' => $noticeType,
            'printed_at' => $printedAt,
            'letterhead_base64' => $letterhead['base64'],
            'letterhead_mime' => $letterhead['mime'],
            'logo_base64' => $logo['base64'],
            'logo_mime' => $logo['mime'],
        ])->render();

        return [
            'raw' => $this->pdf($html),
            'filename' => 'avisos_cobro_'.$noticeType.'_'.$printedAt->format('Ymd').'.pdf',
        ];
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
