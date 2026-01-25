<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable as Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyBankReconciliationQuery
{
    private string $dateBasis = 'PAID_ON';

    private ?string $paidFrom = null;

    private ?string $paidTo = null;

    private ?int $destinationBankId = null;

    private ?string $status = null;

    private ?string $method = null;

    public function withFilters(mixed $filters): self
    {
        $filters = is_array($filters) ? $filters : [];

        $basis = $filters['date_basis'] ?? null;
        $basisStr = is_string($basis) ? strtoupper(trim($basis)) : '';
        $this->dateBasis = in_array($basisStr, ['PAID_ON', 'CREATED_AT'], true) ? $basisStr : 'PAID_ON';

        $paidBetween = (array) ($filters['paid_between'] ?? []);
        $from = ! empty($paidBetween['from']) ? (string) $paidBetween['from'] : null;
        $to = ! empty($paidBetween['to']) ? (string) $paidBetween['to'] : null;

        if ($from === null && $to === null) {
            $today = date('Y-m-d');
            $from = $today;
            $to = $today;
        }

        $this->paidFrom = $from;
        $this->paidTo = $to;

        $destBankId = $filters['destination_bank_id'] ?? null;
        $this->destinationBankId = is_numeric($destBankId) && (int) $destBankId > 0 ? (int) $destBankId : null;

        $status = $filters['status'] ?? null;
        $this->status = is_string($status) && $status !== '' ? strtoupper($status) : null;

        $method = $filters['method'] ?? null;
        $this->method = is_string($method) && $method !== '' ? strtoupper($method) : null;

        return $this;
    }

    public function paginate(int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        return $this->buildQuery()
            ->paginate($perPage, $this->selectColumns(), 'page', $page)
            ->withQueryString();
    }

    public function get(int $limit = 5000): Collection
    {
        return $this->buildQuery()
            ->limit($limit)
            ->get($this->selectColumns());
    }

    public function transform(LengthAwarePaginator|Collection $results): Collection
    {
        $collection = $results instanceof LengthAwarePaginator
            ? collect($results->items())
            : $results;

        return $collection->map(function ($row): array {
            $createdAt = (string) ($row->created_at ?? '');
            $displayDate = $this->dateBasis === 'CREATED_AT'
                ? ($createdAt !== '' ? substr($createdAt, 0, 10) : '')
                : (string) ($row->paid_on ?? '');

            $method = strtoupper((string) ($row->method_code ?? $row->legacy_method ?? $row->method_name ?? ''));
            $originAccount = $method === 'PMOV'
                ? (string) ($row->payer_phone_e164 ?? '')
                : (string) ($row->payer_account_number ?? '');

            $payerDocType = strtoupper((string) ($row->payer_document_type_code ?? ''));
            $payerDocNumber = (string) ($row->payer_document_number ?? '');
            $payerDoc = trim(($payerDocType !== '' ? $payerDocType.'-' : '').$payerDocNumber);

            $destBankName = (string) ($row->destination_bank_name ?? '');
            $destAccount = (string) ($row->destination_account_number ?? '');
            $destinationAccount = trim(($destBankName !== '' ? $destBankName.' ' : '').$destAccount);

            return [
                'payment_id' => (int) ($row->payment_id ?? 0),
                'date' => $displayDate,
                'paid_on' => (string) ($row->paid_on ?? ''),
                'created_at' => $createdAt,
                'reference' => (string) ($row->reference ?? ''),
                'amount_bs' => ((int) ($row->amount_bs_minor ?? 0)) / 100.0,
                'status' => (string) ($row->status ?? ''),
                'method' => $method,
                'destination_bank_name' => $destBankName,
                'destination_account' => $destinationAccount,
                'origin_bank_name' => (string) ($row->origin_bank_name ?? ''),
                'origin_account' => $originAccount,
                'payer_document' => $payerDoc,
            ];
        })->values();
    }

    public function transformForExport(Collection $results): Collection
    {
        return $results->map(function ($row): array {
            $createdAt = (string) ($row->created_at ?? '');
            $createdOn = $createdAt !== '' ? substr($createdAt, 0, 10) : '';
            $displayDate = $this->dateBasis === 'CREATED_AT'
                ? $createdOn
                : (string) ($row->paid_on ?? '');

            $method = strtoupper((string) ($row->method_code ?? $row->legacy_method ?? $row->method_name ?? ''));
            $originAccount = $method === 'PMOV'
                ? (string) ($row->payer_phone_e164 ?? '')
                : (string) ($row->payer_account_number ?? '');

            $payerDocType = strtoupper((string) ($row->payer_document_type_code ?? ''));
            $payerDocNumber = (string) ($row->payer_document_number ?? '');
            $payerDoc = trim(($payerDocType !== '' ? $payerDocType.'-' : '').$payerDocNumber);

            $destBankName = (string) ($row->destination_bank_name ?? '');
            $destAccount = (string) ($row->destination_account_number ?? '');
            $destinationAccount = trim(($destBankName !== '' ? $destBankName.' ' : '').$destAccount);

            return [
                'Fecha (según filtro)' => $displayDate,
                'Fecha de pago' => (string) ($row->paid_on ?? ''),
                'Fecha de registro' => $createdOn,
                'Banco destino' => $destBankName,
                'Cuenta destino' => $destinationAccount,
                'Método' => $method,
                'Referencia' => (string) ($row->reference ?? ''),
                'Monto (Bs)' => number_format(((int) ($row->amount_bs_minor ?? 0)) / 100.0, 2, ',', '.'),
                'Banco origen' => (string) ($row->origin_bank_name ?? ''),
                'Cuenta/Teléfono origen' => $originAccount,
                'Cédula/RIF' => $payerDoc,
                'Estatus' => (string) ($row->status ?? ''),
                'ID Pago' => (int) ($row->payment_id ?? 0),
            ];
        });
    }

    public static function getFilterOptions(): array
    {
        return DB::table('company_bank_accounts as cba')
            ->join('banks as b', 'b.id', '=', 'cba.bank_id')
            ->whereNull('cba.deleted_at')
            ->whereNull('b.deleted_at')
            ->where('cba.is_active', true)
            ->where('b.is_active', true)
            ->distinct()
            ->orderBy('b.name')
            ->pluck('b.name', 'b.id')
            ->map(fn ($name, $id) => ['id' => (int) $id, 'name' => (string) $name])
            ->values()
            ->all();
    }

    private function selectColumns(): array
    {
        return [
            'p.id as payment_id',
            'p.paid_on',
            'p.created_at',
            'p.method as legacy_method',
            'p.reference',
            'p.amount_bs_minor',
            'ps.code as status_code',
            'ps.name as status',
            'pt.code as method_code',
            'pt.name as method_name',
            'bdest.id as destination_bank_id',
            'bdest.name as destination_bank_name',
            'cba.account_number as destination_account_number',
            'borig.name as origin_bank_name',
            'dt.code as payer_document_type_code',
            'p.payer_document_number',
            'p.payer_account_number',
            'p.payer_phone_e164',
        ];
    }

    private function buildQuery(): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('payments as p')
            ->join('company_bank_accounts as cba', 'cba.id', '=', 'p.company_bank_account_id')
            ->join('banks as bdest', 'bdest.id', '=', 'cba.bank_id')
            ->leftJoin('banks as borig', 'borig.id', '=', 'p.origin_bank_id')
            ->leftJoin('payment_statuses as ps', 'ps.id', '=', 'p.payment_status_id')
            ->leftJoin('payment_types as pt', 'pt.id', '=', 'p.payment_type_id')
            ->leftJoin('document_types as dt', 'dt.id', '=', 'p.payer_document_type_id')
            ->whereNull('p.deleted_at')
            ->whereNull('cba.deleted_at')
            ->whereNull('bdest.deleted_at');

        $tz = (string) config('app.timezone', 'America/Caracas');
        if ($this->dateBasis === 'CREATED_AT') {
            if ($this->paidFrom !== null) {
                $fromAt = Carbon::parse($this->paidFrom, $tz)->startOfDay()->toDateTimeString();
                $q->where('p.created_at', '>=', $fromAt);
            }
            if ($this->paidTo !== null) {
                $toAt = Carbon::parse($this->paidTo, $tz)->endOfDay()->toDateTimeString();
                $q->where('p.created_at', '<=', $toAt);
            }
        } else {
            if ($this->paidFrom !== null) {
                $q->whereDate('p.paid_on', '>=', $this->paidFrom);
            }
            if ($this->paidTo !== null) {
                $q->whereDate('p.paid_on', '<=', $this->paidTo);
            }
        }

        if ($this->destinationBankId !== null) {
            $q->where('bdest.id', $this->destinationBankId);
        }

        if ($this->status !== null) {
            $map = [
                'REGISTERED' => 'REG',
                'CONFIRMED' => 'CONF',
                'APPLIED' => 'CONC',
                'CONCILIADO' => 'CONC',
                'VOID' => 'VOID',
                'VOIDED' => 'VOID',
            ];
            $code = $map[$this->status] ?? $this->status;
            $q->where('ps.code', $code);
        } else {
            $q->where(function ($w): void {
                $w->whereNull('ps.code')->orWhere('ps.code', '!=', 'VOID');
            });
        }

        if ($this->method !== null) {
            $q->where(function ($w): void {
                $w->where('pt.code', $this->method)->orWhere('p.method', $this->method);
            });
        }

        if ($this->dateBasis === 'CREATED_AT') {
            return $q->orderBy('p.created_at', 'desc')->orderBy('bdest.name')->orderBy('p.id', 'desc');
        }

        return $q->orderBy('p.paid_on', 'desc')->orderBy('bdest.name')->orderBy('p.id', 'desc');
    }
}
