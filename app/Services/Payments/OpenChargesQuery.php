<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\ChargeStatusCode;
use App\Enums\ContractStatusCode;
use App\Models\Charge;
use App\Models\Local;
use App\Models\PaymentAllocation;
use App\Support\FxConversionHelper;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Query builder para obtener cargos abiertos de un deudor.
 *
 * Extrae la lógica compleja de PaymentController::openCharges()
 * para mejorar testabilidad y reutilización.
 */
class OpenChargesQuery
{
    private string $debtorType;

    private int $debtorId;

    private Carbon $paidOn;

    private ?int $localId = null;

    private ?string $currency = null;

    private ?string $kind = null;

    private ?string $periodFrom = null;

    private ?string $periodTo = null;

    private bool $overdueOnly = false;

    private int $limit = 500;

    public function __construct(
        private FxConversionHelper $fxHelper,
    ) {}

    public function forDebtor(string $type, int $id): self
    {
        $this->debtorType = strtoupper($type);
        $this->debtorId = $id;

        return $this;
    }

    public function atDate(string|Carbon $paidOn): self
    {
        $this->paidOn = $paidOn instanceof Carbon ? $paidOn : Carbon::parse($paidOn);

        return $this;
    }

    public function filterLocal(?int $localId): self
    {
        $this->localId = $localId;

        return $this;
    }

    public function filterCurrency(?string $currency): self
    {
        $this->currency = $currency ? strtoupper($currency) : null;

        return $this;
    }

    public function filterKind(?string $kind): self
    {
        $this->kind = $kind ? strtoupper($kind) : null;

        return $this;
    }

    public function filterPeriod(?string $from, ?string $to): self
    {
        $this->periodFrom = $from;
        $this->periodTo = $to;

        return $this;
    }

    public function overdueOnly(bool $value = true): self
    {
        $this->overdueOnly = $value;

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Ejecutar la query y obtener los cargos con sus saldos.
     *
     * @return array{items: list<array<string, mixed>>, fx_rates: array<string, float|null>}
     */
    public function execute(): array
    {
        // Resolver dominio de locales para CONCESSIONAIRE
        // (can be empty if concessionaire has no active contracts, but we still query concessionaire-level charges)
        $localIds = $this->resolveLocalIds();

        // Construir query base
        $q = $this->buildBaseQuery($localIds);

        // Aplicar filtros opcionales
        $this->applyFilters($q);

        // Obtener cargos
        $charges = $q
            ->orderBy('period')
            ->limit($this->limit)
            ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on', 'local_id', 'kind']);

        if ($charges->isEmpty()) {
            return ['items' => [], 'fx_rates' => []];
        }

        // Calcular saldos y transformar
        $items = $this->transformCharges($charges);

        // Obtener tasas FX para sum-then-convert en frontend
        $fxRates = $this->getFxRates();

        return ['items' => $items, 'fx_rates' => $fxRates];
    }

    /**
     * Obtener tasas FX actuales para EUR y USD.
     *
     * @return array<string, float|null>
     */
    private function getFxRates(): array
    {
        $eurRate = $this->fxHelper->rateToVes('EUR', $this->paidOn);
        $usdRate = $this->fxHelper->rateToVes('USD', $this->paidOn);

        return [
            'EUR' => $eurRate,
            'USD' => $usdRate,
        ];
    }

    /**
     * Resolver IDs de locales para el deudor.
     *
     * @return list<int>
     */
    private function resolveLocalIds(): array
    {
        if ($this->debtorType !== 'CONCESSIONAIRE') {
            return [];
        }

        return DB::table('concessionaire_contract as cc')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_statuses as cs', 'cs.id', '=', 'c.contract_status_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->where('cc.concessionaire_id', $this->debtorId)
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereDate('c.start_date', '<=', $this->paidOn->toDateString())
            ->whereIn('cs.code', ContractStatusCode::activeForCharges())
            ->pluck('l.id')
            ->unique()
            ->values()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Construir query base según tipo de deudor.
     *
     * @param  list<int>  $localIds
     * @return \Illuminate\Database\Eloquent\Builder<Charge>
     */
    private function buildBaseQuery(array $localIds): \Illuminate\Database\Eloquent\Builder
    {
        if ($this->debtorType === 'CONCESSIONAIRE') {
            // Include both concessionaire-level AND local-level charges
            $q = Charge::query()->where(function ($query) use ($localIds) {
                // Concessionaire-level charges
                $query->where(function ($sub) {
                    $sub->where('debtor_type', 'CONCESSIONAIRE')
                        ->where('debtor_id', $this->debtorId);
                });
                // Local-level charges (if any locals found)
                if (! empty($localIds)) {
                    $query->orWhere(function ($sub) use ($localIds) {
                        $sub->where('debtor_type', 'LOCAL')
                            ->whereIn('debtor_id', $localIds);
                    });
                }
            });
        } else {
            $q = Charge::query()
                ->where('debtor_type', $this->debtorType)
                ->where('debtor_id', $this->debtorId);

            if ($this->localId !== null && $this->localId > 0) {
                $q->where('local_id', $this->localId);
            }
        }

        // Filtrar por estados cobrables
        $statusIds = ChargeStatusCode::collectableIds();
        if (! empty($statusIds)) {
            $q->whereIn('charge_status_id', $statusIds);
        }

        return $q;
    }

    /**
     * Aplicar filtros opcionales a la query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Charge>  $q
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $q): void
    {
        if ($this->currency !== null) {
            $q->where('currency', $this->currency);
        }

        if ($this->kind !== null) {
            $q->where('kind', $this->kind);
        }

        if ($this->periodFrom !== null) {
            $from = Carbon::createFromFormat('Y-m', $this->periodFrom)->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }

        if ($this->periodTo !== null) {
            $to = Carbon::createFromFormat('Y-m', $this->periodTo)->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }

        if ($this->overdueOnly) {
            $q->whereDate('due_on', '<', $this->paidOn->toDateString());
        }
    }

    /**
     * Transformar cargos a formato de respuesta con saldos calculados.
     *
     * @param  \Illuminate\Support\Collection<int, Charge>  $charges
     * @return list<array<string, mixed>>
     */
    private function transformCharges(\Illuminate\Support\Collection $charges): array
    {
        $chargeIds = $charges->pluck('id')->all();

        // Pre-cargar allocations
        $allocByCharge = PaymentAllocation::query()
            ->whereIn('charge_id', $chargeIds)
            ->selectRaw('charge_id, SUM(amount_bs_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v])
            ->all();

        // Calcular outstanding batch usando FxHelper
        $outstandingMap = $this->fxHelper->chargesOutstandingVesBatch($charges, $this->paidOn);

        // Pre-cargar labels de locales
        $localsLabels = $this->loadLocalLabels($charges);

        // Calcular applied en moneda original
        $appliedCurrencyMap = $this->calculateAppliedInCurrency($charges, $chargeIds);

        $items = [];
        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $currency = (string) ($charge->getAttribute('currency') ?? '');
            $amountMinor = (int) $charge->getAttribute('amount_minor');
            $amountBsMinor = $this->fxHelper->chargeBaselineVes($charge, $this->paidOn);
            $allocated = $allocByCharge[$cid] ?? 0;
            $outstandingBsMinor = $outstandingMap[$cid] ?? 0;

            // Outstanding en moneda original
            $appliedCcyMinor = $appliedCurrencyMap[$cid] ?? 0;
            $outstandingCcyMinor = max(0, $amountMinor - $appliedCcyMinor);

            $items[] = [
                'charge_id' => $cid,
                'local_id' => (int) ($charge->getAttribute('local_id') ?? 0),
                'local_label' => $localsLabels[(int) ($charge->getAttribute('local_id') ?? 0)] ?? null,
                'period' => (string) $charge->getAttribute('period'),
                'due_on' => (string) ($charge->getAttribute('due_on') ?? ''),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'amount_bs_minor' => $amountBsMinor,
                'allocated_bs_minor' => $allocated,
                'outstanding_bs_minor' => $outstandingBsMinor,
                'applied_currency_minor' => $appliedCcyMinor,
                'outstanding_currency_minor' => $outstandingCcyMinor,
                'fx_rate_id' => null, // Could be added if needed
                'rate_to_ves' => null,
                'kind' => (string) ($charge->getAttribute('kind') ?? ''),
            ];
        }

        return $items;
    }

    /**
     * Cargar labels de locales.
     *
     * @param  \Illuminate\Support\Collection<int, Charge>  $charges
     * @return array<int, string>
     */
    private function loadLocalLabels(\Illuminate\Support\Collection $charges): array
    {
        $localIds = $charges->pluck('local_id')->filter()->unique()->values()->all();

        if (empty($localIds)) {
            return [];
        }

        return Local::query()
            ->whereIn('id', $localIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id')
            ->map(function ($l) {
                $code = (string) ($l->getAttribute('code') ?? '');
                $name = (string) ($l->getAttribute('name') ?? '');
                $label = trim(($code ? $code.' • ' : '').$name);

                return $label !== '' ? $label : (string) $l->getAttribute('id');
            })
            ->all();
    }

    /**
     * Calcular monto aplicado en moneda original para cada cargo.
     *
     * @param  \Illuminate\Support\Collection<int, Charge>  $charges
     * @param  list<int>  $chargeIds
     * @return array<int, int>
     */
    private function calculateAppliedInCurrency(\Illuminate\Support\Collection $charges, array $chargeIds): array
    {
        // Pre-cargar allocation rows con payment dates
        $allocRows = PaymentAllocation::query()
            ->whereIn('charge_id', $chargeIds)
            ->leftJoin('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->get(['payment_allocations.charge_id', 'payment_allocations.amount_bs_minor', 'p.paid_on']);

        // Pre-cargar credit application rows
        $creditRows = \App\Models\CreditApplication::query()
            ->whereIn('charge_id', $chargeIds)
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);

        $appliedMap = [];

        foreach ($charges as $charge) {
            $cid = (int) $charge->getKey();
            $currency = strtoupper((string) $charge->getAttribute('currency'));
            $applied = 0;

            if (in_array($currency, ['USD', 'EUR'], true)) {
                // Convertir cada allocation de Bs a moneda original
                foreach ($allocRows as $row) {
                    if ((int) $row->getAttribute('charge_id') !== $cid) {
                        continue;
                    }

                    $amtBs = (int) ($row->getAttribute('amount_bs_minor') ?? 0);
                    $paidOnRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    $atDate = $paidOnRaw !== '' ? new \DateTimeImmutable($paidOnRaw) : $this->paidOn;

                    $converted = $this->fxHelper->fromVes($amtBs, $currency, $atDate);
                    if ($converted !== null) {
                        $applied += $converted;
                    }
                }

                // Convertir credits a moneda original
                foreach ($creditRows as $row) {
                    if ((int) $row->getAttribute('charge_id') !== $cid) {
                        continue;
                    }

                    $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                    if ($amtMinor <= 0) {
                        continue;
                    }

                    $paidOnRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    $atDate = $paidOnRaw !== '' ? new \DateTimeImmutable($paidOnRaw) : $this->paidOn;
                    $ccyCr = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));

                    // Credit to Bs first
                    $creditBs = $this->fxHelper->toVes($amtMinor, $ccyCr, $atDate);
                    if ($creditBs !== null) {
                        // Then Bs to charge currency
                        $converted = $this->fxHelper->fromVes($creditBs, $currency, $atDate);
                        if ($converted !== null) {
                            $applied += $converted;
                        }
                    }
                }
            } elseif ($currency === 'VES') {
                // VES: sum directly
                foreach ($allocRows as $row) {
                    if ((int) $row->getAttribute('charge_id') !== $cid) {
                        continue;
                    }
                    $applied += (int) ($row->getAttribute('amount_bs_minor') ?? 0);
                }

                foreach ($creditRows as $row) {
                    if ((int) $row->getAttribute('charge_id') !== $cid) {
                        continue;
                    }
                    $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                    if ($amtMinor > 0) {
                        $paidOnRaw = (string) ($row->getAttribute('paid_on') ?? '');
                        $atDate = $paidOnRaw !== '' ? new \DateTimeImmutable($paidOnRaw) : $this->paidOn;
                        $ccyCr = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
                        $creditBs = $this->fxHelper->toVes($amtMinor, $ccyCr, $atDate);
                        if ($creditBs !== null) {
                            $applied += $creditBs;
                        }
                    }
                }
            }

            $appliedMap[$cid] = $applied;
        }

        return $appliedMap;
    }
}
