<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DelinquencyReportQuery
{
    private string $scope = 'concessionaire';

    private string $debtType = 'overdue';

    private Carbon $cutoffAt;

    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $cachedRows = null;

    public function __construct(?Carbon $cutoffAt = null)
    {
        $timezone = (string) config('app.timezone', 'America/Caracas');
        $this->cutoffAt = ($cutoffAt ?? Carbon::now($timezone))->setTimezone($timezone);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        $scope = strtolower(trim((string) ($filters['scope'] ?? 'concessionaire')));
        $this->scope = in_array($scope, ['concessionaire', 'local'], true) ? $scope : 'concessionaire';

        $debtType = strtolower(trim((string) ($filters['debt_type'] ?? 'overdue')));
        $this->debtType = in_array($debtType, ['overdue', 'current'], true) ? $debtType : 'overdue';

        $this->cachedRows = null;

        return $this;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $rows = $this->rows();
        $page = max(1, $page);
        $perPage = min(max($perPage, 10), 200);
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        if ($page > $lastPage) {
            $page = $lastPage;
        }

        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => request()->url(),
                'pageName' => 'page',
            ],
        );
    }

    /**
     * @return array{scope:string,debt_type:string,cutoff_date:string,cutoff_at:string}
     */
    public function appliedFilters(): array
    {
        return [
            'scope' => $this->scope,
            'debt_type' => $this->debtType,
            'cutoff_date' => $this->cutoffAt->toDateString(),
            'cutoff_at' => $this->cutoffAt->toDateTimeString(),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        if ($this->cachedRows !== null) {
            return $this->cachedRows;
        }

        $records = collect(DB::select($this->sql()))
            ->map(fn (object $row): array => $this->transformRow($row))
            ->filter(fn (array $row): bool => (int) $row['gross_selected_bs_minor'] > 0 && (int) $row['final_due_bs_minor'] > 0)
            ->values();

        $this->cachedRows = $this->sortRows($records)->values();

        return $this->cachedRows;
    }

    /**
     * @return array<string, mixed>
     */
    public function totals(): array
    {
        $rows = $this->rows();

        return [
            'debtors_count' => $rows->count(),
            'locals_count' => (int) $rows->sum(fn (array $row): int => (int) ($row['locals_count'] ?? 0)),
            'charges_count' => (int) $rows->sum(fn (array $row): int => (int) ($row['selected_charge_count'] ?? 0)),
            'gross_selected_bs_minor' => (int) $rows->sum(fn (array $row): int => (int) ($row['gross_selected_bs_minor'] ?? 0)),
            'final_due_bs_minor' => (int) $rows->sum(fn (array $row): int => (int) ($row['final_due_bs_minor'] ?? 0)),
            'credits_open_bs_minor' => (int) $rows->sum(fn (array $row): int => (int) ($row['credits_open_bs_minor'] ?? 0)),
            'payments_available_bs_minor' => (int) $rows->sum(fn (array $row): int => (int) ($row['payments_available_bs_minor'] ?? 0)),
            'max_days_overdue' => (int) ($rows->max(fn (array $row): int => (int) ($row['max_days_overdue'] ?? 0)) ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dataForPdf(int $limit = 25): array
    {
        $limit = min(max($limit, 10), 100);
        $rows = $this->rows();

        return [
            'filters' => $this->appliedFilters(),
            'rows' => $rows->take($limit)->values()->all(),
            'totals' => $this->totals(),
            'row_limit' => $limit,
            'rows_truncated' => $rows->count() > $limit,
            'generated_at' => $this->cutoffAt->toDateTimeString(),
        ];
    }

    private function sql(): string
    {
        $cutoffDate = $this->quoted($this->cutoffAt->toDateString()).'::date';
        $cutoffTimestamp = $this->quoted($this->cutoffAt->toDateTimeString()).'::timestamp';
        $scopedCharges = $this->scope === 'local'
            ? $this->localScopedChargesSql()
            : $this->concessionaireScopedChargesSql();
        $debtorRows = $this->scope === 'local'
            ? $this->localDebtorRowsSql()
            : $this->concessionaireDebtorRowsSql();

        return <<<SQL
WITH allocation_currency AS (
    SELECT
        pa.charge_id,
        COALESCE(SUM(pa.amount_bs_minor), 0)::bigint AS paid_bs_minor,
        COALESCE(SUM(
            CASE
                WHEN UPPER(COALESCE(ch.currency, 'VES')) = 'VES' THEN pa.amount_bs_minor
                ELSE COALESCE(ROUND(ROUND((pa.amount_bs_minor::numeric * 100) / NULLIF(fr_alloc.rate_to_ves, 0)) / 100), 0)::bigint
            END
        ), 0)::bigint AS paid_currency_minor
    FROM payment_allocations pa
    INNER JOIN payments p ON p.id = pa.payment_id
        AND p.deleted_at IS NULL
        AND p.voided_at IS NULL
    INNER JOIN charges ch ON ch.id = pa.charge_id
    LEFT JOIN LATERAL (
        SELECT fr.rate_to_ves
        FROM fx_rates fr
        WHERE fr.currency_code = UPPER(COALESCE(ch.currency, 'VES'))
          AND fr.is_active = TRUE
          AND fr.deleted_at IS NULL
          AND fr.operational_from <= p.paid_on::timestamp
          AND (fr.operational_to IS NULL OR fr.operational_to > p.paid_on::timestamp)
        ORDER BY fr.operational_from DESC
        LIMIT 1
    ) fr_alloc ON UPPER(COALESCE(ch.currency, 'VES')) <> 'VES'
    WHERE pa.deleted_at IS NULL
    GROUP BY pa.charge_id
),
credit_bs AS (
    SELECT
        ca.charge_id,
        COALESCE(SUM(
            CASE
                WHEN UPPER(COALESCE(cc.currency, 'VES')) = 'VES' THEN ca.amount_minor
                ELSE COALESCE(ROUND((ca.amount_minor::numeric * ROUND(fr_credit.rate_to_ves * 100)) / 100), 0)::bigint
            END
        ), 0)::bigint AS credit_bs_minor
    FROM credit_applications ca
    LEFT JOIN customer_credits cc ON cc.id = ca.customer_credit_id
    LEFT JOIN LATERAL (
        SELECT fr.rate_to_ves
        FROM fx_rates fr
        WHERE fr.currency_code = UPPER(COALESCE(cc.currency, 'VES'))
          AND fr.is_active = TRUE
          AND fr.deleted_at IS NULL
          AND fr.operational_from <= {$cutoffTimestamp}
          AND (fr.operational_to IS NULL OR fr.operational_to > {$cutoffTimestamp})
        ORDER BY fr.operational_from DESC
        LIMIT 1
    ) fr_credit ON UPPER(COALESCE(cc.currency, 'VES')) <> 'VES'
    WHERE ca.deleted_at IS NULL
    GROUP BY ca.charge_id
),
open_charges AS (
    SELECT
        ch.id AS charge_id,
        ch.debtor_type,
        ch.debtor_id,
        COALESCE(ch.local_id, CASE WHEN ch.debtor_type = 'LOCAL' THEN ch.debtor_id ELSE NULL END) AS local_id,
        ch.contract_id,
        ch.market_id,
        ch.kind,
        UPPER(COALESCE(ch.currency, 'VES')) AS currency,
        ch.amount_minor,
        ch.period,
        ch.due_on,
        COALESCE(al.paid_bs_minor, 0)::bigint AS paid_bs_minor,
        COALESCE(al.paid_currency_minor, 0)::bigint AS paid_currency_minor,
        COALESCE(cb.credit_bs_minor, 0)::bigint AS credit_bs_minor,
        CASE
            WHEN UPPER(COALESCE(ch.currency, 'VES')) = 'VES' THEN COALESCE(cb.credit_bs_minor, 0)
            ELSE COALESCE(ROUND(ROUND((COALESCE(cb.credit_bs_minor, 0)::numeric * 100) / NULLIF(fr_charge.rate_to_ves, 0)) / 100), 0)::bigint
        END AS credit_currency_minor,
        CASE
            WHEN UPPER(COALESCE(ch.currency, 'VES')) = 'VES' THEN 1::numeric
            ELSE COALESCE(fr_charge.rate_to_ves, 0)::numeric
        END AS charge_rate_to_ves
    FROM charges ch
    INNER JOIN charge_statuses cs ON cs.id = ch.charge_status_id
    LEFT JOIN allocation_currency al ON al.charge_id = ch.id
    LEFT JOIN credit_bs cb ON cb.charge_id = ch.id
    LEFT JOIN LATERAL (
        SELECT fr.rate_to_ves
        FROM fx_rates fr
        WHERE fr.currency_code = UPPER(COALESCE(ch.currency, 'VES'))
          AND fr.is_active = TRUE
          AND fr.deleted_at IS NULL
          AND fr.operational_from <= {$cutoffTimestamp}
          AND (fr.operational_to IS NULL OR fr.operational_to > {$cutoffTimestamp})
        ORDER BY fr.operational_from DESC
        LIMIT 1
    ) fr_charge ON UPPER(COALESCE(ch.currency, 'VES')) <> 'VES'
    WHERE ch.deleted_at IS NULL
      AND cs.code IN ('ISSUED', 'PARTIAL')
),
charge_balances AS (
    SELECT
        oc.*,
        GREATEST(oc.amount_minor - oc.paid_currency_minor - oc.credit_currency_minor, 0)::bigint AS outstanding_currency_minor
    FROM open_charges oc
),
charge_debts AS (
    SELECT
        cb.*,
        CASE
            WHEN cb.currency = 'VES' THEN cb.outstanding_currency_minor
            ELSE COALESCE(ROUND((cb.outstanding_currency_minor::numeric * ROUND(cb.charge_rate_to_ves * 100)) / 100), 0)::bigint
        END AS outstanding_bs_minor
    FROM charge_balances cb
),
active_contract_by_local AS (
    SELECT DISTINCT ON (cl.local_id)
        cl.local_id,
        cl.contract_id
    FROM contract_local cl
    INNER JOIN contracts ct ON ct.id = cl.contract_id
    INNER JOIN contract_statuses cts ON cts.id = ct.contract_status_id
    WHERE ct.deleted_at IS NULL
      AND ct.start_date <= {$cutoffDate}
      AND cts.code IN ('VIG', 'EXT', 'VENC')
      AND (
          (cts.code IN ('VIG', 'EXT') AND (ct.end_date IS NULL OR ct.end_date >= {$cutoffDate}))
          OR cts.code = 'VENC'
      )
    ORDER BY cl.local_id, ct.start_date DESC, ct.id DESC
),
primary_concessionaire_by_contract AS (
    SELECT DISTINCT ON (contract_id)
        contract_id,
        concessionaire_id
    FROM concessionaire_contract
    ORDER BY contract_id, is_primary DESC, id ASC
),
payment_allocated_by_payment AS (
    SELECT
        payment_id,
        COALESCE(SUM(amount_bs_minor), 0)::bigint AS allocated_bs_minor
    FROM payment_allocations
    WHERE deleted_at IS NULL
    GROUP BY payment_id
),
available_payments AS (
    SELECT
        p.debtor_type,
        p.debtor_id,
        GREATEST(COALESCE(SUM(p.amount_bs_minor), 0) - COALESCE(SUM(pap.allocated_bs_minor), 0), 0)::bigint AS payments_available_bs_minor
    FROM payments p
    INNER JOIN payment_statuses ps ON ps.id = p.payment_status_id
    LEFT JOIN payment_allocated_by_payment pap ON pap.payment_id = p.id
    WHERE p.deleted_at IS NULL
      AND p.voided_at IS NULL
      AND ps.code = 'CONF'
    GROUP BY p.debtor_type, p.debtor_id
),
open_credits AS (
    SELECT
        debtor_type,
        debtor_id,
        COALESCE(SUM(balance_minor), 0)::bigint AS credits_open_bs_minor
    FROM customer_credits
    WHERE deleted_at IS NULL
      AND status = 'OPEN'
    GROUP BY debtor_type, debtor_id
),
scoped_charges AS (
    {$scopedCharges}
),
aggregated_charges AS (
    SELECT
        sc.report_debtor_type,
        sc.report_debtor_id,
        COUNT(*) FILTER (WHERE sc.outstanding_bs_minor > 0)::int AS open_charge_count,
        COUNT(*) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on <= {$cutoffDate})::int AS overdue_charge_count,
        COUNT(*) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on > {$cutoffDate})::int AS current_charge_count,
        COALESCE(SUM(sc.outstanding_bs_minor) FILTER (WHERE sc.outstanding_bs_minor > 0), 0)::bigint AS gross_open_bs_minor,
        COALESCE(SUM(sc.outstanding_bs_minor) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on <= {$cutoffDate}), 0)::bigint AS gross_overdue_bs_minor,
        COALESCE(SUM(sc.outstanding_bs_minor) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on > {$cutoffDate}), 0)::bigint AS gross_current_bs_minor,
        MAX(({$cutoffDate} - sc.due_on::date)) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on <= {$cutoffDate})::int AS max_days_overdue,
        ROUND(AVG(({$cutoffDate} - sc.due_on::date)) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on <= {$cutoffDate})::numeric)::int AS avg_days_overdue,
        MIN(sc.due_on) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on <= {$cutoffDate}) AS oldest_due_on,
        MIN(sc.due_on) FILTER (WHERE sc.outstanding_bs_minor > 0 AND sc.due_on > {$cutoffDate}) AS next_due_on,
        COUNT(DISTINCT sc.local_id) FILTER (WHERE sc.local_id IS NOT NULL)::int AS locals_count,
        STRING_AGG(DISTINCT NULLIF(TRIM(l.code), ''), ', ' ORDER BY NULLIF(TRIM(l.code), '')) AS local_codes,
        STRING_AGG(DISTINCT NULLIF(TRIM(m.name), ''), ', ' ORDER BY NULLIF(TRIM(m.name), '')) AS market_names
    FROM scoped_charges sc
    LEFT JOIN locals l ON l.id = sc.local_id AND l.deleted_at IS NULL
    LEFT JOIN markets m ON m.id = COALESCE(sc.market_id, l.market_id) AND m.deleted_at IS NULL
    WHERE sc.outstanding_bs_minor > 0
    GROUP BY sc.report_debtor_type, sc.report_debtor_id
),
debtor_rows AS (
    {$debtorRows}
)
SELECT
    dr.*,
    COALESCE(oc.credits_open_bs_minor, 0)::bigint AS credits_open_bs_minor,
    COALESCE(ap.payments_available_bs_minor, 0)::bigint AS payments_available_bs_minor
FROM debtor_rows dr
LEFT JOIN open_credits oc ON oc.debtor_type = dr.report_debtor_type AND oc.debtor_id = dr.report_debtor_id
LEFT JOIN available_payments ap ON ap.debtor_type = dr.report_debtor_type AND ap.debtor_id = dr.report_debtor_id
SQL;
    }

    private function localScopedChargesSql(): string
    {
        return <<<'SQL'
    SELECT
        'LOCAL'::varchar AS report_debtor_type,
        cd.debtor_id AS report_debtor_id,
        cd.*
    FROM charge_debts cd
    WHERE cd.debtor_type = 'LOCAL'
SQL;
    }

    private function concessionaireScopedChargesSql(): string
    {
        return <<<'SQL'
    SELECT
        'CONCESSIONAIRE'::varchar AS report_debtor_type,
        COALESCE(pcc.concessionaire_id, 0) AS report_debtor_id,
        cd.*
    FROM charge_debts cd
    LEFT JOIN active_contract_by_local acl ON acl.local_id = cd.local_id
    LEFT JOIN primary_concessionaire_by_contract pcc ON pcc.contract_id = acl.contract_id
    WHERE cd.debtor_type = 'LOCAL'

    UNION ALL

    SELECT
        'CONCESSIONAIRE'::varchar AS report_debtor_type,
        cd.debtor_id AS report_debtor_id,
        cd.*
    FROM charge_debts cd
    WHERE cd.debtor_type = 'CONCESSIONAIRE'
SQL;
    }

    private function localDebtorRowsSql(): string
    {
        return <<<'SQL'
    SELECT
        ac.*,
        COALESCE(NULLIF(TRIM(l.code || ' ' || l.name), ''), 'Local #' || ac.report_debtor_id::text) AS debtor_name,
        COALESCE(l.code, '') AS debtor_code,
        ''::varchar AS debtor_document,
        COALESCE(cn.full_name, '') AS concessionaire_name,
        COALESCE(cn.document_number, '') AS concessionaire_document,
        COALESCE(ac.market_names, m.name, '') AS market_names
    FROM aggregated_charges ac
    INNER JOIN locals l ON l.id = ac.report_debtor_id AND l.deleted_at IS NULL
    LEFT JOIN markets m ON m.id = l.market_id AND m.deleted_at IS NULL
    LEFT JOIN active_contract_by_local acl ON acl.local_id = l.id
    LEFT JOIN primary_concessionaire_by_contract pcc ON pcc.contract_id = acl.contract_id
    LEFT JOIN concessionaires cn ON cn.id = pcc.concessionaire_id AND cn.deleted_at IS NULL
SQL;
    }

    private function concessionaireDebtorRowsSql(): string
    {
        return <<<'SQL'
    SELECT
        ac.*,
        COALESCE(cn.full_name, 'Recuperados por el Mercado') AS debtor_name,
        ''::varchar AS debtor_code,
        COALESCE(cn.document_number, '') AS debtor_document,
        COALESCE(cn.full_name, '') AS concessionaire_name,
        COALESCE(cn.document_number, '') AS concessionaire_document,
        COALESCE(ac.market_names, '') AS market_names
    FROM aggregated_charges ac
    LEFT JOIN concessionaires cn ON cn.id = ac.report_debtor_id
    WHERE ac.report_debtor_id = 0 OR cn.deleted_at IS NULL
SQL;
    }

    /**
     * @return array<string, mixed>
     */
    private function transformRow(object $row): array
    {
        $grossOverdue = (int) ($row->gross_overdue_bs_minor ?? 0);
        $grossCurrent = (int) ($row->gross_current_bs_minor ?? 0);
        $grossSelected = $this->debtType === 'overdue' ? $grossOverdue : $grossCurrent;
        $selectedChargeCount = $this->debtType === 'overdue'
            ? (int) ($row->overdue_charge_count ?? 0)
            : (int) ($row->current_charge_count ?? 0);
        $credits = (int) ($row->credits_open_bs_minor ?? 0);
        $payments = (int) ($row->payments_available_bs_minor ?? 0);
        $available = $credits + $payments;
        $finalDue = max(0, $grossSelected - $available);

        return [
            'scope' => $this->scope,
            'debt_type' => $this->debtType,
            'debtor_type' => (string) ($row->report_debtor_type ?? ''),
            'debtor_id' => (int) ($row->report_debtor_id ?? 0),
            'debtor_name' => (string) ($row->debtor_name ?? ''),
            'debtor_code' => (string) ($row->debtor_code ?? ''),
            'debtor_document' => (string) ($row->debtor_document ?? ''),
            'concessionaire_name' => (string) ($row->concessionaire_name ?? ''),
            'concessionaire_document' => (string) ($row->concessionaire_document ?? ''),
            'market_names' => (string) ($row->market_names ?? ''),
            'local_codes' => (string) ($row->local_codes ?? ''),
            'locals_count' => (int) ($row->locals_count ?? 0),
            'open_charge_count' => (int) ($row->open_charge_count ?? 0),
            'overdue_charge_count' => (int) ($row->overdue_charge_count ?? 0),
            'current_charge_count' => (int) ($row->current_charge_count ?? 0),
            'selected_charge_count' => $selectedChargeCount,
            'gross_open_bs_minor' => (int) ($row->gross_open_bs_minor ?? 0),
            'gross_overdue_bs_minor' => $grossOverdue,
            'gross_current_bs_minor' => $grossCurrent,
            'gross_selected_bs_minor' => $grossSelected,
            'credits_open_bs_minor' => $credits,
            'payments_available_bs_minor' => $payments,
            'available_balance_bs_minor' => $available,
            'relief_applied_bs_minor' => min($grossSelected, $available),
            'final_due_bs_minor' => $finalDue,
            'max_days_overdue' => max(0, (int) ($row->max_days_overdue ?? 0)),
            'avg_days_overdue' => max(0, (int) ($row->avg_days_overdue ?? 0)),
            'oldest_due_on' => $row->oldest_due_on !== null ? (string) $row->oldest_due_on : null,
            'next_due_on' => $row->next_due_on !== null ? (string) $row->next_due_on : null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function sortRows(Collection $rows): Collection
    {
        return $rows->sort(function (array $a, array $b): int {
            if ($this->debtType === 'overdue') {
                $byDays = (int) $b['max_days_overdue'] <=> (int) $a['max_days_overdue'];
                if ($byDays !== 0) {
                    return $byDays;
                }

                $byCount = (int) $b['selected_charge_count'] <=> (int) $a['selected_charge_count'];
                if ($byCount !== 0) {
                    return $byCount;
                }
            } else {
                $aDue = (string) ($a['next_due_on'] ?? '9999-12-31');
                $bDue = (string) ($b['next_due_on'] ?? '9999-12-31');
                $byDue = strcmp($aDue, $bDue);
                if ($byDue !== 0) {
                    return $byDue;
                }
            }

            $byAmount = (int) $b['final_due_bs_minor'] <=> (int) $a['final_due_bs_minor'];
            if ($byAmount !== 0) {
                return $byAmount;
            }

            return strcmp(strtolower((string) $a['debtor_name']), strtolower((string) $b['debtor_name']));
        });
    }

    private function quoted(string $value): string
    {
        return DB::connection()->getPdo()->quote($value);
    }
}
