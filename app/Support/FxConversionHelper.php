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
     * Política global: las conversiones FX se **redondean** a 2 decimales (una sola vez)
     * siguiendo lineamientos del BCV sobre redondear basándose en el tercer decimal.
     * Esto evita discrepancias acumulativas cuando se suman múltiples conversiones.
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

        // Integer math: amount (2dp) * rate (2dp) => 4dp, then round back to 2dp.
        // Use rate_minor first to avoid floating-point drift.
        $rateMinor = (int) round($rateToVes * 100);
        if ($rateMinor <= 0) {
            return null;
        }

        $prod = $amountMinor * $rateMinor;

        return (int) round($prod / 100);
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

        // Integer math: Bs (2dp) / rate (2dp) => 4dp, then round back to 2dp
        $prod = (int) round(($amountBsMinor * 100) / $rateToVes);

        return (int) round($prod / 100);
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
     * IMPORTANTE: El outstanding se calcula en la moneda original del cargo,
     * luego se convierte a VES con la tasa de hoy. Esto evita discrepancias
     * cuando la tasa FX cambia entre el momento del pago y hoy.
     *
     * @param  Charge  $charge  El cargo
     * @param  DateTimeInterface  $at  Fecha para conversiones FX
     * @param  int|null  $allocatedBsMinor  DEPRECATED: ignorado, se calcula internamente
     * @param  int|null  $creditedBsMinor  DEPRECATED: ignorado, se calcula internamente
     */
    public function chargeOutstandingVes(
        Charge $charge,
        DateTimeInterface $at,
        ?int $allocatedBsMinor = null,
        ?int $creditedBsMinor = null,
    ): int {
        $chargeId = (int) $charge->getKey();
        $currency = strtoupper((string) $charge->getAttribute('currency'));
        $amountMinor = (int) $charge->getAttribute('amount_minor');

        // Si es VES, usar lógica simple (no hay conversión FX)
        if ($currency === 'VES') {
            $allocated = (int) PaymentAllocation::query()
                ->where('charge_id', $chargeId)
                ->whereNull('payment_allocations.deleted_at')
                ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
                ->whereNull('p.deleted_at')
                ->whereNull('p.voided_at')
                ->sum('payment_allocations.amount_bs_minor');
            $credited = $this->sumCreditApplicationsVes($chargeId, $at);

            return max(0, $amountMinor - $allocated - $credited);
        }

        // Para monedas extranjeras: calcular outstanding en moneda original,
        // luego convertir a VES con tasa de hoy
        $allocatedCurrencyMinor = $this->sumAllocationsInCurrency($chargeId, $currency);
        $creditedCurrencyMinor = $this->sumCreditsInCurrency($chargeId, $currency, $at);

        $outstandingCurrencyMinor = max(0, $amountMinor - $allocatedCurrencyMinor - $creditedCurrencyMinor);

        if ($outstandingCurrencyMinor === 0) {
            return 0;
        }

        // Convertir outstanding a VES con tasa de hoy
        return $this->toVes($outstandingCurrencyMinor, $currency, $at) ?? 0;
    }

    /**
     * Suma de allocations convertidas a la moneda del cargo.
     *
     * Cada allocation se convierte de Bs a la moneda original usando
     * la tasa FX del momento del pago.
     */
    public function sumAllocationsInCurrency(int $chargeId, string $currency): int
    {
        $rows = PaymentAllocation::query()
            ->where('payment_allocations.charge_id', $chargeId)
            ->whereNull('payment_allocations.deleted_at')
            ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->get(['payment_allocations.amount_bs_minor', 'p.paid_on']);

        $total = 0;
        foreach ($rows as $row) {
            $bsMinor = (int) ($row->getAttribute('amount_bs_minor') ?? 0);
            if ($bsMinor <= 0) {
                continue;
            }

            $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
            if ($paidRaw === '') {
                continue;
            }

            $paidAt = new \DateTimeImmutable($paidRaw);
            $currencyMinor = $this->fromVes($bsMinor, $currency, $paidAt);
            if ($currencyMinor !== null) {
                $total += $currencyMinor;
            }
        }

        return $total;
    }

    /**
     * Suma de credit applications convertidas a la moneda del cargo.
     */
    public function sumCreditsInCurrency(int $chargeId, string $targetCurrency, DateTimeInterface $defaultAt): int
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

            $creditCurrency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
            $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
            $paidAt = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $defaultAt;

            // Si el crédito está en la misma moneda, sumar directo
            if ($creditCurrency === $targetCurrency) {
                $total += $amtMinor;

                continue;
            }

            // Convertir: credit currency -> VES -> target currency
            $ves = $this->toVes($amtMinor, $creditCurrency, $paidAt);
            if ($ves !== null) {
                $converted = $this->fromVes($ves, $targetCurrency, $paidAt);
                if ($converted !== null) {
                    $total += $converted;
                }
            }
        }

        return $total;
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
     * IMPORTANTE: Usa el enfoque "sum-then-convert" para evitar discrepancias
     * de redondeo. Los outstanding se calculan en moneda original, se suman
     * por moneda, se convierten una vez a VES, y luego se distribuye cualquier
     * diferencia de redondeo entre los cargos del grupo.
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

        // Pre-cargar allocations con fecha de pago para conversión correcta
        $allocRows = PaymentAllocation::query()
            ->whereIn('payment_allocations.charge_id', $chargeIds)
            ->whereNull('payment_allocations.deleted_at')
            ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->get(['payment_allocations.charge_id', 'payment_allocations.amount_bs_minor', 'p.paid_on']);

        // Pre-cargar credit applications
        $creditRows = CreditApplication::query()
            ->whereIn('charge_id', $chargeIds)
            ->whereNull('credit_applications.deleted_at')
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);

        // Calcular outstanding en moneda original para cada cargo
        $result = [];
        $outstandingByCurrency = []; // currency => [cid => outstandingCurrencyMinor]

        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $currency = strtoupper((string) $charge->getAttribute('currency'));
            $amountMinor = (int) $charge->getAttribute('amount_minor');

            // Si es VES, usar lógica simple (sin ajuste FX)
            if ($currency === 'VES') {
                $allocated = $allocRows->where('charge_id', $cid)->sum('amount_bs_minor');
                $credited = 0;
                foreach ($creditRows->where('charge_id', $cid) as $row) {
                    $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                    $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    $atRow = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $at;
                    $creditCurrency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
                    $ves = $this->toVes($amtMinor, $creditCurrency, $atRow);
                    if ($ves !== null) {
                        $credited += $ves;
                    }
                }
                $result[$cid] = max(0, $amountMinor - (int) $allocated - $credited);

                continue;
            }

            // Para monedas extranjeras: convertir allocations a moneda original
            $allocatedCurrency = 0;
            foreach ($allocRows->where('charge_id', $cid) as $row) {
                $bsMinor = (int) ($row->getAttribute('amount_bs_minor') ?? 0);
                $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                if ($bsMinor > 0 && $paidRaw !== '') {
                    $paidAt = new \DateTimeImmutable($paidRaw);
                    $converted = $this->fromVes($bsMinor, $currency, $paidAt);
                    if ($converted !== null) {
                        $allocatedCurrency += $converted;
                    }
                }
            }

            // Convertir credits a moneda original
            $creditedCurrency = 0;
            foreach ($creditRows->where('charge_id', $cid) as $row) {
                $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                if ($amtMinor <= 0) {
                    continue;
                }
                $creditCurrency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
                $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                $paidAt = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $at;

                if ($creditCurrency === $currency) {
                    $creditedCurrency += $amtMinor;
                } else {
                    $ves = $this->toVes($amtMinor, $creditCurrency, $paidAt);
                    if ($ves !== null) {
                        $converted = $this->fromVes($ves, $currency, $paidAt);
                        if ($converted !== null) {
                            $creditedCurrency += $converted;
                        }
                    }
                }
            }

            $outstandingCurrency = max(0, $amountMinor - $allocatedCurrency - $creditedCurrency);

            // Guardar outstanding en moneda original para ajuste posterior
            if (! isset($outstandingByCurrency[$currency])) {
                $outstandingByCurrency[$currency] = [];
            }
            $outstandingByCurrency[$currency][$cid] = $outstandingCurrency;

            // Conversión individual (será ajustada después)
            if ($outstandingCurrency === 0) {
                $result[$cid] = 0;
            } else {
                $result[$cid] = $this->toVes($outstandingCurrency, $currency, $at) ?? 0;
            }
        }

        // Ajustar para que la suma de individuales = sum-then-convert total por moneda
        foreach ($outstandingByCurrency as $currency => $chargeOutstandings) {
            $nonZeroCharges = array_filter($chargeOutstandings, fn ($v) => $v > 0);
            if (empty($nonZeroCharges)) {
                continue;
            }

            // Suma en moneda original
            $sumCurrency = array_sum($nonZeroCharges);

            // Total via sum-then-convert (una sola conversión)
            $totalSumThenConvert = $this->toVes($sumCurrency, $currency, $at) ?? 0;

            // Total via convert-then-sum (suma de conversiones individuales)
            $totalConvertThenSum = 0;
            foreach ($nonZeroCharges as $cid => $outCurrency) {
                $totalConvertThenSum += $result[$cid];
            }

            // Calcular diferencia
            $diff = $totalSumThenConvert - $totalConvertThenSum;

            // Distribuir diferencia entre los cargos con outstanding > 0
            // Ordenar por charge_id para garantizar distribución determinística
            if ($diff !== 0) {
                $chargeIds = array_keys($nonZeroCharges);
                sort($chargeIds, SORT_NUMERIC); // Orden determinístico por ID
                $count = count($chargeIds);
                $perCharge = (int) ($diff / $count);
                $remainder = $diff - ($perCharge * $count);

                foreach ($chargeIds as $i => $cid) {
                    $result[$cid] += $perCharge;
                    // Distribuir el resto uno a uno al primer cargo (ID más bajo)
                    if ($i < abs($remainder)) {
                        $result[$cid] += ($remainder > 0) ? 1 : -1;
                    }
                }
            }
        }

        return $result;
    }

    public function chargesOutstandingCurrencyMinorBatch(Collection $charges, DateTimeInterface $at): array
    {
        if ($charges->isEmpty()) {
            return [];
        }

        $chargeIds = $charges->pluck('id')->all();

        $allocRows = PaymentAllocation::query()
            ->whereIn('payment_allocations.charge_id', $chargeIds)
            ->whereNull('payment_allocations.deleted_at')
            ->join('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->get(['payment_allocations.charge_id', 'payment_allocations.amount_bs_minor', 'p.paid_on']);

        $creditRows = CreditApplication::query()
            ->whereIn('charge_id', $chargeIds)
            ->whereNull('credit_applications.deleted_at')
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);

        $result = [];
        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $currency = strtoupper((string) $charge->getAttribute('currency'));
            $amountMinor = (int) $charge->getAttribute('amount_minor');

            if ($currency === 'VES') {
                $allocated = $allocRows->where('charge_id', $cid)->sum('amount_bs_minor');
                $credited = 0;
                foreach ($creditRows->where('charge_id', $cid) as $row) {
                    $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                    $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    $atRow = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $at;
                    $creditCurrency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
                    $ves = $this->toVes($amtMinor, $creditCurrency, $atRow);
                    if ($ves !== null) {
                        $credited += $ves;
                    }
                }
                $result[$cid] = max(0, $amountMinor - (int) $allocated - $credited);

                continue;
            }

            $allocatedCurrency = 0;
            foreach ($allocRows->where('charge_id', $cid) as $row) {
                $bsMinor = (int) ($row->getAttribute('amount_bs_minor') ?? 0);
                $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                if ($bsMinor > 0 && $paidRaw !== '') {
                    $paidAt = new \DateTimeImmutable($paidRaw);
                    $converted = $this->fromVes($bsMinor, $currency, $paidAt);
                    if ($converted !== null) {
                        $allocatedCurrency += $converted;
                    }
                }
            }

            $creditedCurrency = 0;
            foreach ($creditRows->where('charge_id', $cid) as $row) {
                $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                if ($amtMinor <= 0) {
                    continue;
                }
                $creditCurrency = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
                $paidRaw = (string) ($row->getAttribute('paid_on') ?? '');
                $paidAt = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $at;

                if ($creditCurrency === $currency) {
                    $creditedCurrency += $amtMinor;
                } else {
                    $ves = $this->toVes($amtMinor, $creditCurrency, $paidAt);
                    if ($ves !== null) {
                        $converted = $this->fromVes($ves, $currency, $paidAt);
                        if ($converted !== null) {
                            $creditedCurrency += $converted;
                        }
                    }
                }
            }

            $result[$cid] = max(0, $amountMinor - $allocatedCurrency - $creditedCurrency);
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
