<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\BankGatewayInterface;
use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\Audit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PaymentService extends BaseService implements PaymentServiceInterface
{
    /**
     * Set defaults before creating a Payment.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        // Default lifecycle status
        if (empty($attributes['status'])) {
            $attributes['status'] = 'REGISTERED';
        }

        // Map legacy 'method' (code) to FK payment_type_id when present
        if (! isset($attributes['payment_type_id']) && ! empty($attributes['method']) && is_string($attributes['method'])) {
            try {
                /** @var null|\App\Models\PaymentType $pt */
                $pt = \App\Models\PaymentType::query()->where('code', strtoupper(trim((string) $attributes['method'])))->first();
                if ($pt) {
                    $attributes['payment_type_id'] = (int) $pt->getKey();
                }
            } catch (\Throwable $e) {
                // ignore, will be validated elsewhere
            }
        }
        // Map legacy 'payer_document_type' (code) to FK payer_document_type_id when present
        if (! isset($attributes['payer_document_type_id']) && ! empty($attributes['payer_document_type']) && is_string($attributes['payer_document_type'])) {
            try {
                /** @var null|\App\Models\DocumentType $dt */
                $dt = \App\Models\DocumentType::query()->where('code', strtoupper(trim((string) $attributes['payer_document_type'])))->first();
                if ($dt) {
                    $attributes['payer_document_type_id'] = (int) $dt->getKey();
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        try {
            $methodRaw = (string) ($attributes['method'] ?? '');
            $method = strtoupper(trim($methodRaw));
            $method = match ($method) {
                'TRANSFER' => 'TRF',
                'PAGOMOVIL', 'PAGO MOVIL', 'PAGO-MOVIL' => 'PMOV',
                default => $method,
            };

            $companyId = (int) ($attributes['company_bank_account_id'] ?? 0);
            $originBankId = (int) ($attributes['origin_bank_id'] ?? 0);
            $amountMinor = (int) ($attributes['amount_bs_minor'] ?? 0);
            $paidOn = (string) ($attributes['paid_on'] ?? '');
            $reference = (string) ($attributes['reference'] ?? '');
            $refDigits = preg_replace('/\D+/', '', $reference) ?? '';

            $fingerprint = [];
            if ($method === 'PMOV') {
                $phoneIn = (string) ($attributes['payer_phone_e164'] ?? '');
                $digits = preg_replace('/\D+/', '', $phoneIn) ?? '';
                if ($digits !== '') {
                    if (str_starts_with($digits, '0') && strlen($digits) === 11) {
                        $digits = '58'.substr($digits, 1, 10);
                    }
                }
                $fingerprint = [
                    'm' => 'PMOV',
                    'c' => $companyId,
                    'o' => $originBankId,
                    'p' => $digits,
                    'r' => $refDigits,
                    'a' => $amountMinor,
                    'd' => $paidOn,
                    't' => '300',
                ];
            } elseif ($method === 'DEB') {
                $fingerprint = [
                    'm' => 'DEB',
                    'c' => $companyId,
                    'r' => $refDigits,
                    'a' => $amountMinor,
                    'd' => $paidOn,
                    't' => 'DEB',
                ];
            } else {
                $acctIn = (string) ($attributes['payer_account_number'] ?? '');
                $acct = preg_replace('/\D+/', '', $acctIn) ?? '';
                $fingerprint = [
                    'm' => ($method !== '' ? $method : 'TRF'),
                    'c' => $companyId,
                    'o' => $originBankId,
                    'a20' => $acct,
                    'r' => $refDigits,
                    'a' => $amountMinor,
                    'd' => $paidOn,
                    't' => '211',
                ];
            }

            $json = json_encode($fingerprint, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
            if (is_string($json)) {
                $attributes['idempotency_key'] = hash('sha256', $json);
            }
        } catch (\Throwable $e) {

        }
    }

    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'local_id' => $model->getAttribute('local_id'),
            'debtor_type' => $model->getAttribute('debtor_type'),
            'debtor_id' => $model->getAttribute('debtor_id'),
            'company_bank_account_id' => $model->getAttribute('company_bank_account_id'),
            'method' => $model->getAttribute('method'),
            'origin_bank_id' => $model->getAttribute('origin_bank_id'),
            'payer_document_type' => $model->getAttribute('payer_document_type'),
            'payer_document_number' => $model->getAttribute('payer_document_number'),
            'payer_account_number' => $model->getAttribute('payer_account_number'),
            'payer_phone_e164' => $model->getAttribute('payer_phone_e164'),
            'reference' => $model->getAttribute('reference'),
            'amount_bs_minor' => $model->getAttribute('amount_bs_minor'),
            'applied_bs_minor' => $totalApplied,
            'available_bs_minor' => $availableBs,
            'paid_on' => $model->getAttribute('paid_on'),
            'fx_rate_id' => $model->getAttribute('fx_rate_id'),
            'status' => $model->getAttribute('status'),
            'gateway_request' => $model->getAttribute('gateway_request'),
            'gateway_response' => $model->getAttribute('gateway_response'),
            'gateway_resp_code' => $model->getAttribute('gateway_resp_code'),
            'gateway_message' => $model->getAttribute('gateway_message'),
            'payer_details' => $model->getAttribute('payer_details'),
            'idempotency_key' => $model->getAttribute('idempotency_key'),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        // Derive friendly labels from eager-loaded relations if present
        /** @var null|\App\Models\CompanyBankAccount $companyAcc */
        $companyAcc = null;
        /** @var null|\App\Models\Bank $originBank */
        $originBank = null;
        try {
            if ($model->relationLoaded('companyBankAccount')) {
                /** @var null|\App\Models\CompanyBankAccount $relAcc */
                $relAcc = $model->getRelation('companyBankAccount');
                $companyAcc = $relAcc;
            } else {
                $maybe = $model->getAttribute('companyBankAccount');
                if ($maybe instanceof \App\Models\CompanyBankAccount) {
                    $companyAcc = $maybe;
                }
            }
            if ($model->relationLoaded('originBank')) {
                /** @var null|\App\Models\Bank $relBank */
                $relBank = $model->getRelation('originBank');
                $originBank = $relBank;
            } else {
                $maybeB = $model->getAttribute('originBank');
                if ($maybeB instanceof \App\Models\Bank) {
                    $originBank = $maybeB;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $companyLabel = null;
        if ($companyAcc instanceof \App\Models\CompanyBankAccount) {
            /** @var null|\App\Models\Bank $bankRel */
            $bankRel = null;
            if ($companyAcc->relationLoaded('bank')) {
                /** @var null|\App\Models\Bank $rel */
                $rel = $companyAcc->getRelation('bank');
                $bankRel = $rel;
            } else {
                $maybeBank = $companyAcc->getAttribute('bank');
                if ($maybeBank instanceof \App\Models\Bank) {
                    $bankRel = $maybeBank;
                }
            }
            $bankName = $bankRel?->getAttribute('name');
            $accountNumber = (string) ($companyAcc->getAttribute('account_number') ?? '');
            $companyLabel = trim(($bankName ? ($bankName.' • ') : '').$accountNumber) ?: null;
        }

        // Derive debtor friendly name from type/id when possible
        $debtorName = null;
        try {
            $debtorType = strtoupper((string) ($model->getAttribute('debtor_type') ?? ''));
            $debtorId = (int) ($model->getAttribute('debtor_id') ?? 0);
            if ($debtorType === 'CONCESSIONAIRE' && $debtorId > 0) {
                /** @var null|\App\Models\Concessionaire $c */
                $c = \App\Models\Concessionaire::query()->find($debtorId);
                $debtorName = $c?->getAttribute('full_name');
            } elseif ($debtorType === 'LOCAL' && $debtorId > 0) {
                /** @var null|\App\Models\Local $l */
                $l = \App\Models\Local::query()->find($debtorId);
                if ($l) {
                    $code = (string) ($l->getAttribute('code') ?? '');
                    $name = (string) ($l->getAttribute('name') ?? '');
                    $debtorName = trim(($code ? $code.' • ' : '').$name) ?: null;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Totals for FE (applied and available)
        $totalApplied = (int) PaymentAllocation::query()->where('payment_id', (int) $model->getKey())->sum('amount_bs_minor');
        $amountBs = (int) ($model->getAttribute('amount_bs_minor') ?? 0);
        $availableBs = max(0, $amountBs - $totalApplied);

        // Resolve method code from FK if needed
        $methodForUi = (string) ($model->getAttribute('method') ?? '');
        if ($methodForUi === '') {
            try {
                $ptId = (int) ($model->getAttribute('payment_type_id') ?? 0);
                if ($ptId > 0) {
                    $pt = \App\Models\PaymentType::query()->find($ptId);
                    $methodForUi = (string) ($pt?->getAttribute('code') ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Resolve document type code for FE display if only FK exists
        $documentTypeCode = (string) ($model->getAttribute('payer_document_type') ?? '');
        if ($documentTypeCode === '') {
            try {
                $dtId = (int) ($model->getAttribute('payer_document_type_id') ?? 0);
                if ($dtId > 0) {
                    $dt = \App\Models\DocumentType::query()->find($dtId);
                    $documentTypeCode = (string) ($dt?->getAttribute('code') ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return [
            'id' => $model->getAttribute('id'),
            'local_id' => $model->getAttribute('local_id'),
            'debtor_type' => $model->getAttribute('debtor_type'),
            'debtor_id' => $model->getAttribute('debtor_id'),
            'debtor_name' => $debtorName,
            'company_bank_account_id' => $model->getAttribute('company_bank_account_id'),
            'company_bank_account_label' => $companyLabel,
            'method' => $methodForUi,
            'origin_bank_id' => $model->getAttribute('origin_bank_id'),
            'origin_bank_name' => $originBank?->getAttribute('name'),
            'payer_document_type' => $model->getAttribute('payer_document_type'),
            'document_type_code' => $documentTypeCode,
            'payer_document_number' => $model->getAttribute('payer_document_number'),
            'payer_account_number' => $model->getAttribute('payer_account_number'),
            'payer_phone_e164' => $model->getAttribute('payer_phone_e164'),
            'reference' => $model->getAttribute('reference'),
            'amount_bs_minor' => $model->getAttribute('amount_bs_minor'),
            // Expose applied/available for FE summary (already computed above)
            'applied_bs_minor' => $totalApplied,
            'available_bs_minor' => $availableBs,
            'paid_on' => $model->getAttribute('paid_on'),
            'fx_rate_id' => $model->getAttribute('fx_rate_id'),
            'status' => $model->getAttribute('status'),
            'gateway_request' => $model->getAttribute('gateway_request'),
            'gateway_response' => $model->getAttribute('gateway_response'),
            'gateway_resp_code' => $model->getAttribute('gateway_resp_code'),
            'gateway_message' => $model->getAttribute('gateway_message'),
            'payer_details' => $model->getAttribute('payer_details'),
            'idempotency_key' => $model->getAttribute('idempotency_key'),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'local_id' => 'Local id',
            'debtor_type' => 'Debtor type',
            'debtor_id' => 'Debtor id',
            'company_bank_account_id' => 'Company bank account id',
            'method' => 'Method',
            'origin_bank_id' => 'Origin bank id',
            'payer_document_type' => 'Payer document type',
            'payer_document_number' => 'Payer document number',
            'payer_account_number' => 'Payer account number',
            'payer_phone_e164' => 'Payer phone e164',
            'reference' => 'Reference',
            'amount_bs_minor' => 'Amount bs minor',
            'paid_on' => 'Paid on',
            'fx_rate_id' => 'Fx rate id',
            'status' => 'Status',
            'gateway_request' => 'Gateway request',
            'gateway_response' => 'Gateway response',
            'gateway_resp_code' => 'Gateway resp code',
            'gateway_message' => 'Gateway message',
            'payer_details' => 'Payer details',
            'idempotency_key' => 'Idempotency key',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'local_id' => 'Local',
            'debtor_type' => 'Tipo de deudor',
            'debtor_id' => 'Deudor',
            'company_bank_account_label' => 'Cuenta receptora',
            'method' => 'Método',
            'origin_bank_name' => 'Banco origen',
            'payer_document_type' => 'Tipo doc. pagador',
            'payer_document_number' => 'Documento pagador',
            'payer_account_number' => 'Cuenta pagador',
            'payer_phone_e164' => 'Teléfono pagador',
            'reference' => 'Referencia',
            'amount_bs_minor' => 'Monto (Bs, céntimos)',
            'paid_on' => 'Pagado el',
            'fx_rate_id' => 'Tasa FX',
            'status' => 'Estado',
            'gateway_request' => 'Solicitud gateway',
            'gateway_response' => 'Respuesta gateway',
            'gateway_resp_code' => 'Cod. resp.',
            'gateway_message' => 'Mensaje gateway',
            'payer_details' => 'Detalles pagador',
            'idempotency_key' => 'Idempotencia',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\Payment::class;
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\Payment::query();
        $total = (int) $model->count();
        // Aggregate by catalog status (payment_status_id) and map to UI labels
        $byStatus = [];
        if (Schema::hasColumn('payments', 'payment_status_id')) {
            $raw = (clone $model)
                ->selectRaw('payment_status_id, COUNT(*) as cnt')
                ->groupBy('payment_status_id')
                ->pluck('cnt', 'payment_status_id')
                ->toArray();

            // Map ids to codes, then to UI labels
            $map = [];
            try {
                $map = \App\Models\PaymentStatus::query()
                    ->whereIn('id', array_keys($raw))
                    ->pluck('code', 'id')
                    ->toArray();
            } catch (\Throwable $e) {
                $map = [];
            }
            $codeToUi = [
                'REG' => 'REGISTERED',
                'CONF' => 'CONFIRMED',
                'CONC' => 'APPLIED',
            ];
            foreach ($raw as $id => $cnt) {
                $code = strtoupper((string) ($map[$id] ?? ''));
                $ui = $codeToUi[$code] ?? $code ?: 'UNKNOWN';
                $byStatus[$ui] = (int) $cnt;
            }
        }

        return [
            'stats' => [
                'total' => $total,
                'by_status' => $byStatus,
            ],
        ];
    }

    /**
     * Verify a payment with external gateway and update status/messages.
     */
    public function verify(int|string $paymentId): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        // Only REGISTERED payments can be verified
        $status = (string) ($payment->getAttribute('status') ?? 'REGISTERED');
        if ($status !== 'REGISTERED') {
            Log::warning('payment.verify.invalid_state', [
                'payment_id' => (int) $paymentId,
                'current_status' => $status,
            ]);
            throw new DomainActionException('Solo pagos en estado REGISTERED pueden ser verificados.');
        }

        // Resolve method code for logging (supports legacy 'method' or FK payment_type_id)
        $methodCode = (string) ($payment->getAttribute('method') ?? '');
        if ($methodCode === '') {
            try {
                $ptId = (int) ($payment->getAttribute('payment_type_id') ?? 0);
                if ($ptId > 0) {
                    $pt = \App\Models\PaymentType::query()->find($ptId);
                    $methodCode = (string) ($pt?->getAttribute('code') ?? '');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Auto-confirm for debit card payments (POS confirms this, no external gateway)
        if (strtoupper($methodCode) === 'DEB') {
            \Log::info('payment.verify.auto_confirm_debit', [
                'payment_id' => (int) $paymentId,
            ]);
            $attributes = [
                'gateway_request' => ['note' => 'Auto-verify for debit (POS).'],
                'gateway_response' => ['sRespCode' => '00', 'sRespDesc' => 'Autoverificación (tarjeta débito).'],
                'gateway_resp_code' => '00',
                'gateway_message' => 'Autoverificación (tarjeta débito).',
                'status' => 'CONFIRMED',
            ];
            $updated = $this->update($payment, $attributes);

            return $this->toRow($updated);
        }

        Log::info('payment.verify.start', [
            'payment_id' => (int) $paymentId,
            'method' => $methodCode,
            'amount_bs_minor' => (int) ($payment->getAttribute('amount_bs_minor') ?? 0),
            'paid_on' => (string) ($payment->getAttribute('paid_on') ?? ''),
        ]);

        /** @var BankGatewayInterface $gateway */
        $gateway = $this->container->get(BankGatewayInterface::class);
        $result = $gateway->verify($payment);

        // Log gateway outcome (truncate potentially large payloads)
        $rawReq = $result['raw_request'] ?? null;
        $rawRes = $result['raw_response'] ?? null;
        $reqSnippet = is_string($rawReq) ? mb_substr($rawReq, 0, 1024) : null;
        $resSnippet = is_string($rawRes) ? mb_substr($rawRes, 0, 1024) : null;
        Log::info('payment.verify.gateway_result', [
            'payment_id' => (int) $paymentId,
            'ok' => (bool) $result['ok'],
            'code' => (string) ($result['code'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
            'raw_request_len' => is_string($rawReq) ? mb_strlen($rawReq) : null,
            'raw_response_len' => is_string($rawRes) ? mb_strlen($rawRes) : null,
            'raw_request_snippet' => $reqSnippet,
            'raw_response_snippet' => $resSnippet,
        ]);

        // Ensure JSON columns receive arrays; decode JSON strings or wrap raw text
        $toArray = static function ($v) {
            if (is_array($v)) {
                return $v;
            }
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }

                return $v === '' ? null : ['raw' => $v];
            }

            return $v === null ? null : ['raw' => $v];
        };

        $code = (string) ($result['code'] ?? '');
        $code = $code === '' ? null : mb_substr($code, 0, 8);
        $message = (string) ($result['message'] ?? '');
        $message = $message === '' ? null : mb_substr($message, 0, 255);

        $attributes = [
            'gateway_request' => $toArray($result['raw_request'] ?? null),
            'gateway_response' => $toArray($result['raw_response'] ?? null),
            'gateway_resp_code' => $code,
            'gateway_message' => $message,
            'status' => $result['ok'] ? 'CONFIRMED' : 'REGISTERED',
        ];

        $updated = $this->update($payment, $attributes);

        Log::info('payment.verify.saved', [
            'payment_id' => (int) $paymentId,
            'new_status' => (string) ($updated->getAttribute('status') ?? ''),
            'gateway_resp_code' => (string) ($updated->getAttribute('gateway_resp_code') ?? ''),
        ]);

        return $this->toRow($updated);
    }

    /**
     * Apply a confirmed payment. Future: generate allocations FIFO.
     */
    public function apply(int|string $paymentId): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        $status = (string) ($payment->getAttribute('status') ?? 'REGISTERED');
        if ($status !== 'CONFIRMED') {
            throw new DomainActionException('Solo pagos CONFIRMED pueden ser aplicados.');
        }

        // Require at least one allocation before marking APPLIED
        $totalApplied = (int) PaymentAllocation::query()->where('payment_id', (int) $payment->getKey())->sum('amount_bs_minor');
        if ($totalApplied <= 0) {
            throw new DomainActionException('No hay asignaciones para aplicar.');
        }

        // Mark APPLIED when allocations exist (cruce ya efectuado)
        $updated = $this->update($payment, [
            'status' => 'APPLIED',
        ]);

        return $this->toRow($updated);
    }

    public function resolveFxId(string $currencyCode, \DateTimeInterface $paidOn): ?int
    {
        /** @var FxRateServiceInterface $fx */
        $fx = $this->container->get(FxRateServiceInterface::class);
        $rate = $fx->resolveAt($currencyCode, $paidOn);

        return $rate?->getAttribute('id');
    }

    /**
     * @param  array<int, array{charge_id:int, amount_bs_minor:int}>  $items
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function storeAllocations(int|string $paymentId, array $items, array $options = []): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        // Normalize and compute idempotency payload hash (BEFORE guards to allow idempotent retries)
        $normalized = array_map(static fn ($it) => [
            'charge_id' => (int) $it['charge_id'],
            'amount_bs_minor' => (int) $it['amount_bs_minor'],
        ], $items);
        usort($normalized, static fn ($a, $b) => $a['charge_id'] <=> $b['charge_id']);
        $payloadHash = hash('sha256', json_encode($normalized));
        $idempoKeyIn = (string) ($options['idempotency_key'] ?? '');
        $cacheKey = $idempoKeyIn !== '' ? ('payments:allocations:'.$payment->getKey().':'.$idempoKeyIn.':'.$payloadHash) : null;
        if ($cacheKey && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            Log::info('payments.allocations.idempotent_hit', [
                'payment_id' => (int) $payment->getKey(),
                'cache_key' => $cacheKey,
            ]);

            return ['payment_id' => (int) $payment->getKey(), 'status' => (string) ($payment->getAttribute('status') ?? '')];
        }

        // Guard: payment must be CONFIRMED
        $status = (string) ($payment->getAttribute('status') ?? 'REGISTERED');
        if ($status !== 'CONFIRMED') {
            throw new DomainActionException('Solo pagos CONFIRMED pueden aplicar asignaciones.');
        }

        $useCredit = (bool) ($options['use_credit'] ?? false);
        $paidOn = \Illuminate\Support\Carbon::parse((string) $payment->getAttribute('paid_on'));

        // Determine allowed debtor domain for charges
        $allowedLocalIds = [];
        if ((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE') {
            $concessionaireId = (int) $payment->getAttribute('debtor_id');
            $allowedLocalIds = \DB::table('concessionaire_contract as cc')
                ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                ->join('locals as l', 'l.id', '=', 'cl.local_id')
                ->where('cc.concessionaire_id', $concessionaireId)
                ->whereNull('c.deleted_at')
                ->whereNull('l.deleted_at')
                ->whereDate('c.start_date', '<=', $paidOn->toDateString())
                ->where(function ($q) use ($paidOn) {
                    $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $paidOn->toDateString());
                })
                ->pluck('l.id')->unique()->values()->all();
        }
        // Collectable statuses
        $collectableIds = [];
        try {
            $collectableIds = \App\Models\ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
        } catch (\Throwable $e) {
            $collectableIds = [];
        }

        $ids = array_column($normalized, 'charge_id');
        $charges = \App\Models\Charge::query()
            ->whereIn('id', $ids)
            ->get(['id', 'debtor_type', 'debtor_id', 'local_id', 'charge_status_id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on']);
        $byId = $charges->keyBy('id');

        // Pre-validation
        $errors = [];
        foreach ($normalized as $it) {
            $cid = (int) $it['charge_id'];
            /** @var null|\App\Models\Charge $c */
            $c = $byId->get($cid);
            if (! $c) {
                $errors[] = "Charge {$cid} no existe.";

                continue;
            }
            $statusId = (int) ($c->getAttribute('charge_status_id') ?? 0);
            if (! empty($collectableIds) && ! in_array($statusId, $collectableIds, true)) {
                $errors[] = "Charge {$cid} no está en estado cobrable.";
            }
            $cDebtorType = (string) ($c->getAttribute('debtor_type') ?? '');
            $cDebtorId = (int) ($c->getAttribute('debtor_id') ?? 0);
            if ((string) $payment->getAttribute('debtor_type') === 'LOCAL') {
                if (! ($cDebtorType === 'LOCAL' && $cDebtorId === (int) $payment->getAttribute('debtor_id'))) {
                    $errors[] = "Charge {$cid} no pertenece al deudor del pago.";
                }
            } else { // CONCESSIONAIRE
                if (! ($cDebtorType === 'LOCAL' && in_array($cDebtorId, $allowedLocalIds, true))) {
                    $errors[] = "Charge {$cid} no pertenece al dominio de locales del concesionario.";
                }
            }
        }
        if (! empty($errors)) {
            throw new DomainActionException(implode(' ', $errors));
        }

        $appliedNow = DB::transaction(function () use ($payment, $normalized, $useCredit, $paidOn, $cacheKey) {
            $didSetApplied = false;
            Log::info('payments.allocations.begin', [
                'payment_id' => (int) $payment->getKey(),
                'items_count' => count($normalized),
            ]);

            // Lock payment row to avoid concurrent modifications
            DB::table('payments')->where('id', $payment->getKey())->lockForUpdate()->first();

            /** @var FxRateServiceInterface $fx */
            $fx = $this->container->get(FxRateServiceInterface::class);

            // Available from payment funds
            $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
            $currentAssigned = (int) \App\Models\PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
            $available = max(0, $amountPayment - $currentAssigned);

            $applied = 0; // from payment funds
            $creditUsed = 0;

            // Preload open credits if needed
            $credits = collect();
            if ($useCredit) {
                $credits = \App\Models\CustomerCredit::query()
                    ->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                    ->where('debtor_id', (int) $payment->getAttribute('debtor_id'))
                    ->where('status', 'OPEN')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $touched = [];
            foreach ($normalized as $it) {
                $amt = (int) $it['amount_bs_minor'];
                if ($amt <= 0) {
                    continue;
                }
                /** @var \App\Models\Charge|null $charge */
                $charge = \App\Models\Charge::query()->find((int) $it['charge_id']);
                if (! $charge) {
                    continue;
                }
                $touched[] = (int) $charge->getKey();

                // Allocate from payment funds first
                $fromPayment = min($amt, $available);
                if ($fromPayment > 0) {
                    $existing = \App\Models\PaymentAllocation::query()
                        ->where('payment_id', (int) $payment->getKey())
                        ->where('charge_id', (int) $it['charge_id'])
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        $existing->increment('amount_bs_minor', $fromPayment);
                    } else {
                        (new \App\Models\PaymentAllocation([
                            'payment_id' => (int) $payment->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'local_id' => (int) $charge->getAttribute('local_id'),
                            'debtor_type' => (string) $charge->getAttribute('debtor_type'),
                            'debtor_id' => (int) $charge->getAttribute('debtor_id'),
                            'amount_bs_minor' => $fromPayment,
                        ]))->save();
                    }
                    $applied += $fromPayment;
                    $available -= $fromPayment;
                }

                // Remainder from credits
                $remain = $amt - $fromPayment;
                if ($remain > 0 && $useCredit && $credits->isNotEmpty()) {
                    $needed = $remain;
                    foreach ($credits as $credit) {
                        if ($needed <= 0) {
                            break;
                        }
                        $bal = (int) $credit->getAttribute('balance_minor');
                        if ($bal <= 0) {
                            continue;
                        }
                        $use = min($bal, $needed);
                        (new \App\Models\CreditApplication([
                            'customer_credit_id' => (int) $credit->getKey(),
                            'payment_id' => (int) $payment->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'amount_minor' => (int) $use,
                        ]))->save();
                        $credit->decrement('balance_minor', $use);
                        if (((int) $credit->getAttribute('balance_minor')) <= 0) {
                            $credit->setAttribute('status', 'CLOSED');
                            $credit->save();
                        }
                        $needed -= $use;
                        $creditUsed += $use;
                    }
                }
            }

            // Recompute available after applying
            $totalApplied = (int) \App\Models\PaymentAllocation::query()->where('payment_id', (int) $payment->getKey())->sum('amount_bs_minor');
            $amountBs = (int) ($payment->getAttribute('amount_bs_minor') ?? 0);
            $afterAvailable = max(0, $amountBs - $totalApplied);

            $createdCredit = false;
            // If leftover and no open charges remain, create customer credit (VES)
            if ($afterAvailable > 0) {
                // Build base query similar to openCharges()
                if ((string) $payment->getAttribute('debtor_type') === 'CONCESSIONAIRE') {
                    $concessionaireId = (int) $payment->getAttribute('debtor_id');
                    $locals = \DB::table('concessionaire_contract as cc')
                        ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
                        ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
                        ->join('locals as l', 'l.id', '=', 'cl.local_id')
                        ->where('cc.concessionaire_id', $concessionaireId)
                        ->whereNull('c.deleted_at')
                        ->whereNull('l.deleted_at')
                        ->whereDate('c.start_date', '<=', $paidOn->toDateString())
                        ->where(function ($q) use ($paidOn) {
                            $q->whereNull('c.end_date')->orWhereDate('c.end_date', '>=', $paidOn->toDateString());
                        })
                        ->pluck('l.id')->unique()->values()->all();
                    $cq = \App\Models\Charge::query()->where('debtor_type', 'LOCAL')->whereIn('debtor_id', $locals);
                } else {
                    $cq = \App\Models\Charge::query()->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                        ->where('debtor_id', (int) $payment->getAttribute('debtor_id'));
                }
                try {
                    $ids = \App\Models\ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
                    if (! empty($ids)) {
                        $cq->whereIn('charge_status_id', $ids);
                    }
                } catch (\Throwable $e) {
                }
                $charges = $cq->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued']);
                $ids = $charges->pluck('id')->all();
                $allocByCharge = \App\Models\PaymentAllocation::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_bs_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
                $creditByCharge = \App\Models\CreditApplication::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
                $sumOutstanding = 0;
                foreach ($charges as $c) {
                    $currency = (string) ($c->getAttribute('currency') ?? '');
                    $amountMinor = (int) $c->getAttribute('amount_minor');
                    $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
                    $amountBsMinor = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
                    if ($amountBsMinor === null) {
                        $rate = $fx->resolveAt($currency, $paidOn);
                        $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                        $amountBsMinor = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : null;
                    }
                    $allocated = (int) ($allocByCharge[(int) $c->getAttribute('id')] ?? 0);
                    $credited = (int) ($creditByCharge[(int) $c->getAttribute('id')] ?? 0);
                    $outstanding = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : 0;
                    $sumOutstanding += $outstanding;
                }
                if ($sumOutstanding === 0) {
                    (new \App\Models\CustomerCredit([
                        'debtor_type' => (string) $payment->getAttribute('debtor_type'),
                        'debtor_id' => (int) $payment->getAttribute('debtor_id'),
                        'source_payment_id' => (int) $payment->getKey(),
                        'currency' => 'VES',
                        'balance_minor' => (int) $afterAvailable,
                        'status' => 'OPEN',
                        'created_from' => 'overpayment',
                    ]))->save();
                    $createdCredit = true;
                }
            }

            // Update charge statuses for touched charges (ISSUED -> PARTIAL/SETTLED)
            if (! empty($touched)) {
                $statusIds = [
                    'ISSUED' => (int) (\App\Models\ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0),
                    'PARTIAL' => (int) (\App\Models\ChargeStatus::query()->where('code', 'PARTIAL')->value('id') ?? 0),
                    'SETTLED' => (int) (\App\Models\ChargeStatus::query()->where('code', 'SETTLED')->value('id') ?? 0),
                ];
                $chargesTouched = \App\Models\Charge::query()->whereIn('id', $touched)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'charge_status_id']);
                foreach ($chargesTouched as $c) {
                    $cid = (int) $c->getAttribute('id');
                    $amountMinor = (int) $c->getAttribute('amount_minor');
                    $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
                    $baseline = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
                    if ($baseline === null) {
                        $currency = (string) $c->getAttribute('currency');
                        $rate = $this->container->get(FxRateServiceInterface::class)->resolveAt($currency, $paidOn);
                        $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                        $baseline = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : 0;
                    }
                    $allocated = (int) \App\Models\PaymentAllocation::query()->where('charge_id', $cid)->sum('amount_bs_minor');
                    $credited = (int) \App\Models\CreditApplication::query()->where('charge_id', $cid)->sum('amount_minor');
                    $outstanding = max(0, $baseline - $allocated - $credited);
                    $newStatusId = (int) $c->getAttribute('charge_status_id');
                    if ($outstanding === 0) {
                        $newStatusId = $statusIds['SETTLED'] ?: $newStatusId;
                        \App\Models\Charge::query()->where('id', $cid)->update(['charge_status_id' => $newStatusId, 'settled_on' => $paidOn->toDateString()]);
                    } else {
                        if (($allocated + $credited) > 0) {
                            $newStatusId = $statusIds['PARTIAL'] ?: $newStatusId;
                            \App\Models\Charge::query()->where('id', $cid)->update(['charge_status_id' => $newStatusId]);
                        }
                    }
                }
            }

            // Final status transition: mark APPLIED when full distribution done (no available left OR leftover converted to credit with no open charges)
            if (($afterAvailable === 0 && $totalApplied > 0) || $createdCredit) {
                $payment->setAttribute('status', 'APPLIED');
                $payment->save();
                $didSetApplied = true;
            }

            if ($cacheKey) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 15 * 60);
            }

            return $didSetApplied;
        });

        if ($appliedNow) {
            DB::afterCommit(function () use ($payment) {
                try {
                    $svc = app(\App\Contracts\Services\ReceiptServiceInterface::class);
                    $svc->issue((int) $payment->getKey());
                    $svc->issueByPaymentPerCharge((int) $payment->getKey());
                } catch (\Throwable $e) {
                    \Log::error('receipt.issue.failed', [
                        'payment_id' => (int) $payment->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }

        return $this->toRow($payment->fresh());
    }

    public function createAndVerify(array $attributes, ?array $auditContext = null): array
    {
        $methodRaw = (string) ($attributes['method'] ?? '');
        $method = strtoupper(trim($methodRaw));
        $method = match ($method) {
            'TRANSFER' => 'TRF',
            'PAGOMOVIL', 'PAGO MOVIL', 'PAGO-MOVIL' => 'PMOV',
            default => $method,
        };

        $hasCredentials = (string) config('services.bank_gateway.key') !== ''
            && (string) config('services.bank_gateway.secret') !== ''
            && (string) config('services.bank_gateway.merchant_id') !== ''
            && (string) config('services.bank_gateway.terminal_id') !== '';

        if ($method !== 'DEB' && ! $hasCredentials) {
            try {
                Audit::query()->create([
                    'event' => 'payment.verify_failed',
                    'auditable_type' => Payment::class,
                    'auditable_id' => 0,
                    'new_values' => [
                        'reason' => 'missing_credentials',
                        'input' => $attributes,
                    ],
                    'url' => (string) ($auditContext['url'] ?? ''),
                    'ip_address' => (string) ($auditContext['ip'] ?? ''),
                    'user_agent' => (string) ($auditContext['ua'] ?? ''),
                    'tags' => 'payment',
                ]);
            } catch (\Throwable $e) {
            }

            throw new DomainActionException('No se puede registrar pagos por TRANSFERENCIA o PAGO MÓVIL sin verificación del banco.');
        }

        try {
            DB::beginTransaction();
            /** @var \App\Models\Payment $payment */
            $payment = $this->create($attributes);

            if ($method === 'DEB') {
                $res = $this->verify($payment->getKey());
                DB::commit();

                return $res;
            }

            $res = $this->verify($payment->getKey());
            $status = (string) ($res['status'] ?? 'REGISTERED');
            if ($status !== 'CONFIRMED') {
                $code = (string) ($res['gateway_resp_code'] ?? ($res['code'] ?? ''));
                $desc = (string) ($res['gateway_message'] ?? ($res['message'] ?? ''));
                DB::rollBack();
                try {
                    Audit::query()->create([
                        'event' => 'payment.verify_failed',
                        'auditable_type' => Payment::class,
                        'auditable_id' => 0,
                        'new_values' => [
                            'code' => $code,
                            'message' => $desc,
                            'input' => $attributes,
                            'gateway_request' => $res['gateway_request'] ?? null,
                            'gateway_response' => $res['gateway_response'] ?? null,
                        ],
                        'url' => (string) ($auditContext['url'] ?? ''),
                        'ip_address' => (string) ($auditContext['ip'] ?? ''),
                        'user_agent' => (string) ($auditContext['ua'] ?? ''),
                        'tags' => 'payment',
                    ]);
                } catch (\Throwable $e) {
                }

                $msg = trim('No validado. Código '.$code.($desc !== '' ? ' – '.$desc : '').'. El pago no fue registrado.');
                throw new DomainActionException($msg);
            }

            DB::commit();

            return $res;
        } catch (\Illuminate\Database\QueryException $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $ignore) {
            }
            try {
                $companyId = (int) ($attributes['company_bank_account_id'] ?? 0);
                $originBankId = (int) ($attributes['origin_bank_id'] ?? 0);
                $amountMinor = (int) ($attributes['amount_bs_minor'] ?? 0);
                $paidOn = (string) ($attributes['paid_on'] ?? '');
                $refDigits = preg_replace('/\D+/', '', (string) ($attributes['reference'] ?? '')) ?? '';
                if ($method === 'PMOV') {
                    $phoneIn = (string) ($attributes['payer_phone_e164'] ?? '');
                    $digits = preg_replace('/\D+/', '', $phoneIn) ?? '';
                    if ($digits !== '' && str_starts_with($digits, '0') && strlen($digits) === 11) {
                        $digits = '58'.substr($digits, 1, 10);
                    }
                    $fp = [
                        'm' => 'PMOV', 'c' => $companyId, 'o' => $originBankId, 'p' => $digits, 'r' => $refDigits, 'a' => $amountMinor, 'd' => $paidOn, 't' => '300',
                    ];
                } elseif ($method === 'DEB') {
                    $fp = [
                        'm' => 'DEB', 'c' => $companyId, 'r' => $refDigits, 'a' => $amountMinor, 'd' => $paidOn, 't' => 'DEB',
                    ];
                } else {
                    $acct = preg_replace('/\D+/', '', (string) ($attributes['payer_account_number'] ?? '')) ?? '';
                    $fp = [
                        'm' => ($method !== '' ? $method : 'TRF'), 'c' => $companyId, 'o' => $originBankId, 'a20' => $acct, 'r' => $refDigits, 'a' => $amountMinor, 'd' => $paidOn, 't' => '211',
                    ];
                }
                $key = hash('sha256', json_encode($fp, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
                $existing = Payment::query()->where('idempotency_key', $key)->orderByDesc('id')->first();
                try {
                    Audit::query()->create([
                        'event' => 'payment.idempotent_duplicate',
                        'auditable_type' => Payment::class,
                        'auditable_id' => $existing?->getKey() ?? 0,
                        'new_values' => [
                            'idempotency_key' => $key,
                            'existing_payment_id' => $existing?->getKey(),
                            'existing_status' => $existing?->status,
                        ],
                        'url' => (string) ($auditContext['url'] ?? ''),
                        'ip_address' => (string) ($auditContext['ip'] ?? ''),
                        'user_agent' => (string) ($auditContext['ua'] ?? ''),
                        'tags' => 'payment',
                    ]);
                } catch (\Throwable $e2) {
                }
            } catch (\Throwable $e2) {
            }

            throw new DomainActionException('Este pago ya fue registrado.');
        } catch (DomainActionException $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $ignore) {
            }
            throw $e;
        } catch (\Throwable $e) {
            try {
                DB::rollBack();
            } catch (\Throwable $ignore) {
            }
            Log::error('payment.create_and_verify.unhandled', ['error' => $e->getMessage()]);
            throw new DomainActionException('No fue posible registrar el pago.');
        }
    }
}
