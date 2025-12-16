<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Services\FxRateServiceInterface;
use App\Models\Charge;
use App\Models\CreditApplication;
use App\Models\PaymentAllocation;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Helper centralizado para conversiones de moneda usando tasas FX.
 *
 * Elimina duplicación de lógica de conversión en PaymentController,
 * PaymentService, ChargesOrchestrator, etc.
 */
class FxConversionHelper
{
    public function __construct(
        private FxRateServiceInterface $fxService,
    ) {}

    /**
     * Convertir monto de una moneda a VES en una fecha dada.
     *
     * Política global: las conversiones FX se **truncan** a 2 decimales (no se redondea)
     * para evitar discrepancias entre módulos (portal, admin, PDFs, reportes).
     *
     * @param  int  $amountMinor  Monto en unidades menores (centavos)
     * @param  string  $currency  Código de moneda (EUR, USD, VES)
     * @param  DateTimeInterface  $at  Fecha para resolver la tasa
     * @return int|null Monto en VES minor, o null si no hay tasa disponible
     */
    public function toVes(int $amountMinor, string $currency, DateTimeInterface $at): ?int
    {
        $currency = strtoupper($currency);

        if ($currency === 'VES') {
            return $amountMinor;
        }

        $rate = $this->fxService->resolveAt($currency, $at);
        $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;

        if ($rateToVes === null || $rateToVes <= 0) {
            return null;
        }

        // Integer math: amount (2dp) * rate (2dp) => 4dp, then truncate back to 2dp.
        // Use rate_minor first to avoid floating-point drift.
        $rateMinor = (int) round($rateToVes * 100);
        if ($rateMinor <= 0) {
            return null;
        }

        $prod = $amountMinor * $rateMinor;

        return (int) intdiv($prod, 100);
    }

    /**
     * Convertir monto de VES a otra moneda en una fecha dada.
     *
     * @param  int  $amountBsMinor  Monto en VES unidades menores
     * @param  string  $toCurrency  Código de moneda destino (EUR, USD)
     * @param  DateTimeInterface  $at  Fecha para resolver la tasa
     * @return int|null Monto en moneda destino minor, o null si no hay tasa
     */
    public function fromVes(int $amountBsMinor, string $toCurrency, DateTimeInterface $at): ?int
    {
        $toCurrency = strtoupper($toCurrency);

        if ($toCurrency === 'VES') {
            return $amountBsMinor;
        }

        $rate = $this->fxService->resolveAt($toCurrency, $at);
        $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;

        if ($rateToVes === null || $rateToVes <= 0) {
            return null;
        }

        // Integer math: Bs (2dp) / rate (2dp) => 4dp, then truncate back to 2dp
        $prod = (int) round(($amountBsMinor * 100) / $rateToVes);

        return (int) intdiv($prod, 100);
    }

    /**
     * Obtener el monto baseline en VES de un cargo.
     *
     * Usa amount_bs_minor_issued si existe, sino calcula dinámicamente.
     */
    public function chargeBaselineVes(Charge $charge, DateTimeInterface $at): ?int
    {
        $issued = $charge->getAttribute('amount_bs_minor_issued');
        if (is_numeric($issued)) {
            return (int) $issued;
        }

        $currency = (string) $charge->getAttribute('currency');
        $amountMinor = (int) $charge->getAttribute('amount_minor');

        return $this->toVes($amountMinor, $currency, $at);
    }

    /**
     * Calcular outstanding de un cargo en VES.
     *
     * @param  Charge  $charge  El cargo
     * @param  DateTimeInterface  $at  Fecha para conversiones FX
     * @param  int|null  $allocatedBsMinor  Total ya asignado (si no se provee, se calcula)
     * @param  int|null  $creditedBsMinor  Total de créditos aplicados (si no se provee, se calcula)
     */
    public function chargeOutstandingVes(
        Charge $charge,
        DateTimeInterface $at,
        ?int $allocatedBsMinor = null,
        ?int $creditedBsMinor = null,
    ): int {
        $baseline = $this->chargeBaselineVes($charge, $at);
        if ($baseline === null) {
            return 0;
        }

        $chargeId = (int) $charge->getKey();

        // Calcular allocations si no se provee
        if ($allocatedBsMinor === null) {
            $allocatedBsMinor = (int) PaymentAllocation::query()
                ->where('charge_id', $chargeId)
                ->sum('amount_bs_minor');
        }

        // Calcular credits si no se provee
        if ($creditedBsMinor === null) {
            $creditedBsMinor = $this->sumCreditApplicationsVes($chargeId, $at);
        }

        return max(0, $baseline - $allocatedBsMinor - $creditedBsMinor);
    }

    /**
     * Suma de credit applications convertidas a VES para un cargo.
     */
    public function sumCreditApplicationsVes(int $chargeId, DateTimeInterface $defaultAt): int
    {
        $rows = CreditApplication::query()
            ->where('charge_id', $chargeId)
            ->whereNull('credit_applications.deleted_at')
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);

        $total = 0;
        foreach ($rows as $row) {
            $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
            if ($amtMinor <= 0) {
                continue;
            }

            $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
            $at = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $defaultAt;
            $currency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));

            $ves = $this->toVes($amtMinor, $currency, $at);
            if ($ves !== null) {
                $total += $ves;
            }
        }

        return $total;
    }

    /**
     * Calcular outstanding de múltiples cargos en VES.
     *
     * Optimizado para evitar N+1 queries.
     *
     * @param  Collection<int, Charge>  $charges
     * @return array<int, int> Map de charge_id => outstanding_bs_minor
     */
    public function chargesOutstandingVesBatch(Collection $charges, DateTimeInterface $at): array
    {
        if ($charges->isEmpty()) {
            return [];
        }

        $chargeIds = $charges->pluck('id')->all();

        // Pre-cargar allocations por cargo
        $allocByCharge = PaymentAllocation::query()
            ->whereIn('charge_id', $chargeIds)
            ->selectRaw('charge_id, SUM(amount_bs_minor) as total')
            ->groupBy('charge_id')
            ->pluck('total', 'charge_id')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v])
            ->all();

        // Pre-cargar credit applications
        $creditRows = CreditApplication::query()
            ->whereIn('charge_id', $chargeIds)
            ->whereNull('credit_applications.deleted_at')
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);

        $creditByCharge = [];
        foreach ($creditRows as $row) {
            $cid = (int) $row->getAttribute('charge_id');
            $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
            if ($amtMinor <= 0) {
                continue;
            }

            $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
            $atRow = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $at;
            $currency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));

            $ves = $this->toVes($amtMinor, $currency, $atRow);
            if ($ves !== null) {
                $creditByCharge[$cid] = ($creditByCharge[$cid] ?? 0) + $ves;
            }
        }

        // Calcular outstanding para cada cargo
        $result = [];
        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $baseline = $this->chargeBaselineVes($charge, $at);
            if ($baseline === null) {
                $result[$cid] = 0;

                continue;
            }

            $allocated = $allocByCharge[$cid] ?? 0;
            $credited = $creditByCharge[$cid] ?? 0;
            $result[$cid] = max(0, $baseline - $allocated - $credited);
        }

        return $result;
    }

    /**
     * Obtener ID de tasa FX para una moneda y fecha.
     */
    public function rateId(string $currency, DateTimeInterface $at): ?int
    {
        if (strtoupper($currency) === 'VES') {
            return null;
        }

        $rate = $this->fxService->resolveAt($currency, $at);

        return $rate ? (int) $rate->getAttribute('id') : null;
    }

    /**
     * Obtener tasa rate_to_ves para una moneda y fecha.
     */
    public function rateToVes(string $currency, DateTimeInterface $at): ?float
    {
        if (strtoupper($currency) === 'VES') {
            return 1.0;
        }

        $rate = $this->fxService->resolveAt($currency, $at);

        return $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
    }
}
