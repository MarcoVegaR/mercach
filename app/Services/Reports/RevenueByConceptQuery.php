<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Payment;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class RevenueByConceptQuery
{
    public const CONCEPTS = [
        'RENT_EUR_M2' => 'Tasa de uso por convenio',
        'CONDO_USD' => 'Gastos Comunes',
        'RENT_EUR_FIXED' => 'Alquiler fijo',
        'ADJ' => 'Gasto Fijo de Mantenimiento',
        'CESION_DERECHOS' => 'Cesión de derechos',
        'FINE' => 'Multa',
    ];

    /** @param array{from: string, to: string, concepts: list<string>} $filters */
    public function __construct(private array $filters) {}

    /** @return array<string, mixed> */
    public function data(): array
    {
        $summary = $this->summaries();
        $totalsRow = $this->grouped()->selectRaw('COUNT(DISTINCT payment_id) AS payment_count')
            ->selectRaw('COALESCE(SUM(amount_bs_minor), 0) AS amount_bs_minor')
            ->selectRaw('COALESCE(SUM(amount_usd_minor), 0) AS amount_usd_minor')
            ->selectRaw('COUNT(*) FILTER (WHERE amount_usd_minor IS NULL) AS missing_rate_count')->first();
        $totals = $this->amounts($totalsRow);
        $rows = $this->grouped()->orderBy('paid_on')->orderBy('payment_id')->orderBy('kind')->get()
            ->map(fn ($row): array => $this->detail($row))->all();

        return [
            'filters' => $this->filters,
            'rows' => $rows,
            'summary' => $summary->all(),
            'totals' => $totals,
            'reconciliation' => $this->filters['concepts'] === [] ? $this->reconciliation($totals['amount_bs_minor']) : null,
            'concepts_label' => collect(self::CONCEPTS)->only($this->filters['concepts'])->implode(', ') ?: 'Todos',
            'generated_at' => now()->format('d/m/Y H:i'),
        ];
    }

    /** @return array<string, mixed> */
    public static function options(): array
    {
        return [
            'conceptOptions' => collect(self::CONCEPTS)->map(fn (string $label, string $value): array => compact('value', 'label'))->values()->all(),
            'datePresets' => [
                ['value' => 'today', 'label' => 'Hoy', 'from' => now()->toDateString(), 'to' => now()->toDateString()],
                ['value' => 'yesterday', 'label' => 'Ayer', 'from' => now()->subDay()->toDateString(), 'to' => now()->subDay()->toDateString()],
                ['value' => 'week', 'label' => 'Esta semana', 'from' => now()->startOfWeek()->toDateString(), 'to' => now()->toDateString()],
                ['value' => 'month', 'label' => 'Este mes', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()],
            ],
        ];
    }

    private function payments(): Builder
    {
        return Payment::query()
            ->leftJoin('payment_statuses as ps', 'ps.id', '=', 'payments.payment_status_id')
            ->leftJoin('payment_types as pt', 'pt.id', '=', 'payments.payment_type_id')
            ->whereNull('payments.voided_at')
            ->whereBetween('payments.paid_on', [$this->filters['from'], $this->filters['to']])
            ->whereIn('ps.code', ['REG', 'CONF', 'CONC'])
            ->whereRaw("COALESCE(NULLIF(UPPER(TRIM(pt.code)), ''), UPPER(TRIM(payments.method)), '') <> ?", ['EXO'])
            ->toBase();
    }

    private function grouped(): Builder
    {
        $rate = "CASE WHEN fx.currency_code = 'USD' AND fx.rate_to_ves > 0 AND fx.value_date <= payments.paid_on THEN fx.rate_to_ves ELSE NULL END";
        $debtor = "COALESCE(con.full_name, NULLIF(TRIM(CONCAT(pl.code, ' ', pl.name)), ''), CONCAT(payments.debtor_type, ' #', payments.debtor_id))";

        $query = $this->payments()
            ->join('payment_allocations as pa', 'pa.payment_id', '=', 'payments.id')
            ->join('charges as c', 'c.id', '=', 'pa.charge_id')
            ->leftJoin('fx_rates as fx', 'fx.id', '=', 'payments.fx_rate_id')
            ->leftJoin('locals as l', 'l.id', '=', 'c.local_id')
            ->leftJoin('concessionaires as con', function ($join): void {
                $join->on('con.id', '=', 'payments.debtor_id')->where('payments.debtor_type', '=', 'CONCESSIONAIRE');
            })
            ->leftJoin('locals as pl', function ($join): void {
                $join->on('pl.id', '=', 'payments.debtor_id')->where('payments.debtor_type', '=', 'LOCAL');
            })
            ->whereNull('pa.deleted_at')->whereNull('c.deleted_at')
            ->select(['payments.id as payment_id', 'payments.paid_on', 'payments.reference', 'c.kind'])
            ->selectRaw($debtor.' AS debtor_name')
            ->selectRaw("STRING_AGG(DISTINCT l.code, ', ' ORDER BY l.code) AS locals")
            ->selectRaw($rate.' AS rate')
            ->selectRaw('SUM(pa.amount_bs_minor)::bigint AS amount_bs_minor')
            ->selectRaw('ROUND(SUM(pa.amount_bs_minor) / ('.$rate.'))::bigint AS amount_usd_minor')
            ->groupBy('payments.id', 'c.kind', 'fx.currency_code', 'fx.rate_to_ves', 'fx.value_date', 'con.full_name', 'pl.code', 'pl.name');

        if ($this->filters['concepts'] !== []) {
            $query->whereIn('c.kind', $this->filters['concepts']);
        }

        return $query->newQuery()->fromSub($query, 'applied');
    }

    /** @return Collection<int, non-empty-array<string, mixed>> */
    private function summaries(): Collection
    {
        return $this->grouped()->select('kind')->selectRaw('COUNT(DISTINCT payment_id) AS payment_count')
            ->selectRaw('SUM(amount_bs_minor) AS amount_bs_minor, SUM(amount_usd_minor) AS amount_usd_minor')
            ->selectRaw('COUNT(*) FILTER (WHERE amount_usd_minor IS NULL) AS missing_rate_count')
            ->groupBy('kind')->orderBy('kind')->get()
            ->map(fn ($row): array => array_merge($this->amounts($row), [
                'concept' => self::CONCEPTS[$row->kind] ?? $row->kind,
            ]));
    }

    /** @return array<string, int|null> */
    private function amounts(object $row): array
    {
        return [
            'payment_count' => (int) $row->payment_count,
            'amount_bs_minor' => (int) $row->amount_bs_minor,
            'amount_usd_minor' => (int) $row->missing_rate_count > 0 ? null : (int) $row->amount_usd_minor,
            'missing_rate_count' => (int) $row->missing_rate_count,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(object $row): array
    {
        return [
            'payment_id' => (int) $row->payment_id,
            'paid_on' => $row->paid_on,
            'reference' => $row->reference,
            'debtor_name' => $row->debtor_name,
            'locals' => $row->locals,
            'concept' => self::CONCEPTS[$row->kind] ?? $row->kind,
            'rate' => $row->rate === null ? null : (float) $row->rate,
            'amount_bs_minor' => (int) $row->amount_bs_minor,
            'amount_usd_minor' => $row->amount_usd_minor === null ? null : (int) $row->amount_usd_minor,
        ];
    }

    /** @return array<string, int> */
    private function reconciliation(int $applied): array
    {
        $row = $this->payments()->selectRaw('COUNT(*) AS payment_count, COALESCE(SUM(payments.amount_bs_minor), 0) AS received')->first();

        return [
            'payment_count' => (int) $row->payment_count,
            'received_bs_minor' => (int) $row->received,
            'applied_bs_minor' => $applied,
            'unattributed_bs_minor' => (int) $row->received - $applied,
        ];
    }
}
