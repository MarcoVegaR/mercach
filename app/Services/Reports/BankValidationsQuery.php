<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Audit;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * Query builder for Bank Validations report.
 *
 * Extracts complex logic from ReportController for better testability and reuse.
 */
class BankValidationsQuery
{
    /** @var array<string, mixed> */
    private array $filters = [];

    private string $searchQuery = '';

    /**
     * Set filters for the query.
     *
     * @param  array<string, mixed>  $filters
     */
    public function withFilters(array $filters): self
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * Set search query string.
     */
    public function search(string $query): self
    {
        $this->searchQuery = trim($query);

        return $this;
    }

    /**
     * Execute the query and return results.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(): Collection
    {
        $rows = collect();

        // Get payment-based rows
        $paymentRows = $this->getPaymentRows();
        $rows = $rows->concat($paymentRows);

        // Get audit-based rows (failed verifications)
        $auditRows = $this->getAuditRows();
        foreach ($auditRows as $row) {
            $rows->push($row);
        }

        // Apply status filter if present
        if (! empty($this->filters['status'])) {
            $statusFilter = (string) $this->filters['status'];
            $rows = $rows->filter(fn (array $row) => (string) ($row['status'] ?? '') === $statusFilter)->values();
        }

        return $rows;
    }

    /**
     * Get common response codes for filter dropdown.
     *
     * @return array<int, array{code: string, label: string}>
     */
    public static function getResponseCodes(): array
    {
        return [
            ['code' => 'ACCP', 'label' => 'ACCP - Transacción Aprobada'],
            ['code' => '00', 'label' => '00 - Aprobado'],
            ['code' => '831', 'label' => '831 - Transacción ya fue validada'],
            ['code' => '830', 'label' => '830 - Descripción de error no disponible'],
            ['code' => 'BE11', 'label' => 'BE11 - Id. Emisor no corresponde'],
            ['code' => '706', 'label' => '706 - Cod. Banco de numero cuenta invalido'],
            ['code' => '707', 'label' => '707 - Transacción duplicada'],
            ['code' => '708', 'label' => '708 - Cuenta destino inválida'],
            ['code' => '709', 'label' => '709 - Referencia no encontrada'],
            ['code' => '710', 'label' => '710 - Monto no coincide'],
        ];
    }

    /**
     * Get rows from payments with gateway responses.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function getPaymentRows(): Collection
    {
        $query = Payment::query()
            ->with(['companyBankAccount.bank', 'originBank'])
            ->where(function ($w): void {
                $w->whereNotNull('gateway_response')
                    ->orWhereNotNull('gateway_resp_code')
                    ->orWhereNotNull('gateway_message');
            });

        // Apply search
        if ($this->searchQuery !== '') {
            $q = $this->searchQuery;
            $query->where(function ($w) use ($q): void {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('payer_document_number', 'like', "%{$q}%")
                    ->orWhere('payer_account_number', 'like', "%{$q}%");
            });
        }

        // Apply date filters
        $paidBetween = (array) ($this->filters['paid_between'] ?? []);
        if (! empty($paidBetween['from'])) {
            $query->whereDate('paid_on', '>=', (string) $paidBetween['from']);
        }
        if (! empty($paidBetween['to'])) {
            $query->whereDate('paid_on', '<=', (string) $paidBetween['to']);
        }

        // Apply response code filter
        if (! empty($this->filters['response_code'])) {
            $query->where('gateway_resp_code', (string) $this->filters['response_code']);
        }

        return $query->get()->map(fn ($payment) => $this->transformPayment($payment));
    }

    /**
     * Transform a payment to report row format.
     *
     * @return array<string, mixed>
     */
    private function transformPayment(Payment $payment): array
    {
        $reqId = $this->extractReqId($payment->getAttribute('gateway_response'));

        $companyAccount = null;
        if ($payment->companyBankAccount) {
            $bank = $payment->companyBankAccount->bank;
            $bankName = $bank ? (string) $bank->name : '';
            $accountNumber = (string) ($payment->companyBankAccount->account_number ?? '');
            $companyAccount = trim(($bankName !== '' ? $bankName.' ' : '').$accountNumber);
        }

        $method = strtoupper((string) ($payment->method ?? ''));
        $origin = $method === 'PMOV'
            ? (string) $payment->payer_phone_e164
            : (string) $payment->payer_account_number;

        return [
            'id' => (int) $payment->id,
            'paid_on' => (string) $payment->paid_on,
            'reference' => (string) $payment->reference,
            'origin_account' => $origin,
            'destination_account' => $companyAccount,
            'amount_bs' => (float) $payment->amount_bs_minor / 100.0,
            'payer_document' => (string) $payment->payer_document_number,
            'gateway_resp_code' => (string) ($payment->gateway_resp_code ?? ''),
            'gateway_message' => (string) ($payment->gateway_message ?? ''),
            'req_id' => $reqId,
            'status' => (string) ($payment->status ?? ''),
            'method' => $method,
        ];
    }

    /**
     * Get rows from audit entries (failed verifications).
     *
     * @return array<string, array<string, mixed>>
     */
    private function getAuditRows(): array
    {
        $audits = Audit::query()
            ->where('event', 'payment.verify_failed')
            ->where('auditable_type', Payment::class)
            ->where('auditable_id', 0)
            ->orderByDesc('id')
            ->limit(2000)
            ->get();

        $paidFrom = (string) (($this->filters['paid_between'] ?? [])['from'] ?? '');
        $paidTo = (string) (($this->filters['paid_between'] ?? [])['to'] ?? '');
        $responseCodeFilter = (string) ($this->filters['response_code'] ?? '');

        $rowsByKey = [];

        foreach ($audits as $audit) {
            $row = $this->processAuditEntry($audit, $paidFrom, $paidTo, $responseCodeFilter);
            if ($row === null) {
                continue;
            }

            $key = $row['_key'];
            unset($row['_key']);

            if (! array_key_exists($key, $rowsByKey)) {
                $rowsByKey[$key] = $row;
            } elseif (($row['req_id'] ?? '') !== '' && ($rowsByKey[$key]['req_id'] ?? '') === '') {
                $rowsByKey[$key] = $row;
            }
        }

        return $rowsByKey;
    }

    /**
     * Process a single audit entry.
     *
     * @return array<string, mixed>|null
     */
    private function processAuditEntry(Audit $audit, string $paidFrom, string $paidTo, string $responseCodeFilter): ?array
    {
        $nvRaw = $audit->new_values;
        $nv = is_array($nvRaw) ? $nvRaw : [];
        $input = (array) ($nv['input'] ?? []);
        $message = (string) ($nv['message'] ?? '');

        $paidOn = (string) ($input['paid_on'] ?? '');

        // Date filter
        if ($paidFrom !== '' && $paidOn !== '' && $paidOn < $paidFrom) {
            return null;
        }
        if ($paidTo !== '' && $paidOn !== '' && $paidOn > $paidTo) {
            return null;
        }

        $reference = (string) ($input['reference'] ?? '');
        $payerDocument = (string) ($input['payer_document_number'] ?? '');
        $payerAccount = (string) ($input['payer_account_number'] ?? '');

        // Search filter
        if ($this->searchQuery !== '') {
            $matched = false;
            foreach ([$reference, $payerDocument, $payerAccount] as $field) {
                if ($field !== '' && stripos($field, $this->searchQuery) !== false) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return null;
            }
        }

        // Extract response code from message
        $code = null;
        if (preg_match('/c[oó]digo\s+([0-9A-Z]+)/iu', $message, $m)) {
            $code = strtoupper((string) $m[1]);
        }
        if ($responseCodeFilter !== '' && $code !== $responseCodeFilter) {
            return null;
        }

        // Get company account
        $companyAccount = $this->resolveCompanyAccount((int) ($input['company_bank_account_id'] ?? 0));

        $method = strtoupper((string) ($input['method'] ?? ''));
        $origin = $method === 'PMOV'
            ? (string) ($input['payer_phone_e164'] ?? '')
            : $payerAccount;

        $amountMinor = (int) ($input['amount_bs_minor'] ?? 0);

        // Extract ReqId
        $auditReqId = $nv['req_id'] ?? null;
        if ($auditReqId === null && isset($input['__verify_result']['req_id'])) {
            $auditReqId = $input['__verify_result']['req_id'];
        }

        return [
            'id' => -((int) $audit->id),
            'paid_on' => $paidOn !== '' ? $paidOn : (string) $audit->created_at,
            'reference' => $reference,
            'origin_account' => $origin,
            'destination_account' => $companyAccount,
            'amount_bs' => (float) $amountMinor / 100.0,
            'payer_document' => $payerDocument,
            'gateway_resp_code' => $code,
            'gateway_message' => $message,
            'req_id' => $auditReqId !== null ? (string) $auditReqId : null,
            'status' => 'NOT_SAVED',
            'method' => $method,
            '_key' => implode('|', [$paidOn, $reference, $payerDocument, $payerAccount, $method, (string) $amountMinor]),
        ];
    }

    /**
     * Resolve company bank account display string.
     */
    private function resolveCompanyAccount(int $companyId): ?string
    {
        if ($companyId <= 0) {
            return null;
        }

        /** @var CompanyBankAccount|null $acc */
        $acc = CompanyBankAccount::query()->with('bank')->find($companyId);
        if (! $acc) {
            return null;
        }

        $bank = $acc->bank;
        $bankName = $bank ? (string) $bank->name : '';
        $accountNumber = (string) ($acc->account_number ?? '');

        return trim(($bankName !== '' ? $bankName.' ' : '').$accountNumber);
    }

    /**
     * Extract ReqId from gateway response.
     *
     * @param  mixed  $raw
     */
    private function extractReqId($raw): ?string
    {
        if (is_array($raw)) {
            return isset($raw['sReqId']) ? (string) $raw['sReqId'] : null;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return isset($decoded['sReqId']) ? (string) $decoded['sReqId'] : null;
            }
        }

        return null;
    }
}
