<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\BankGatewayInterface;
use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Enums\ChargeStatusCode;
use App\Exceptions\DomainActionException;
use App\Models\Audit;
use App\Models\Charge;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
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

            // EXO: do not auto-generate reference; keep as empty if not provided and ensure key exists for DB
            if ($method === 'EXO' && ! array_key_exists('reference', $attributes)) {
                $attributes['reference'] = '';
                $reference = '';
                $refDigits = '';
            }

            // EXO: if no company account is provided, select the first available (internal backend default)
            if ($method === 'EXO' && $companyId <= 0) {
                try {
                    $firstAcc = \App\Models\CompanyBankAccount::query()->orderBy('id')->first();
                    if ($firstAcc) {
                        $companyId = (int) $firstAcc->getKey();
                        $attributes['company_bank_account_id'] = $companyId;
                    }
                } catch (\Throwable $e) {
                }
            }

            // If EXO/DEB and missing origin bank, fallback to company's bank (DB requires FK)
            if (in_array($method, ['EXO', 'DEB'], true) && $originBankId <= 0 && $companyId > 0) {
                try {
                    $acc = \App\Models\CompanyBankAccount::query()->find($companyId);
                    $bk = $acc?->getAttribute('bank_id');
                    if ($bk) {
                        $attributes['origin_bank_id'] = (int) $bk;
                        $originBankId = (int) $bk;
                    }
                } catch (\Throwable $e) {
                }
            }

            // If EXO and reason provided, stash into payer_details JSON
            if ($method === 'EXO') {
                // If payer document not provided, set neutral defaults (schema requires non-null number)
                if (empty($attributes['payer_document_number'])) {
                    $attributes['payer_document_type'] = $attributes['payer_document_type'] ?? 'G';
                    $attributes['payer_document_number'] = '00000000';
                }
                $reason = (string) ($attributes['exoneration_reason'] ?? '');
                if ($reason !== '') {
                    $pd = $attributes['payer_details'] ?? [];
                    if (! is_array($pd)) {
                        $pd = ['raw' => (string) $pd];
                    }
                    $pd['exoneration_reason'] = $reason;
                    $attributes['payer_details'] = $pd;
                }
            }

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
            } elseif ($method === 'EXO') {
                $fingerprint = [
                    'm' => 'EXO',
                    'c' => $companyId,
                    'r' => $refDigits,
                    'a' => $amountMinor,
                    'd' => $paidOn,
                    't' => 'EXO',
                    'note' => (string) ($attributes['exoneration_reason'] ?? ''),
                    'debtor' => [
                        'type' => (string) ($attributes['debtor_type'] ?? ''),
                        'id' => (int) ($attributes['debtor_id'] ?? 0),
                    ],
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
                $existingKey = null;
                if (array_key_exists('idempotency_key', $attributes)) {
                    $existingKey = trim((string) ($attributes['idempotency_key'] ?? ''));
                }
                if ($existingKey === null || $existingKey === '') {
                    $key = hash('sha256', $json);

                    // If a payment was soft-deleted, allow re-register by avoiding unique collision.
                    // We only change the key when the collision is with deleted rows.
                    try {
                        $existsActiveNonVoided = Payment::query()
                            ->where('idempotency_key', $key)
                            ->whereNull('deleted_at')
                            ->whereNull('voided_at')
                            ->exists();
                        if (! $existsActiveNonVoided) {
                            $existsRecreatable = Payment::withTrashed()
                                ->where('idempotency_key', $key)
                                ->where(function ($q): void {
                                    $q->whereNotNull('deleted_at')
                                        ->orWhereNotNull('voided_at');
                                })
                                ->exists();
                            if ($existsRecreatable) {
                                // Ensure we don't collide with previous recreate attempts (also unique across soft-deletes).
                                $suffix = 'recreate';
                                for ($i = 0; $i < 20; $i++) {
                                    $candidate = hash('sha256', $json.'|'.$suffix);

                                    // Check if candidate collides with ANY payment (active or soft-deleted)
                                    $candidateExists = Payment::withTrashed()->where('idempotency_key', $candidate)->exists();
                                    if (! $candidateExists) {
                                        $key = $candidate;
                                        break;
                                    }

                                    $suffix = 'recreate'.($i + 2);
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        // keep original key
                    }

                    $attributes['idempotency_key'] = $key;
                }
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
        $totalApplied = (int) PaymentAllocation::query()
            ->where('payment_id', (int) $model->getKey())
            ->whereNull('deleted_at')
            ->sum('amount_bs_minor');
        $amountBs = (int) ($model->getAttribute('amount_bs_minor') ?? 0);
        $availableBs = max(0, $amountBs - $totalApplied);

        // Crédito a favor generado específicamente por este pago
        $creditFromPayment = 0;
        try {
            $creditFromPayment = (int) \App\Models\CustomerCredit::query()
                ->where('source_payment_id', (int) $model->getKey())
                ->whereNull('deleted_at')
                ->where('status', 'OPEN')
                ->sum('balance_minor');

            // Si el pago ya generó un crédito a favor, consideramos que todo el
            // remanente fue convertido en crédito y no debe mostrarse como
            // "disponible" en el propio pago (para evitar doble conteo en show/apply).
            if ($creditFromPayment > 0) {
                $availableBs = 0;
            }
        } catch (\Throwable $e) {
            // si el catálogo de créditos no está disponible, dejamos availableBs tal cual
        }

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
            'payer_document_type' => $documentTypeCode, // Use resolved code instead of model attribute
            'document_type_code' => $documentTypeCode,
            'payer_document_number' => $model->getAttribute('payer_document_number'),
            'payer_account_number' => $model->getAttribute('payer_account_number'),
            'payer_phone_e164' => $model->getAttribute('payer_phone_e164'),
            'reference' => $model->getAttribute('reference'),
            'amount_bs_minor' => $model->getAttribute('amount_bs_minor'),
            // Expose applied/available/credit-from-payment for FE summary
            'applied_bs_minor' => $totalApplied,
            'available_bs_minor' => $availableBs,
            'credit_from_payment_bs_minor' => $creditFromPayment,
            'paid_on' => $model->getAttribute('paid_on'),
            'fx_rate_id' => $model->getAttribute('fx_rate_id'),
            'status' => $model->getAttribute('status'),
            'voided_at' => $model->getAttribute('voided_at'),
            'voided_by_user_id' => $model->getAttribute('voided_by_user_id'),
            'void_reason' => $model->getAttribute('void_reason'),
            'gateway_request' => $model->getAttribute('gateway_request'),
            'gateway_response' => $model->getAttribute('gateway_response'),
            'gateway_resp_code' => $model->getAttribute('gateway_resp_code'),
            'gateway_message' => $model->getAttribute('gateway_message'),
            'payer_details' => $model->getAttribute('payer_details'),
            'idempotency_key' => $model->getAttribute('idempotency_key'),
            'exoneration_reason' => $model->getAttribute('exoneration_reason'),
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

        // Auto-confirm for debit and exoneration (no external gateway)
        if (in_array(strtoupper($methodCode), ['DEB', 'EXO'], true)) {
            \Log::info('payment.verify.auto_confirm_debit', [
                'payment_id' => (int) $paymentId,
            ]);
            $attributes = [
                'gateway_request' => ['note' => strtoupper($methodCode) === 'EXO' ? 'Auto-verify for exoneration.' : 'Auto-verify for debit (POS).'],
                'gateway_response' => ['sRespCode' => '00', 'sRespDesc' => strtoupper($methodCode) === 'EXO' ? 'Autoverificación (exoneración).' : 'Autoverificación (tarjeta débito).'],
                'gateway_resp_code' => '00',
                'gateway_message' => strtoupper($methodCode) === 'EXO' ? 'Autoverificación (exoneración).' : 'Autoverificación (tarjeta débito).',
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

        // Log gateway outcome with sanitized snippets (avoid PII/secrets)
        $rawReq = $result['raw_request'] ?? null;
        $rawRes = $result['raw_response'] ?? null;
        $showSreqId = (bool) config('services.bank_gateway.log_show_sreqid', false);
        $mask = static function (?string $s, int $keep = 4): ?string {
            if (! is_string($s) || $s === '') {
                return $s;
            }
            $len = mb_strlen($s);
            if ($len <= $keep) {
                return str_repeat('*', $len);
            }

            return str_repeat('*', max(0, $len - $keep)).mb_substr($s, -$keep);
        };
        $sanitizeJson = static function (?string $json) use ($mask, $showSreqId): ?string {
            if (! is_string($json) || $json === '') {
                return null;
            }
            $data = json_decode($json, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Mask common sensitive fields if present
                foreach (['sFromAcctNo', 'sToAcctNo', 'sDocumentId', 'payer_email'] as $k) {
                    if (isset($data[$k]) && is_string($data[$k])) {
                        $data[$k.'_masked'] = $mask($data[$k]);
                        unset($data[$k]);
                    }
                }
                // Redact probable secrets/tokens
                foreach (['sReqId', 'token', 'session', 'signature', 'x-signature'] as $k) {
                    if (isset($data[$k])) {
                        $val = is_string($data[$k]) ? $data[$k] : json_encode($data[$k]);
                        if ($k === 'sReqId' && $showSreqId && is_string($val)) {
                            // Keep original sReqId, but add hash for correlation
                            $data['sReqId_hash'] = substr(hash('sha256', $val), 0, 16);
                            // leave sReqId as-is
                        } else {
                            $data[$k] = '[redacted]';
                            $data[$k.'_hash'] = is_string($val) ? substr(hash('sha256', $val), 0, 16) : null;
                        }
                    }
                }

                return mb_substr(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 0, 1024);
            }
            // Fallback: best-effort regex masking for raw strings
            $san = $json;
            $tmp = preg_replace('/("sFromAcctNo"\s*:\s*")[^"]+("\s*)/i', '$1[masked]$2', $san);
            $san = is_string($tmp) ? $tmp : $san;
            $tmp = preg_replace('/("sToAcctNo"\s*:\s*")[^"]+("\s*)/i', '$1[masked]$2', $san);
            $san = is_string($tmp) ? $tmp : $san;
            $tmp = preg_replace('/("sDocumentId"\s*:\s*")[^"]+("\s*)/i', '$1[masked]$2', $san);
            $san = is_string($tmp) ? $tmp : $san;
            if (! $showSreqId) {
                $tmp = preg_replace('/("sReqId"\s*:\s*")[^"]+("\s*)/i', '$1[redacted]$2', $san);
                $san = is_string($tmp) ? $tmp : $san;
            }

            return mb_substr($san, 0, 1024);
        };
        $reqSnippet = $sanitizeJson(is_string($rawReq) ? $rawReq : null);
        $resSnippet = $sanitizeJson(is_string($rawRes) ? $rawRes : null);

        // Extract ReqId separately if enabled
        $reqId = null;
        $reqIdHash = null;
        if (is_string($rawRes) && $rawRes !== '') {
            $parsed = json_decode($rawRes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $val = $parsed['sReqId'] ?? null;
                if (is_string($val) && $val !== '') {
                    $reqIdHash = substr(hash('sha256', $val), 0, 16);
                    if ($showSreqId) {
                        $reqId = $val;
                    }
                }
            }
        }
        Log::info('payment.verify.gateway_result', [
            'payment_id' => (int) $paymentId,
            'ok' => (bool) $result['ok'],
            'code' => (string) ($result['code'] ?? ''),
            'message' => (string) ($result['message'] ?? ''),
            'raw_request_len' => is_string($rawReq) ? mb_strlen($rawReq) : null,
            'raw_response_len' => is_string($rawRes) ? mb_strlen($rawRes) : null,
            'raw_request_snippet' => $reqSnippet,
            'raw_response_snippet' => $resSnippet,
            'ReqId' => $reqId,
            'ReqIdHash' => $reqIdHash,
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

        // Persist outcome (both success and failure). On failure, status remains REGISTERED.
        if (! (bool) $result['ok']) {
            $updated = $this->update($payment, $attributes);
            Log::info('payment.verify.saved_failed', [
                'payment_id' => (int) $paymentId,
                'new_status' => (string) ($updated->getAttribute('status') ?? ''),
                'gateway_resp_code' => (string) ($updated->getAttribute('gateway_resp_code') ?? ''),
            ]);

            $row = $this->toRow($updated);
            if ($reqId !== null) {
                $row['req_id'] = $reqId;
                $row['req_id_hash'] = $reqIdHash;
            }

            return $row;
        }

        // Persist success case
        $updated = $this->update($payment, $attributes);

        Log::info('payment.verify.saved', [
            'payment_id' => (int) $paymentId,
            'new_status' => (string) ($updated->getAttribute('status') ?? ''),
            'gateway_resp_code' => (string) ($updated->getAttribute('gateway_resp_code') ?? ''),
        ]);

        $row = $this->toRow($updated);
        if ($reqId !== null) {
            $row['req_id'] = $reqId;
            $row['req_id_hash'] = $reqIdHash;
        }

        return $row;
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
        $totalApplied = (int) PaymentAllocation::query()
            ->where('payment_id', (int) $payment->getKey())
            ->whereNull('deleted_at')
            ->sum('amount_bs_minor');
        if ($totalApplied <= 0) {
            throw new DomainActionException('No hay asignaciones para aplicar.');
        }

        // Mark APPLIED when allocations exist (cruce ya efectuado)
        $updated = $this->update($payment, [
            'status' => 'APPLIED',
        ]);

        return $this->toRow($updated);
    }

    /**
     * @param  array{reason?: string}  $options
     * @return array<string, mixed>
     */
    public function void(int|string $paymentId, array $options = []): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        $status = strtoupper((string) ($payment->getAttribute('status') ?? ''));
        if ($status === 'VOID') {
            throw new DomainActionException('El pago ya fue anulado (VOID).');
        }
        if ($status !== 'APPLIED') {
            throw new DomainActionException('Solo pagos APPLIED pueden ser anulados.');
        }

        $methodCode = strtoupper((string) ($payment->getAttribute('method') ?? ''));
        if ($methodCode === '') {
            try {
                $ptId = (int) ($payment->getAttribute('payment_type_id') ?? 0);
                if ($ptId > 0) {
                    /** @var null|\App\Models\PaymentType $pt */
                    $pt = \App\Models\PaymentType::query()->find($ptId);
                    $methodCode = strtoupper((string) ($pt?->getAttribute('code') ?? ''));
                }
            } catch (\Throwable $e) {
                $methodCode = '';
            }
        }
        if (! in_array($methodCode, ['DEB', 'EXO'], true)) {
            throw new DomainActionException('Solo pagos manuales (Débito/Exonerado) pueden anularse por esta vía.');
        }

        $reason = trim((string) ($options['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Anulación administrativa';
        }

        $userId = null;
        try {
            $userId = auth()->id();
        } catch (\Throwable $e) {
            $userId = null;
        }

        return DB::transaction(function () use ($payment, $reason, $userId) {
            $pid = (int) $payment->getKey();

            DB::table('payments')->where('id', $pid)->lockForUpdate()->first();
            $payment->refresh();

            $lockedStatus = strtoupper((string) ($payment->getAttribute('status') ?? ''));
            if ($lockedStatus === 'VOID') {
                throw new DomainActionException('El pago ya fue anulado (VOID).');
            }
            if ($lockedStatus !== 'APPLIED') {
                throw new DomainActionException('Solo pagos APPLIED pueden ser anulados.');
            }

            $paidOn = Carbon::parse((string) ($payment->getAttribute('paid_on') ?? now()->toDateString()));
            $now = now();

            $allocChargeIds = PaymentAllocation::query()
                ->where('payment_id', $pid)
                ->whereNull('deleted_at')
                ->pluck('charge_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $creditApps = CreditApplication::query()
                ->where('payment_id', $pid)
                ->lockForUpdate()
                ->get(['id', 'customer_credit_id', 'charge_id', 'amount_minor']);

            $creditChargeIds = $creditApps
                ->pluck('charge_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $restoreByCredit = [];
            foreach ($creditApps as $app) {
                $ccId = (int) ($app->getAttribute('customer_credit_id') ?? 0);
                $amt = (int) ($app->getAttribute('amount_minor') ?? 0);
                if ($ccId <= 0 || $amt <= 0) {
                    continue;
                }
                $restoreByCredit[$ccId] = ($restoreByCredit[$ccId] ?? 0) + $amt;
            }

            if (! empty($restoreByCredit)) {
                $creditIds = array_keys($restoreByCredit);
                $credits = CustomerCredit::query()
                    ->whereIn('id', $creditIds)
                    ->lockForUpdate()
                    ->get(['id', 'balance_minor', 'status']);

                $found = $credits->pluck('id')->map(fn ($id) => (int) $id)->all();
                $missing = array_diff($creditIds, $found);
                if (! empty($missing)) {
                    throw new DomainActionException('No fue posible restaurar créditos: faltan registros.');
                }

                foreach ($credits as $credit) {
                    $cid = (int) $credit->getKey();
                    $inc = (int) ($restoreByCredit[$cid] ?? 0);
                    if ($inc <= 0) {
                        continue;
                    }
                    $curBal = (int) ($credit->getAttribute('balance_minor') ?? 0);
                    $newBal = $curBal + $inc;
                    $credit->setAttribute('balance_minor', $newBal);
                    $credit->setAttribute('status', $newBal > 0 ? 'OPEN' : 'USED');
                    $credit->save();
                }
            }

            $createdCredits = CustomerCredit::query()
                ->where('source_payment_id', $pid)
                ->lockForUpdate()
                ->get(['id']);

            foreach ($createdCredits as $cc) {
                $ccid = (int) $cc->getKey();
                $usedElsewhere = CreditApplication::query()
                    ->where('customer_credit_id', $ccid)
                    ->where('payment_id', '!=', $pid)
                    ->exists();

                if ($usedElsewhere) {
                    throw new DomainActionException('No se puede anular: el crédito generado por este pago ya fue utilizado en otra operación.');
                }
            }

            CreditApplication::query()->where('payment_id', $pid)->delete();
            PaymentAllocation::query()->where('payment_id', $pid)->delete();

            foreach ($createdCredits as $cc) {
                $cc->delete();
            }

            Receipt::query()
                ->where('payment_id', $pid)
                ->where('status', 'ACTIVE')
                ->update([
                    'status' => 'VOIDED',
                    'voided_at' => $now,
                    'voided_by_user_id' => $userId,
                    'void_reason' => $reason,
                    'updated_at' => $now,
                ]);

            $fx = $this->container->get(\App\Support\FxConversionHelper::class);

            $chargeIds = array_values(array_unique(array_merge($allocChargeIds, $creditChargeIds)));
            if (! empty($chargeIds)) {
                $issuedId = ChargeStatusCode::ISSUED->id();
                $partialId = ChargeStatusCode::PARTIAL->id();
                $settledId = ChargeStatusCode::SETTLED->id();

                $charges = Charge::query()
                    ->whereIn('id', $chargeIds)
                    ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'charge_status_id', 'settled_on']);

                foreach ($charges as $charge) {
                    $cid = (int) $charge->getKey();
                    $outstanding = $fx->chargeOutstandingVes($charge, $paidOn);

                    if ($outstanding === 0) {
                        $updates = ['charge_status_id' => $settledId];
                        if ($charge->getAttribute('settled_on') === null) {
                            $updates['settled_on'] = $paidOn->toDateString();
                        }
                        Charge::query()->where('id', $cid)->update($updates);

                        continue;
                    }

                    $allocated = (int) PaymentAllocation::query()
                        ->where('charge_id', $cid)
                        ->whereNull('deleted_at')
                        ->sum('amount_bs_minor');
                    $credited = $fx->sumCreditApplicationsVes($cid, $paidOn);

                    Charge::query()->where('id', $cid)->update([
                        'charge_status_id' => (($allocated + $credited) > 0) ? $partialId : $issuedId,
                        'settled_on' => null,
                    ]);
                }
            }

            $payment->setAttribute('status', 'VOID');
            $payment->setAttribute('voided_at', $now);
            $payment->setAttribute('voided_by_user_id', $userId);
            $payment->setAttribute('void_reason', $reason);
            $payment->save();

            return $this->toRow($payment->fresh());
        });
    }

    /**
     * @param  array{paid_on?: string, reason?: string}  $options
     * @return array{ok: bool, voided_payment_id: int, new_payment_id: int, voided: array<string, mixed>, new: array<string, mixed>}
     */
    public function voidRebook(int|string $paymentId, array $options = []): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        $paidOnNewRaw = trim((string) ($options['paid_on'] ?? ''));
        if ($paidOnNewRaw === '') {
            throw new DomainActionException('Debe indicar la nueva fecha de pago.');
        }

        $paidOnNew = Carbon::parse($paidOnNewRaw)->toDateString();

        $reason = trim((string) ($options['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'Anulación y re-registro';
        }

        return DB::transaction(function () use ($payment, $paymentId, $paidOnNew, $reason) {
            // Snapshot fields needed to re-create the payment (before void mutates it)
            $attrs = [
                'local_id' => $payment->getAttribute('local_id') ? (int) $payment->getAttribute('local_id') : null,
                'debtor_type' => (string) ($payment->getAttribute('debtor_type') ?? ''),
                'debtor_id' => (int) ($payment->getAttribute('debtor_id') ?? 0),
                'company_bank_account_id' => (int) ($payment->getAttribute('company_bank_account_id') ?? 0),
                'method' => (string) ($payment->getAttribute('method') ?? ''),
                'payment_type_id' => $payment->getAttribute('payment_type_id') ? (int) $payment->getAttribute('payment_type_id') : null,
                'origin_bank_id' => (int) ($payment->getAttribute('origin_bank_id') ?? 0),
                'payer_document_type_id' => $payment->getAttribute('payer_document_type_id') ? (int) $payment->getAttribute('payer_document_type_id') : null,
                'payer_document_number' => (string) ($payment->getAttribute('payer_document_number') ?? ''),
                'payer_account_number' => $payment->getAttribute('payer_account_number') ? (string) $payment->getAttribute('payer_account_number') : null,
                'payer_phone_e164' => $payment->getAttribute('payer_phone_e164') ? (string) $payment->getAttribute('payer_phone_e164') : null,
                'reference' => (string) ($payment->getAttribute('reference') ?? ''),
                'amount_bs_minor' => (int) ($payment->getAttribute('amount_bs_minor') ?? 0),
                'paid_on' => $paidOnNew,
                'fx_rate_id' => null,
                'exoneration_reason' => (string) ($payment->getAttribute('exoneration_reason') ?? ''),
                'idempotency_key' => hash('sha256', 'void-rebook:'.(string) $paymentId.':'.$paidOnNew),
            ];

            // Best-effort: refresh fx_rate_id for the new date (keep previous if not resolvable)
            try {
                $resolved = $this->resolveFxId('USD', new \DateTimeImmutable($paidOnNew));
                $attrs['fx_rate_id'] = $resolved;
            } catch (\Throwable $e) {
                $attrs['fx_rate_id'] = $payment->getAttribute('fx_rate_id') ? (int) $payment->getAttribute('fx_rate_id') : null;
            }

            $voided = $this->void($paymentId, ['reason' => $reason]);

            $new = $this->createAndVerify($attrs);
            $newId = (int) ($new['id'] ?? 0);

            return [
                'ok' => true,
                'voided_payment_id' => (int) $paymentId,
                'new_payment_id' => $newId,
                'voided' => $voided,
                'new' => $new,
            ];
        });
    }

    public function resolveFxId(string $currencyCode, \DateTimeInterface $paidOn): ?int
    {
        /** @var FxRateServiceInterface $fx */
        $fx = $this->container->get(FxRateServiceInterface::class);
        $rate = $fx->resolveAt($currencyCode, $paidOn);

        return $rate?->getAttribute('id');
    }

    /**
     * Before update guard: block edits based on payment status and allocations.
     * - APPLIED: no edits allowed.
     * - CONFIRMED with allocations: no edits allowed.
     * - CONFIRMED without allocations: allow only debtor_type, debtor_id, local_id.
     * - CONFIRMED DEB/EXO without allocations: allow editing amount, reference, date, payer info (manual methods).
     * - CONFIRMED PMOV/TRANSFER: not editable (bank-verified).
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        try {
            $status = strtoupper((string) ($model->getAttribute('status') ?? ''));

            \Log::info('PaymentService.beforeUpdate START', [
                'payment_id' => $model->getKey(),
                'status' => $status,
                'attributes' => $attributes,
            ]);

            if ($status === 'APPLIED') {
                throw new DomainActionException('Pagos en estado APPLIED (Conciliado) no pueden editarse.');
            }

            if ($status === 'VOID') {
                throw new DomainActionException('Pagos en estado VOID (Anulado) no pueden editarse.');
            }

            if ($status === 'CONFIRMED') {
                $allocSum = (int) PaymentAllocation::query()
                    ->where('payment_id', (int) $model->getKey())
                    ->whereNull('deleted_at')
                    ->sum('amount_bs_minor');
                if ($allocSum > 0) {
                    throw new DomainActionException('Pagos CONFIRMED con asignaciones no pueden editarse.');
                }

                // Determine method code to allow DEB amendments before apply
                $methodCode = strtoupper((string) ($model->getAttribute('method') ?? ''));
                if ($methodCode === '') {
                    try {
                        $ptId = (int) ($model->getAttribute('payment_type_id') ?? 0);
                        if ($ptId > 0) {
                            /** @var null|\App\Models\PaymentType $pt */
                            $pt = \App\Models\PaymentType::query()->find($ptId);
                            $methodCode = strtoupper((string) ($pt?->getAttribute('code') ?? ''));
                        }
                    } catch (\Throwable $e) {
                        $methodCode = '';
                    }
                }

                // Allowed fields: if DEB or EXO (manual methods), permit core editable fields; else only debtor fields
                // _version is always allowed as it's a control field for optimistic locking
                $allowed = ['debtor_type', 'debtor_id', 'local_id', '_version'];
                if (in_array($methodCode, ['DEB', 'EXO'], true)) {
                    $allowed = array_merge($allowed, [
                        // Editable fields
                        'amount_bs_minor', 'reference', 'paid_on', 'fx_rate_id',
                        'payer_document_type', 'payer_document_type_id', 'payer_document_number', 'payer_details',
                        'exoneration_reason', // for EXO method
                        // Read-only fields that frontend may send (ignored if unchanged)
                        'company_bank_account_id',
                        'method', 'payment_type_id',
                        'origin_bank_id',
                        'payer_account_number',
                        'payer_phone_e164',
                        // No cambio de método; mantener integridad
                    ]);
                }

                // Block changes to disallowed fields
                $attempted = [];
                foreach ($attributes as $key => $new) {
                    if (! in_array($key, $allowed, true)) {
                        $cur = $model->getAttribute($key);
                        if ($new !== $cur) {
                            $attempted[] = $key;
                        }
                    }
                }

                \Log::info('PaymentService.beforeUpdate VALIDATION', [
                    'payment_id' => $model->getKey(),
                    'methodCode' => $methodCode,
                    'allowed' => $allowed,
                    'attempted' => $attempted,
                ]);

                if (! empty($attempted)) {
                    throw new DomainActionException(
                        in_array($methodCode, ['DEB', 'EXO'], true)
                            ? 'Pago manual (Débito/Exonerado): solo es posible editar datos del pagador, monto, referencia y fecha antes de aplicar.'
                            : 'Pagos verificados por el banco no pueden editarse. Solo es posible cambiar el deudor.'
                    );
                }

                // Ensure only allowed keys are passed forward (optional hardening)
                foreach (array_keys($attributes) as $k) {
                    if (! in_array($k, $allowed, true)) {
                        unset($attributes[$k]);
                    }
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof DomainActionException) {
                throw $e;
            }
        }
    }

    /**
     * Store allocations for a payment using the AllocationProcessor.
     *
     * @param  array<int, array{charge_id:int, amount_bs_minor:int}>  $items
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function storeAllocations(int|string $paymentId, array $items, array $options = []): array
    {
        $start = microtime(true);

        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        // Idempotency check (BEFORE processing to allow retries)
        $cacheKey = $this->buildAllocationCacheKey($payment, $items, $options);
        if ($cacheKey && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            Log::info('payments.allocations.idempotent_hit', [
                'payment_id' => (int) $payment->getKey(),
                'cache_key' => $cacheKey,
            ]);

            Log::info('payments.allocations.latency', [
                'payment_id' => (int) $payment->getKey(),
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'stage' => 'IDEMPOTENT_HIT',
                'items_count' => count($items),
            ]);

            return ['payment_id' => (int) $payment->getKey(), 'status' => (string) ($payment->getAttribute('status') ?? '')];
        }

        // Guard: payment must be CONFIRMED
        $status = (string) ($payment->getAttribute('status') ?? 'REGISTERED');
        if ($status !== 'CONFIRMED') {
            throw new DomainActionException('Solo pagos CONFIRMED pueden aplicar asignaciones.');
        }

        // Delegate to AllocationProcessor
        /** @var \App\Services\Payments\AllocationProcessor $processor */
        $processor = $this->container->get(\App\Services\Payments\AllocationProcessor::class);
        $result = $processor->process($payment, $items, $options);

        Log::info('payments.allocations.latency', [
            'payment_id' => (int) $payment->getKey(),
            'latency_ms' => (int) ((microtime(true) - $start) * 1000),
            'stage' => 'PROCESSED',
            'items_count' => count($items),
        ]);

        // Cache for idempotency
        if ($cacheKey) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, true, 15 * 60);
        }

        // Issue receipts after commit
        $appliedNow = $result['did_set_applied'];
        DB::afterCommit(function () use ($payment, $appliedNow) {
            $this->issueReceiptsAfterAllocation($payment, $appliedNow);
        });

        return $this->toRow($payment->fresh());
    }

    /**
     * Build cache key for allocation idempotency.
     *
     * @param  array<int, array{charge_id: int, amount_bs_minor: int}>  $items
     * @param  array<string, mixed>  $options
     */
    private function buildAllocationCacheKey(Payment $payment, array $items, array $options): ?string
    {
        $normalized = array_map(static fn ($it) => [
            'charge_id' => (int) $it['charge_id'],
            'amount_bs_minor' => (int) $it['amount_bs_minor'],
        ], $items);
        usort($normalized, static fn ($a, $b) => $a['charge_id'] <=> $b['charge_id']);
        $payloadHash = hash('sha256', json_encode($normalized));
        $idempoKeyIn = (string) ($options['idempotency_key'] ?? '');

        return $idempoKeyIn !== ''
            ? ('payments:allocations:'.$payment->getKey().':'.$idempoKeyIn.':'.$payloadHash)
            : null;
    }

    /**
     * Preview allocations: validates items against outstanding and payment available.
     *
     * @param  array<int, array{charge_id: int, amount_bs_minor: int}>  $items
     * @param  array{use_credit?: bool}  $options
     * @return array{ok: bool, errors: list<string>, available_bs_minor: int, requested_bs_minor: int, summary: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function previewAllocations(int|string $paymentId, array $items, array $options = []): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        /** @var \App\Services\Payments\PreviewAllocationsValidator $validator */
        $validator = $this->container->get(\App\Services\Payments\PreviewAllocationsValidator::class);

        return $validator->validate($payment, $items, $options);
    }

    /**
     * Suggest allocations for a payment using a strategy.
     *
     * @param  array{strategy?: string, currency?: string, kind?: string, period_from?: string, period_to?: string, overdue_only?: bool}  $filters
     * @return array{items: list<array{charge_id: int, amount_bs_minor: int}>, summary: array<string, mixed>}
     */
    public function suggestAllocations(int|string $paymentId, array $filters = []): array
    {
        /** @var \App\Models\Payment $payment */
        $payment = $this->repo->findOrFailById($paymentId);

        /** @var \App\Services\Payments\SuggestAllocationsQuery $query */
        $query = $this->container->get(\App\Services\Payments\SuggestAllocationsQuery::class);

        return $query
            ->forPayment($payment)
            ->strategy($filters['strategy'] ?? 'fifo')
            ->filterCurrency($filters['currency'] ?? null)
            ->filterKind($filters['kind'] ?? null)
            ->filterPeriod($filters['period_from'] ?? null, $filters['period_to'] ?? null)
            ->overdueOnly((bool) ($filters['overdue_only'] ?? false))
            ->execute();
    }

    /**
     * Issue receipts after allocation commit.
     */
    private function issueReceiptsAfterAllocation(Payment $payment, bool $appliedNow): void
    {
        try {
            $svc = app(\App\Contracts\Services\ReceiptServiceInterface::class);
            // Summary receipt only when payment is APPLIED
            if ($appliedNow || (string) ($payment->fresh()->getAttribute('status') ?? '') === 'APPLIED') {
                $svc->issue((int) $payment->getKey());
            }
        } catch (\Throwable $e) {
            \Log::error('receipt.issue.failed', [
                'payment_id' => (int) $payment->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
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

        try {
            // Wrap the whole flow in a single transaction; any exception will rollback automatically.
            return DB::transaction(function () use (&$attributes) {
                /** @var \App\Models\Payment $payment */
                $payment = $this->create($attributes);

                // Auto-verify for all methods; DEB will short-circuit to CONFIRMED inside verify()
                $res = $this->verify($payment->getKey());
                // Preserve verification outcome for auditing outside the transaction (e.g., ReqId)
                $attributes['__verify_result'] = $res;

                // Decide success robustly: prefer gateway code, fallback to status label
                $code = (string) ($res['gateway_resp_code'] ?? ($res['code'] ?? ''));
                $ok = in_array($code, ['00', 'ACCP', '831'], true) || ((string) ($res['status'] ?? '') === 'CONFIRMED');
                if (! $ok) {
                    $desc = (string) ($res['gateway_message'] ?? ($res['message'] ?? ''));
                    // Throw to trigger transaction rollback; audit outside the transaction
                    $msg = trim('No validado. Código '.$code.($desc !== '' ? ' – '.$desc : '').'. El pago no fue registrado.');
                    throw new DomainActionException($msg);
                }

                return $res;
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Detect duplicate unique constraint vs other DB errors
            $state = '';
            try {
                /** @var mixed $code */
                $code = $e->errorInfo[0] ?? $e->getCode();
                $state = is_string($code) ? $code : (string) $code;
            } catch (\Throwable $ignore) {
                $state = (string) $e->getCode();
            }
            $msg = (string) $e->getMessage();
            $isIdempotencyUnique = ($state === '23505')
                && (str_contains($msg, 'payments_idempotency_unique') || str_contains(strtolower($msg), 'idempotency_key'));

            if ($isIdempotencyUnique) {
                // Build fingerprint again to log/audit duplicate details (outside tx)
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
                    $existing = Payment::withTrashed()->where('idempotency_key', $key)->orderByDesc('id')->first();
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

                if (app()->environment('testing')) {
                    $inputKey = (string) ($attributes['idempotency_key'] ?? '');
                    throw new DomainActionException('Este pago ya fue registrado. DB: '.$msg.' INPUT_KEY: '.$inputKey);
                }

                throw new DomainActionException('Este pago ya fue registrado.');
            }

            // Non-idempotency unique errors (or other DB issues)
            if (app()->environment('testing')) {
                throw new DomainActionException('DB error: '.$msg);
            }

            // Non-unique DB errors
            \Log::error('payment.create_and_verify.db_error', [
                'sqlstate' => $state,
                'message' => $e->getMessage(),
            ]);
            throw new DomainActionException('No fue posible registrar el pago.');
        } catch (DomainActionException $e) {
            // Audit verification failure outside transaction so it persists
            try {
                $extra = [];
                if (isset($attributes['__verify_result']) && is_array($attributes['__verify_result'])) {
                    $vr = $attributes['__verify_result'];
                    if (isset($vr['req_id'])) {
                        $extra['req_id'] = $vr['req_id'];
                    }
                    if (isset($vr['req_id_hash'])) {
                        $extra['req_id_hash'] = $vr['req_id_hash'];
                    }
                    if (isset($vr['gateway_resp_code'])) {
                        $extra['gateway_resp_code'] = $vr['gateway_resp_code'];
                    }
                }

                Audit::query()->create([
                    'event' => 'payment.verify_failed',
                    'auditable_type' => Payment::class,
                    'auditable_id' => 0,
                    'new_values' => array_merge([
                        'reason' => 'exception',
                        'message' => $e->getMessage(),
                        'input' => $attributes,
                    ], $extra),
                    'url' => (string) ($auditContext['url'] ?? ''),
                    'ip_address' => (string) ($auditContext['ip'] ?? ''),
                    'user_agent' => (string) ($auditContext['ua'] ?? ''),
                    'tags' => 'payment',
                ]);
            } catch (\Throwable $ignore) {
            }

            throw $e;
        } catch (\Throwable $e) {
            Log::error('payment.create_and_verify.unhandled', ['error' => $e->getMessage()]);
            throw new DomainActionException('No fue posible registrar el pago.');
        }
    }

    /**
     * Prevent deleting confirmed/applied payments or those with allocations.
     * CONFIRMED payments can only be deleted if method is DEB or EXO (manual/exonerated).
     * PMOV and TRANSFER are bank-verified and cannot be deleted once confirmed.
     */
    public function delete(Model|int|string $modelOrId): bool
    {
        /** @var \App\Models\Payment $payment */
        $payment = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);

        $status = strtoupper((string) ($payment->getAttribute('status') ?? ''));
        $allocSum = (int) PaymentAllocation::query()
            ->where('payment_id', (int) $payment->getKey())
            ->whereNull('deleted_at')
            ->sum('amount_bs_minor');

        if ($status === 'APPLIED' || $status === 'VOID' || $allocSum > 0) {
            throw new DomainActionException('No se puede eliminar un pago APPLIED (Conciliado) o con asignaciones.');
        }

        // For CONFIRMED payments, only allow deletion if method is DEB or EXO
        if ($status === 'CONFIRMED') {
            $methodCode = strtoupper((string) ($payment->getAttribute('method') ?? ''));
            if ($methodCode === '') {
                try {
                    $ptId = (int) ($payment->getAttribute('payment_type_id') ?? 0);
                    if ($ptId > 0) {
                        /** @var null|\App\Models\PaymentType $pt */
                        $pt = \App\Models\PaymentType::query()->find($ptId);
                        $methodCode = strtoupper((string) ($pt?->getAttribute('code') ?? ''));
                    }
                } catch (\Throwable $e) {
                    $methodCode = '';
                }
            }
            if (! in_array($methodCode, ['DEB', 'EXO'], true)) {
                throw new DomainActionException('Solo pagos manuales (Débito/Exonerado) confirmados pueden eliminarse. Pagos verificados por el banco no pueden eliminarse.');
            }
        }

        return $this->repo->delete($payment);
    }
}
