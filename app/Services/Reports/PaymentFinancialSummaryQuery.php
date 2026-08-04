<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentFinancialSummaryQuery
{
    private string $reportType = 'income';

    private string $groupBy = 'day';

    private string $paidFrom;

    private string $paidTo;

    private ?string $method = null;

    private ?int $bankId = null;

    public function __construct()
    {
        $tz = (string) config('app.timezone', 'America/Caracas');
        $now = Carbon::now($tz);
        $this->paidFrom = $now->startOfMonth()->toDateString();
        $this->paidTo = $now->endOfMonth()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        $reportType = strtolower(trim((string) ($filters['report_type'] ?? 'income')));
        $this->reportType = in_array($reportType, ['income', 'exonerations'], true) ? $reportType : 'income';

        $groupBy = strtolower(trim((string) ($filters['group_by'] ?? 'day')));
        $this->groupBy = in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'day';

        $paidBetween = (array) ($filters['paid_between'] ?? []);
        $tz = (string) config('app.timezone', 'America/Caracas');
        $from = ! empty($paidBetween['from']) ? Carbon::parse((string) $paidBetween['from'], $tz)->toDateString() : $this->paidFrom;
        $to = ! empty($paidBetween['to']) ? Carbon::parse((string) $paidBetween['to'], $tz)->toDateString() : $this->paidTo;

        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        $this->paidFrom = $from;
        $this->paidTo = $to;

        $method = strtoupper(trim((string) ($filters['method'] ?? '')));
        $this->method = $method !== '' ? $method : null;

        $bankId = (int) ($filters['bank_id'] ?? 0);
        $this->bankId = $bankId > 0 ? $bankId : null;

        return $this;
    }

    /**
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->buildGroupedQuery()
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function grouped(int $limit = 5000): Collection
    {
        return $this->buildGroupedQuery()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function details(int $limit = 500): Collection
    {
        $methodExpression = $this->methodCodeExpression();

        return $this->buildBaseQuery()
            ->leftJoin('concessionaires as c', function ($join): void {
                $join->on('c.id', '=', 'p.debtor_id')
                    ->where('p.debtor_type', '=', 'CONCESSIONAIRE');
            })
            ->leftJoin('locals as l', function ($join): void {
                $join->on('l.id', '=', 'p.debtor_id')
                    ->where('p.debtor_type', '=', 'LOCAL');
            })
            ->select([
                'p.id',
                'p.paid_on',
                'p.reference',
                'p.amount_bs_minor',
                'p.exoneration_reason',
                'p.debtor_type',
                'p.debtor_id',
                'ps.code as status_code',
                'ps.name as status_name',
                'pt.name as method_name',
                'receiver_bank.id as receiver_bank_id',
                'receiver_bank.name as receiver_bank_name',
            ])
            ->selectRaw($methodExpression.' as method_code')
            ->selectRaw("COALESCE(c.full_name, NULLIF(TRIM(CONCAT(l.code, ' ', l.name)), ''), CONCAT(COALESCE(p.debtor_type, 'DEUDOR'), ' #', COALESCE(p.debtor_id::text, ''))) as debtor_name")
            ->orderBy('p.paid_on')
            ->orderBy('p.id')
            ->limit($limit)
            ->get()
            ->map(fn ($row): array => $this->transformDetailRow($row));
    }

    /**
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        $row = $this->buildBaseQuery()
            ->selectRaw('COUNT(*)::int as count')
            ->selectRaw('COALESCE(SUM(p.amount_bs_minor), 0)::bigint as amount_bs_minor')
            ->selectRaw('COALESCE(AVG(p.amount_bs_minor), 0)::numeric as average_bs_minor')
            ->first();

        $count = (int) ($row->count ?? 0);
        $amount = (int) ($row->amount_bs_minor ?? 0);

        return [
            'count' => $count,
            'amount_bs_minor' => $amount,
            'average_bs_minor' => $count > 0 ? (int) round($amount / $count) : 0,
            'status_breakdown' => $this->statusBreakdown()->all(),
            'method_breakdown' => $this->methodBreakdown()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function appliedFilters(): array
    {
        return [
            'report_type' => $this->reportType,
            'group_by' => $this->groupBy,
            'paid_from' => $this->paidFrom,
            'paid_to' => $this->paidTo,
            'method' => $this->method,
            'bank_id' => $this->bankId,
            'bank_name' => $this->receiverBankName(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, \stdClass>|Collection<int, \stdClass>  $results
     * @return Collection<int, array<string, mixed>>
     */
    public function transform(LengthAwarePaginator|Collection $results): Collection
    {
        $collection = $results instanceof LengthAwarePaginator
            ? collect($results->items())
            : $results;

        return $collection->map(fn ($row): array => $this->transformGroupedRow($row))->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function dataForPdf(int $detailLimit = 500): array
    {
        $totals = $this->totals();

        return [
            'filters' => $this->appliedFilters(),
            'rows' => $this->transform($this->grouped())->all(),
            'totals' => $totals,
            'details' => $this->details($detailLimit)->all(),
            'detail_limit' => $detailLimit,
            'details_truncated' => (int) ($totals['count'] ?? 0) > $detailLimit,
            'generated_at' => Carbon::now((string) config('app.timezone', 'America/Caracas'))->toDateTimeString(),
        ];
    }

    private function buildGroupedQuery(): Builder
    {
        $bucketExpression = $this->bucketExpression();
        $keyExpression = $this->periodKeyExpression();
        $labelExpression = $this->periodLabelExpression();

        return $this->buildBaseQuery()
            ->selectRaw($bucketExpression.' as period_start')
            ->selectRaw($keyExpression.' as period_key')
            ->selectRaw($labelExpression.' as period_label')
            ->selectRaw('COUNT(*)::int as count')
            ->selectRaw('COALESCE(SUM(p.amount_bs_minor), 0)::bigint as amount_bs_minor')
            ->selectRaw("SUM(CASE WHEN ps.code = 'REG' THEN 1 ELSE 0 END)::int as registered_count")
            ->selectRaw("SUM(CASE WHEN ps.code = 'CONF' THEN 1 ELSE 0 END)::int as confirmed_count")
            ->selectRaw("SUM(CASE WHEN ps.code = 'CONC' THEN 1 ELSE 0 END)::int as applied_count")
            ->groupByRaw($bucketExpression.', '.$keyExpression.', '.$labelExpression)
            ->orderByRaw($bucketExpression.' asc');
    }

    private function buildBaseQuery(): Builder
    {
        $methodExpression = $this->methodCodeExpression();
        $statusExpression = $this->statusCodeExpression();

        $query = DB::table('payments as p')
            ->leftJoin('payment_types as pt', 'pt.id', '=', 'p.payment_type_id')
            ->leftJoin('payment_statuses as ps', 'ps.id', '=', 'p.payment_status_id')
            ->leftJoin('company_bank_accounts as cba', 'cba.id', '=', 'p.company_bank_account_id')
            ->leftJoin('banks as receiver_bank', 'receiver_bank.id', '=', 'cba.bank_id')
            ->whereNull('p.deleted_at')
            ->whereNull('p.voided_at')
            ->whereDate('p.paid_on', '>=', $this->paidFrom)
            ->whereDate('p.paid_on', '<=', $this->paidTo)
            ->where(function (Builder $where) use ($statusExpression): void {
                $where->whereRaw($statusExpression.' = ?', [''])
                    ->orWhereRaw($statusExpression.' <> ?', ['VOID']);
            });

        if ($this->bankId !== null) {
            $query->where('receiver_bank.id', $this->bankId);
        }

        if ($this->reportType === 'exonerations') {
            return $query->whereRaw($methodExpression.' = ?', ['EXO']);
        }

        $query->whereRaw($methodExpression.' <> ?', ['EXO']);

        if ($this->method !== null) {
            $query->whereRaw($methodExpression.' = ?', [$this->method]);
        }

        return $query;
    }

    /**
     * @return Collection<int, array{code: string, name: string, count: int, amount_bs_minor: int}>
     */
    private function statusBreakdown(): Collection
    {
        $statusExpression = $this->statusCodeExpression();

        return $this->buildBaseQuery()
            ->selectRaw($statusExpression.' as code')
            ->selectRaw("COALESCE(ps.name, 'Sin estado') as name")
            ->selectRaw('COUNT(*)::int as count')
            ->selectRaw('COALESCE(SUM(p.amount_bs_minor), 0)::bigint as amount_bs_minor')
            ->groupByRaw($statusExpression.', ps.name')
            ->orderBy('code')
            ->get()
            ->map(fn ($row): array => [
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? 'Sin estado'),
                'count' => (int) ($row->count ?? 0),
                'amount_bs_minor' => (int) ($row->amount_bs_minor ?? 0),
            ]);
    }

    /**
     * @return Collection<int, array{code: string, name: string, count: int, amount_bs_minor: int}>
     */
    private function methodBreakdown(): Collection
    {
        $methodExpression = $this->methodCodeExpression();
        $methodNameExpression = "COALESCE(NULLIF(TRIM(pt.name), ''), ".$methodExpression.", 'N/A')";

        return $this->buildBaseQuery()
            ->selectRaw($methodExpression.' as code')
            ->selectRaw($methodNameExpression.' as name')
            ->selectRaw('COUNT(*)::int as count')
            ->selectRaw('COALESCE(SUM(p.amount_bs_minor), 0)::bigint as amount_bs_minor')
            ->groupByRaw($methodExpression.', '.$methodNameExpression)
            ->orderByRaw('COALESCE(SUM(p.amount_bs_minor), 0) desc')
            ->get()
            ->map(fn ($row): array => [
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? 'N/A'),
                'count' => (int) ($row->count ?? 0),
                'amount_bs_minor' => (int) ($row->amount_bs_minor ?? 0),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformGroupedRow(object $row): array
    {
        $amount = (int) ($row->amount_bs_minor ?? 0);
        $count = (int) ($row->count ?? 0);

        return [
            'period_start' => (string) ($row->period_start ?? ''),
            'period_key' => (string) ($row->period_key ?? ''),
            'period_label' => (string) ($row->period_label ?? ''),
            'count' => $count,
            'amount_bs_minor' => $amount,
            'average_bs_minor' => $count > 0 ? (int) round($amount / $count) : 0,
            'registered_count' => (int) ($row->registered_count ?? 0),
            'confirmed_count' => (int) ($row->confirmed_count ?? 0),
            'applied_count' => (int) ($row->applied_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformDetailRow(object $row): array
    {
        return [
            'id' => (int) ($row->id ?? 0),
            'paid_on' => (string) ($row->paid_on ?? ''),
            'reference' => (string) ($row->reference ?? ''),
            'amount_bs_minor' => (int) ($row->amount_bs_minor ?? 0),
            'method_code' => (string) ($row->method_code ?? ''),
            'method_name' => (string) ($row->method_name ?? ''),
            'status_code' => (string) ($row->status_code ?? ''),
            'status_name' => (string) ($row->status_name ?? ''),
            'debtor_name' => (string) ($row->debtor_name ?? ''),
            'receiver_bank_id' => (int) ($row->receiver_bank_id ?? 0),
            'receiver_bank_name' => (string) ($row->receiver_bank_name ?? 'Sin banco receptor'),
            'exoneration_reason' => (string) ($row->exoneration_reason ?? ''),
        ];
    }

    private function methodCodeExpression(): string
    {
        return "COALESCE(NULLIF(UPPER(TRIM(pt.code)), ''), NULLIF(UPPER(TRIM(p.method)), ''), '')";
    }

    private function statusCodeExpression(): string
    {
        return "COALESCE(NULLIF(UPPER(TRIM(ps.code)), ''), '')";
    }

    private function receiverBankName(): ?string
    {
        if ($this->bankId === null) {
            return null;
        }

        $name = DB::table('banks')
            ->where('id', $this->bankId)
            ->value('name');

        return $name !== null ? (string) $name : null;
    }

    private function bucketExpression(): string
    {
        return match ($this->groupBy) {
            'week' => "DATE_TRUNC('week', p.paid_on)::date",
            'month' => "DATE_TRUNC('month', p.paid_on)::date",
            default => 'p.paid_on::date',
        };
    }

    private function periodKeyExpression(): string
    {
        return match ($this->groupBy) {
            'week' => "TO_CHAR(DATE_TRUNC('week', p.paid_on)::date, 'YYYY-MM-DD')",
            'month' => "TO_CHAR(DATE_TRUNC('month', p.paid_on)::date, 'YYYY-MM')",
            default => "TO_CHAR(p.paid_on, 'YYYY-MM-DD')",
        };
    }

    private function periodLabelExpression(): string
    {
        return match ($this->groupBy) {
            'week' => "TO_CHAR(DATE_TRUNC('week', p.paid_on)::date, 'DD/MM/YYYY') || ' - ' || TO_CHAR((DATE_TRUNC('week', p.paid_on)::date + INTERVAL '6 days')::date, 'DD/MM/YYYY')",
            'month' => "TO_CHAR(DATE_TRUNC('month', p.paid_on)::date, 'MM/YYYY')",
            default => "TO_CHAR(p.paid_on, 'DD/MM/YYYY')",
        };
    }
}
