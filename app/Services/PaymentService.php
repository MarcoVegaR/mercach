<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\BankGatewayInterface;
use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Model;
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

        // Placeholder: allocations FIFO will be implemented later
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
}
