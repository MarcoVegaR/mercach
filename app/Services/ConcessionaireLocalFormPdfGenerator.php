<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChargeStatusCode;
use App\Exceptions\DomainActionException;
use App\Models\Charge;
use App\Models\Concessionaire;
use App\Models\Local;
use App\Support\FxConversionHelper;
use App\Support\PdfAssetLoader;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ConcessionaireLocalFormPdfGenerator
{
    public function __construct(
        private PdfAssetLoader $assetLoader,
        private FxConversionHelper $fxHelper,
    ) {}

    /** @return array{raw:string, filename:string} */
    public function render(Concessionaire $concessionaire, Local $local, string $formType): array
    {
        $printedAt = Carbon::now((string) config('app.timezone', 'America/Caracas'));

        if ($formType === 'solvency' && ! $this->solvencyEligibility(new Collection([$local]))[0]['can_issue_solvency']) {
            throw new DomainActionException('No se puede emitir la solvencia: el local '.$local->code.' tiene deuda vencida cobrable pendiente.');
        }

        $concessionaire->loadMissing(['documentType', 'phoneAreaCode']);
        $letterhead = $this->assetLoader->branding('letterhead');
        $logo = $this->assetLoader->branding('logo');
        $html = view('pdf.concessionaire_local_form', [
            'concessionaire' => $concessionaire,
            'local' => $local,
            'form_type' => $formType,
            'printed_at' => $printedAt,
            'letterhead_base64' => $letterhead['base64'],
            'letterhead_mime' => $letterhead['mime'],
            'logo_base64' => $logo['base64'],
            'logo_mime' => $logo['mime'],
        ])->render();

        return [
            'raw' => $this->pdf($html),
            'filename' => ($formType === 'solvency' ? 'solvencia' : 'renuncia').'_local_'.$local->getKey().'_'.$printedAt->format('Ymd').'.pdf',
        ];
    }

    /**
     * @param  Collection<int, Local>  $locals
     * @return list<array{code:string,can_issue_solvency:bool}>
     */
    public function solvencyEligibility(Collection $locals): array
    {
        $localIds = $locals->modelKeys();
        $at = Carbon::now((string) config('app.timezone', 'America/Caracas'));
        $charges = Charge::query()
            ->collectible()
            ->where(function ($query) use ($localIds): void {
                $query->whereIn('local_id', $localIds)
                    ->orWhere(function ($query) use ($localIds): void {
                        $query->where('debtor_type', 'LOCAL')->whereIn('debtor_id', $localIds);
                    });
            })
            ->whereIn('charge_status_id', ChargeStatusCode::collectableIds())
            ->whereDate('due_on', '<=', $at->toDateString())
            ->get();
        $balances = $this->fxHelper->chargesOutstandingCurrencyMinorBatch($charges, $at);
        $blocked = [];

        foreach ($charges as $charge) {
            if (($balances[$charge->getKey()] ?? $charge->amount_minor) > 0) {
                if ($charge->local_id !== null) {
                    $blocked[(int) $charge->local_id] = true;
                }
                if ($charge->debtor_type === 'LOCAL') {
                    $blocked[(int) $charge->debtor_id] = true;
                }
            }
        }

        return $locals->map(fn (Local $local): array => [
            'code' => (string) $local->code,
            'can_issue_solvency' => ! isset($blocked[(int) $local->getKey()]),
        ])->values()->all();
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
