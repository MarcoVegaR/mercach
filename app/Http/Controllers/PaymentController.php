<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Http\Requests\PaymentIndexRequest;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Requests\PaymentUpdateRequest;
use App\Models\Audit;
use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\CreditApplication;
use App\Models\CustomerCredit;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PaymentController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private PaymentServiceInterface $serviceConcrete;

    public function __construct(PaymentServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    /**
     * Connectivity probe for Bank Gateway (no DB writes). Signs request in Mode A with secret base64 when configured.
     * Returns HTTP status + business JSON (e.g., sRespCode/sRespDesc) and debug info.
     */
    public function gatewayProbe(Request $request): \Illuminate\Http\JsonResponse
    {

        $host = (string) config('services.bank_gateway.host');
        $path = (string) config('services.bank_gateway.path');
        $scheme = (string) config('services.bank_gateway.scheme', 'https');
        $key = (string) config('services.bank_gateway.key');
        $keyEncoding = strtolower((string) config('services.bank_gateway.key_encoding', 'plain'));
        if ($keyEncoding === 'base64') {
            $decodedKey = base64_decode($key, true);
            if ($decodedKey !== false) {
                $key = $decodedKey;
            }
        }
        $secret = (string) config('services.bank_gateway.secret');
        $merchantId = (string) config('services.bank_gateway.merchant_id');
        $terminalId = (string) config('services.bank_gateway.terminal_id');
        $timeout = (int) config('services.bank_gateway.timeout', 30);
        $verifySsl = (bool) config('services.bank_gateway.verify', true);
        $concatWithNewlines = (bool) config('services.bank_gateway.signature_newlines', false);
        $signatureMode = strtoupper((string) config('services.bank_gateway.signature_mode', 'A'));
        $secretEncoding = strtolower((string) config('services.bank_gateway.secret_encoding', 'plain'));
        $secretPostDecode = strtolower((string) config('services.bank_gateway.secret_post_decode', 'none'));
        $withCharset = (bool) config('services.bank_gateway.content_type_charset', false);
        $stripLeadingSlash = (bool) config('services.bank_gateway.signature_strip_leading_slash', false);

        // Deterministic payload building per trx type
        $trxTypeStr = strtoupper((string) $request->input('sTrxType', $request->input('trx_type', '300')));
        $bankId = (string) $request->input('sBankId', $request->input('bank_id', '156'));
        $docId = (string) $request->input('sDocumentId', $request->input('document_id', 'V12345678'));
        $amountIn = $request->input('nAmount', $request->input('amount', 1500.00));
        $amount = round((float) $amountIn, 2);
        $dateTrx = (string) $request->input('sDateTrx', $request->input('date_trx', gmdate('Y-m-d')));
        $trxId = (string) $request->input('sTrxId', gmdate('Ymd').'00000001');

        $digits = static function ($s): string {
            return preg_replace('/\D+/', '', (string) $s) ?? '';
        };
        $normalizePhone = static function ($raw) use ($digits): string {
            $d = ltrim($digits($raw), '+');
            if ($d === '') {
                return '';
            }
            if (str_starts_with($d, '58')) {
                return $d;
            }
            if (str_starts_with($d, '0') && strlen($d) === 11) {
                return '58'.substr($d, 1);
            }

            return $d;
        };

        if ($trxTypeStr === '211') {
            $fromIn = $request->input('sFromAcctNo', $request->input('from_acct', '01560011223344556677'));
            $toIn = $request->input('sToAcctNo', $request->input('to_acct', '01560099887766554433'));
            $refIn = $request->input('sReferenceNo', $request->input('reference', '123456'));
            $from = $digits($fromIn);
            $to = $digits($toIn);
            $refRaw = $digits($refIn);
            if (strlen($refRaw) < 6) {
                $refRaw = str_pad($refRaw, 6, '0', STR_PAD_LEFT);
            }
            if (strlen($refRaw) > 12) {
                $refRaw = substr($refRaw, 0, 12);
            }

            $payload = [
                'sMerchantId' => $merchantId,
                'sTrxId' => $trxId,
                'sTrxType' => '211',
                'sBankId' => $bankId,
                'sDocumentId' => $docId,
                'sFromAcctNo' => $from,
                'sToAcctNo' => $to,
                'nAmount' => $amount,
                'sReferenceNo' => $refRaw,
                'sDateTrx' => $dateTrx,
                'sTerminalId' => $terminalId,
            ];
        } else {
            $fromIn = $request->input('sFromAcctNo', $request->input('from_phone'));
            $toIn = $request->input('sToAcctNo', $request->input('to_phone'));
            $from = $normalizePhone($fromIn);
            $to = $normalizePhone($toIn);

            $refIn = $request->input('sReferenceNo', $request->input('reference'));
            $refRaw = $digits($refIn);
            if (strlen($refRaw) < 6) {
                $refRaw = str_pad($refRaw, 6, '0', STR_PAD_LEFT);
            }
            if (strlen($refRaw) > 12) {
                $refRaw = substr($refRaw, 0, 12);
            }

            $payload = [
                'sMerchantId' => $merchantId,
                'sTrxId' => $trxId,
                'sTrxType' => '300',
                'sBankId' => $bankId,
                'sDocumentId' => $docId,
                'sFromAcctNo' => $from,
                'sToAcctNo' => $to,
                'nAmount' => $amount,
                'sReferenceNo' => $refRaw,
                'sDateTrx' => $dateTrx,
                'sTerminalId' => $terminalId,
            ];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $dateHeader = gmdate('D, d M Y H:i:s').' GMT';

        // Step 3: sha256 of body; mode A = base64(hex), mode B = base64(raw)
        $shaRaw = hash('sha256', $body, true);
        $shaHex = bin2hex($shaRaw);
        $bodyHashB64 = base64_encode($signatureMode === 'A' ? $shaHex : $shaRaw);

        // Step 4: concatenate host + endpoint + date + bodyHash (with optional strip-leading-slash)
        $signPath = $path;
        if ($stripLeadingSlash) {
            $signPath = ltrim($signPath, '/');
        } else {
            if (! str_starts_with($signPath, '/')) {
                $signPath = '/'.$signPath;
            }
        }
        $message = $concatWithNewlines
            ? ($host."\n".$signPath."\n".$dateHeader."\n".$bodyHashB64)
            : ($host.$signPath.$dateHeader.$bodyHashB64);

        // Step 5: HMAC with secret (optionally base64-decoded)
        $secretKey = $secret;
        $secretWasHex = false;
        if ($secretEncoding === 'base64') {
            $decoded = base64_decode($secret, true);
            if ($decoded !== false) {
                $secretKey = $decoded;
            }
        }
        // Conditionally hex-decode to bytes (only if configured)
        if ($secretPostDecode === 'hex'
            && preg_match('/^[0-9a-f]+$/i', $secretKey) === 1 && (strlen($secretKey) % 2) === 0
        ) {
            $hexBytes = @hex2bin($secretKey);
            if ($hexBytes !== false) {
                $secretKey = $hexBytes;
                $secretWasHex = true;
            }
        }
        $hmacRaw = hash_hmac('sha256', $message, $secretKey, true);
        $hmacHex = bin2hex($hmacRaw);
        $signature = base64_encode($signatureMode === 'A' ? $hmacHex : $hmacRaw);

        $url = $scheme.'://'.rtrim($host, '/').(str_starts_with($path, '/') ? $path : '/'.$path);
        $contentType = 'application/json'.($withCharset ? '; charset=utf-8' : '');

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'Date' => $dateHeader,
            'x-signature' => $signature,
            'Content-Type' => $contentType,
        ])->timeout($timeout)
            ->withOptions(['verify' => $verifySsl])
            ->withBody($body, $contentType)
            ->post($url);

        $rawResponse = $response->body();
        $json = [];
        try {
            $json = $response->json() ?? [];
        } catch (\Throwable $e) {
            $json = [];
        }

        return response()->json([
            'http_status' => $response->status(),
            'sRespCode' => $json['sRespCode'] ?? null,
            'sRespDesc' => $json['sRespDesc'] ?? ($json['message'] ?? null),
            'raw_request' => $body,
            'raw_response' => $rawResponse,
            'debug' => [
                'parsed' => $payload,
                'date' => $dateHeader,
                'body_hash_b64' => $bodyHashB64,
                'signature' => $signature,
                'x_signature_len' => strlen($signature),
                'canonical' => $message,
                'url' => $url,
                'host' => $host,
                'sign_path' => $signPath,
                'concat_newlines' => $concatWithNewlines,
                'secret_encoding' => $secretEncoding,
                'key_encoding' => $keyEncoding,
                'signature_mode' => $signatureMode,
                'secret_post_decode' => $secretPostDecode,
                'secret_was_hex' => $secretWasHex,
            ],
        ]);
    }

    protected function policyModel(): string
    {
        return \App\Models\Payment::class;
    }

    protected function view(): string
    {
        return 'catalogs/payment/index';
    }

    /**
     * Eager-load relations for index/listing to avoid N+1.
     *
     * @return array<string>
     */
    protected function with(): array
    {
        return ['companyBankAccount.bank', 'originBank'];
    }

    /**
     * Display a listing of the resource with extras injected.
     */
    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);

        // Inject stats (and other extras) from service
        $extras = $this->serviceConcrete->getIndexExtras();
        if (isset($extras['stats'])) {
            $response->with('stats', $extras['stats']);
        }

        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('payments.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return PaymentIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'payments.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['payment' => $model->getKey()];
    }

    protected function formView(string $mode): string
    {
        return $mode === 'create' ? 'catalogs/payment/create-modern' : 'catalogs/payment/form';
    }

    /**
     * Provide options for selects in the form.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        // Company bank accounts with bank name for labels
        $accounts = \App\Models\CompanyBankAccount::query()
            ->with('bank')
            ->orderBy('id')
            ->get()
            ->map(function ($acc) {
                $bankName = optional($acc->bank)->name;
                $label = trim(($bankName ? ($bankName.' • ') : '').(string) $acc->account_number);
                $phone = (string) ($acc->getAttribute('phone_number') ?? '');
                $supportsPMOV = preg_match('/^58\d{10}$/', $phone) === 1;

                return [
                    'id' => $acc->id,
                    'label' => $label,
                    'supportsPMOV' => $supportsPMOV,
                ];
            })
            ->all();

        // Banks list
        $banks = \App\Models\Bank::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->all();

        // Phone area codes (for PMOV friendly input)
        $phoneAreaCodes = DB::table('phone_area_codes')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code'])
            ->map(fn ($p) => ['id' => (int) $p->id, 'code' => (string) $p->code])
            ->all();

        // Concessionaires list (id + fields for FE label). Use full_name and document type code via relation
        $concessionaires = \App\Models\Concessionaire::query()
            ->with('documentType')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'document_number', 'document_type_id'])
            ->map(function ($c) {
                /** @var null|\App\Models\DocumentType $dt */
                $dt = $c->documentType;

                return [
                    'id' => $c->id,
                    'name' => (string) ($c->full_name ?? ''),
                    'document_number' => (string) ($c->document_number ?? ''),
                    'document_type_code' => $dt ? (string) ($dt->getAttribute('code') ?? '') : '',
                ];
            })
            ->all();

        // Status options consistent with service logic
        $statuses = [
            ['value' => 'REGISTERED', 'label' => 'Registrado'],
            ['value' => 'CONFIRMED', 'label' => 'Confirmado'],
            ['value' => 'APPLIED', 'label' => 'Aplicado'],
        ];

        // Locals list (id + code • name)
        $locals = \App\Models\Local::query()
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'label' => trim(($l->code ? $l->code.' • ' : '').(string) $l->name),
            ])
            ->all();

        // Payment types as selectable methods (code as value)
        $paymentTypes = \App\Models\PaymentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name'])
            ->map(fn ($pt) => ['value' => strtoupper((string) $pt->code), 'label' => (string) $pt->name])
            ->all();

        return [
            'options' => [
                'companyBankAccounts' => $accounts,
                'banks' => $banks,
                'phoneAreaCodes' => $phoneAreaCodes,
                'statuses' => $statuses,
                'concessionaires' => $concessionaires,
                'locals' => $locals,
                'paymentTypes' => $paymentTypes,
            ],
        ];
    }

    /**
     * Override store to auto-verify with bank after creating.
     */
    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', $this->policyModel());
        Log::info('payments.store called', [
            'input' => $request->all(),
        ]);
        // Resolve and validate FormRequest
        $requestClass = $this->storeRequestClass();
        /** @var \App\Http\Requests\PaymentStoreRequest $validatedRequest */
        $validatedRequest = $requestClass::createFrom($request);
        $validatedRequest->setContainer(app());
        $validatedRequest->setRedirector(app('redirect'));
        try {
            $validatedRequest->validateResolved();
        } catch (ValidationException $ve) {
            Log::warning('payments.store validation failed', [
                'errors' => $ve->errors(),
            ]);
            throw $ve;
        }

        try {
            $validated = $validatedRequest->validated();
            Log::info('payments.store validated', [
                'data' => $validated,
            ]);

            $row = $this->serviceConcrete->createAndVerify($validated, [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ]);

            $id = (int) ($row['id'] ?? 0);

            return redirect()->route('payments.show', ['payment' => $id, 'tab' => 'apply'])
                ->with('success', 'Pago creado y verificado. Puede proceder a aplicar.');
        } catch (\App\Exceptions\DomainActionException $e) {
            Log::error('payments.store domain exception', [
                'message' => $e->getMessage(),
            ]);

            // Ensure verification failures are audited even if the DB transaction was rolled back
            try {
                Audit::query()->create([
                    'event' => 'payment.verify_failed',
                    'auditable_type' => \App\Models\Payment::class,
                    'auditable_id' => 0,
                    'new_values' => [
                        'reason' => 'exception',
                        'message' => $e->getMessage(),
                        'input' => $request->all(),
                    ],
                    'url' => (string) $request->fullUrl(),
                    'ip_address' => (string) $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'tags' => 'payment',
                ]);
            } catch (\Throwable $ignore) {
            }

            return redirect()->back()->withInput()->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('payments.store unhandled exception', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()->with('error', 'Error al crear el pago.');
        }
    }

    protected function storeRequestClass(): string
    {
        return PaymentStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return PaymentUpdateRequest::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'local_id' => null,
            'debtor_type' => 'CONCESSIONAIRE',
            'debtor_id' => null,
            'company_bank_account_id' => null,
            'method' => null,
            'origin_bank_id' => null,
            'payer_document_type' => null,
            'payer_document_number' => null,
            'payer_account_number' => null,
            'payer_phone_e164' => null,
            'reference' => null,
            'amount_bs_minor' => null,
            'paid_on' => null,
            'fx_rate_id' => null,
            'status' => null,
            'gateway_request' => null,
            'gateway_response' => null,
            'gateway_resp_code' => null,
            'gateway_message' => null,
            'payer_details' => null,
            'idempotency_key' => null,
        ];
    }

    public function show(Request $request, Payment $payment): \Inertia\Response
    {
        $this->authorize('view', $payment);

        // Sum of open customer credits for debtor
        $creditSum = 0;
        try {
            $creditSum = (int) \App\Models\CustomerCredit::query()
                ->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                ->where('debtor_id', (int) $payment->getAttribute('debtor_id'))
                ->where('status', 'OPEN')
                ->sum('balance_minor');
        } catch (\Throwable $e) {
        }

        $allocations = [];
        try {
            $rows = \App\Models\PaymentAllocation::query()
                ->where('payment_id', (int) $payment->getKey())
                ->leftJoin('charges as c', 'c.id', '=', 'payment_allocations.charge_id')
                ->orderBy('payment_allocations.id')
                ->get([
                    'payment_allocations.charge_id',
                    'payment_allocations.amount_bs_minor',
                    'payment_allocations.created_at',
                    'c.currency',
                    'c.amount_minor',
                    'c.period',
                    'c.due_on',
                    'c.local_id',
                    'c.kind',
                ]);

            $localIds = $rows->pluck('local_id')->filter()->unique()->values()->all();
            $localsById = [];
            if (! empty($localIds)) {
                $localsById = \App\Models\Local::query()
                    ->whereIn('id', $localIds)
                    ->get(['id', 'code', 'name'])
                    ->keyBy('id')
                    ->map(function ($l) {
                        $code = (string) ($l->getAttribute('code') ?? '');
                        $name = (string) ($l->getAttribute('name') ?? '');
                        $label = trim(($code ? $code.' • ' : '').$name);

                        return $label !== '' ? $label : (string) $l->getAttribute('id');
                    })
                    ->toArray();
            }

            foreach ($rows as $r) {
                $allocations[] = [
                    'charge_id' => (int) ($r->getAttribute('charge_id') ?? 0),
                    'amount_bs_minor' => (int) ($r->getAttribute('amount_bs_minor') ?? 0),
                    'created_at' => (string) ($r->getAttribute('created_at') ?? ''),
                    'currency' => (string) ($r->getAttribute('currency') ?? ''),
                    'amount_minor' => (int) ($r->getAttribute('amount_minor') ?? 0),
                    'period' => (string) ($r->getAttribute('period') ?? ''),
                    'due_on' => (string) ($r->getAttribute('due_on') ?? ''),
                    'local_label' => $localsById[(int) ($r->getAttribute('local_id') ?? 0)] ?? null,
                    'kind' => (string) ($r->getAttribute('kind') ?? ''),
                ];
            }
        } catch (\Throwable $e) {
        }

        $receiptData = null;
        try {
            /** @var null|\App\Models\Receipt $rec */
            $rec = \App\Models\Receipt::query()
                ->where('payment_id', (int) $payment->getKey())
                ->where('status', 'ACTIVE')
                ->where(function ($q) {
                    $q->where('scope', 'PAYMENT')->orWhereNull('scope');
                })
                ->orderByDesc('id')
                ->first();

            // If not found and payment is APPLIED, lazily issue consolidated receipt (idempotent)
            if (! $rec && (string) ($payment->getAttribute('status') ?? '') === 'APPLIED') {
                try {
                    /** @var \App\Contracts\Services\ReceiptServiceInterface $svc */
                    $svc = app(\App\Contracts\Services\ReceiptServiceInterface::class);
                    $rec = $svc->issue((int) $payment->getKey());
                } catch (\Throwable $ignored) {
                }
            }

            if ($rec) {
                $verifyUrl = \Illuminate\Support\Facades\URL::signedRoute('receipts.public.show', ['token' => (string) $rec->getAttribute('public_token')]);
                $downloadUrl = route('receipts.download', ['receipt' => (int) $rec->getKey()]);
                $receiptData = [
                    'id' => (int) $rec->getKey(),
                    'receipt_number' => (string) $rec->getAttribute('receipt_number'),
                    'issued_at' => (string) ($rec->getAttribute('issued_at') ?? ''),
                    'download_url' => $downloadUrl,
                    'verify_url' => $verifyUrl,
                ];
            }
        } catch (\Throwable $e) {
        }

        $receiptsByCharge = [];
        try {
            $recs = \App\Models\Receipt::query()
                ->where('payment_id', (int) $payment->getKey())
                ->where('scope', 'CHARGE')
                ->where('status', 'ACTIVE')
                ->orderBy('id')
                ->get();
            foreach ($recs as $r) {
                $meta = (array) ($r->getAttribute('meta') ?? []);
                $chargeId = (int) ($r->getAttribute('charge_id') ?? 0);
                $period = (string) ($meta['charge_period'] ?? '');
                $kind = (string) ($meta['charge_kind'] ?? '');
                $concept = (string) ($r->getAttribute('concept') ?? '');
                $appliedBsMinor = (int) \App\Models\PaymentAllocation::query()
                    ->where('payment_id', (int) $payment->getKey())
                    ->where('charge_id', $chargeId)
                    ->sum('amount_bs_minor');
                $receiptsByCharge[] = [
                    'id' => (int) $r->getKey(),
                    'receipt_number' => (string) $r->getAttribute('receipt_number'),
                    'issued_at' => (string) ($r->getAttribute('issued_at') ?? ''),
                    'concept' => strtoupper($concept),
                    'charge_id' => $chargeId,
                    'charge_period' => $period,
                    'charge_kind' => $kind,
                    'applied_bs_minor' => $appliedBsMinor,
                    'download_url' => route('receipts.download', ['receipt' => (int) $r->getKey()]),
                    'verify_url' => \Illuminate\Support\Facades\URL::signedRoute('receipts.public.show', ['token' => (string) $r->getAttribute('public_token')]),
                ];
            }
        } catch (\Throwable $e) {
        }

        // Compute can_edit: allow only when REGISTERED; or CONFIRMED with no allocations (limited by service)
        $statusUi = (string) ($payment->getAttribute('status') ?? '');
        $appliedMinor = (int) \App\Models\PaymentAllocation::query()->where('payment_id', (int) $payment->getKey())->sum('amount_bs_minor');
        $canEdit = $statusUi === 'REGISTERED' || ($statusUi === 'CONFIRMED' && $appliedMinor === 0);

        $data = [
            'item' => $this->service->toItem($payment),
            'hasEditRoute' => true,
            'can_edit' => $canEdit,
            'customer_credit_bs_minor' => $creditSum,
            'allocations' => $allocations,
            'receipt' => $receiptData,
            'receipts_by_charge' => $receiptsByCharge,
        ];

        return Inertia::render('catalogs/payment/show', $data);
    }

    public function destroy(Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $payment);
        $this->service->delete($payment);

        return redirect()->route('payments.index')
            ->with('success', 'Registro eliminado correctamente.');
    }

    /**
     * Verify payment against external bank gateway.
     */
    public function verify(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $payment);
        $result = $this->serviceConcrete->verify($payment->getKey());

        $code = (string) ($result['gateway_resp_code'] ?? '');
        $desc = (string) ($result['gateway_message'] ?? '');
        $status = (string) ($result['status'] ?? 'REGISTERED');
        $ok = ($code === '00' || $status === 'CONFIRMED');

        $redirect = redirect()->route('payments.show', ['payment' => $payment->getKey()]);
        if ($ok) {
            return $redirect->with('success', 'Verificación aprobada (00). El pago está CONFIRMED y puede aplicarse.');
        }

        $msg = trim('No validado. Código '.$code.($desc !== '' ? ' – '.$desc : '').'. El pago permanece en estado REGISTERED.');

        return $redirect->with('error', $msg);
    }

    /**
     * Apply a confirmed payment (allocations will be handled by service).
     */
    public function apply(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $payment);
        try {
            $result = $this->serviceConcrete->apply($payment->getKey());

            return redirect()->route('payments.show', ['payment' => $payment->getKey()])
                ->with('success', 'Pago aplicado. Estado: '.($result['status'] ?? 'N/A'));
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('payments.show', ['payment' => $payment->getKey()])
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Resolve FX for a given paid_on and currency.
     */
    public function resolveFx(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'currency' => ['required', 'string', 'size:3'],
            'paid_on' => ['required', 'date'],
        ]);

        $paidOn = \Illuminate\Support\Carbon::parse((string) $request->string('paid_on'));
        $currency = strtoupper((string) $request->string('currency'));

        // Try to resolve full rate to expose both id and rate value to FE
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        $rate = $fx->resolveAt($currency, $paidOn);

        return response()->json([
            'fx_rate_id' => $rate?->getAttribute('id'),
            'rate_to_ves' => $rate?->getAttribute('rate_to_ves'),
        ]);
    }

    /**
     * List open charges for a debtor at a given paid_on, returning outstanding in Bs.
     */
    public function openCharges(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'debtor_type' => ['required', 'string', 'in:CONCESSIONAIRE,LOCAL'],
            'debtor_id' => ['required', 'integer'],
            'paid_on' => ['required', 'date'],
            'local_id' => ['nullable', 'integer'],
            // Optional filters (Phase 2)
            'currency' => ['nullable', 'string', 'size:3'],
            'kind' => ['nullable', 'string', 'max:20'],
            'period_from' => ['nullable', 'date_format:Y-m'],
            'period_to' => ['nullable', 'date_format:Y-m'],
            'overdue_only' => ['nullable', 'boolean'],
        ]);

        $paidOn = Carbon::parse((string) $data['paid_on']);

        // Build base query depending on debtor_type. For CONCESSIONAIRE, collect related LOCALs at paid_on.
        if ($data['debtor_type'] === 'CONCESSIONAIRE') {
            $concessionaireId = (int) $data['debtor_id'];
            // Resolve current locals for concessionaire at paid_on (contracts overlapping paid_on)
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
                ->pluck('l.id')
                ->unique()
                ->values()
                ->all();

            if (empty($locals)) {
                return response()->json(['items' => []]);
            }

            $q = Charge::query()
                ->where('debtor_type', 'LOCAL')
                ->whereIn('debtor_id', $locals);
        } else {
            $q = Charge::query()
                ->where('debtor_type', $data['debtor_type'])
                ->where('debtor_id', $data['debtor_id']);
            if (! empty($data['local_id'])) {
                $q->where('local_id', (int) $data['local_id']);
            }
        }

        // Filter by collectable statuses (ISSUED, PARTIAL) if available
        try {
            $statusIds = ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
            if (! empty($statusIds)) {
                $q->whereIn('charge_status_id', $statusIds);
            }
        } catch (\Throwable $e) {
            // ignore if catalog missing
        }

        // Apply optional filters
        if (! empty($data['currency'])) {
            $q->where('currency', strtoupper((string) $data['currency']));
        }
        if (! empty($data['kind'])) {
            $q->where('kind', strtoupper((string) $data['kind']));
        }
        if (! empty($data['period_from'])) {
            $from = Carbon::createFromFormat('Y-m', (string) $data['period_from'])->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }
        if (! empty($data['period_to'])) {
            $to = Carbon::createFromFormat('Y-m', (string) $data['period_to'])->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }
        if (! empty($data['overdue_only'])) {
            $q->whereDate('due_on', '<', $paidOn->toDateString());
        }

        $charges = $q
            ->orderBy('period')
            ->limit(500)
            ->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on', 'local_id', 'kind']);

        $ids = $charges->pluck('id')->all();
        $allocByCharge = PaymentAllocation::query()
            ->whereIn('charge_id', $ids)
            ->selectRaw('charge_id, SUM(amount_bs_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()
            ->whereIn('charge_id', $ids)
            ->selectRaw('charge_id, SUM(amount_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id');

        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);

        // Preload allocation rows with payment dates to compute currency equivalents accurately
        $allocRows = PaymentAllocation::query()
            ->whereIn('charge_id', $ids)
            ->leftJoin('payments as p', 'p.id', '=', 'payment_allocations.payment_id')
            ->get(['payment_allocations.charge_id', 'payment_allocations.amount_bs_minor', 'p.paid_on']);
        $allocCcyByCharge = [];
        // Preload credit application rows with payment dates and credit currency
        $creditRows = CreditApplication::query()
            ->whereIn('charge_id', $ids)
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);
        $creditCcyByCharge = [];
        // Compute credits converted to Bs per charge using credit currency at paid_on
        $creditBsByCharge = [];
        foreach ($creditRows as $cr) {
            $cid = (int) $cr->getAttribute('charge_id');
            $amtMinor = (int) ($cr->getAttribute('amount_minor') ?? 0);
            if ($amtMinor <= 0) {
                continue;
            }
            $paidRaw = (string) ($cr->getAttribute('paid_on') ?? '');
            $atEach = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $paidOn;
            $ccyCredit = strtoupper((string) ($cr->getAttribute('currency') ?? 'VES'));
            $creditBs = 0;
            if ($ccyCredit === 'VES') {
                $creditBs = $amtMinor;
            } else {
                $rateCredit = $fx->resolveAt($ccyCredit, $atEach);
                $vesCredit = $rateCredit ? (float) $rateCredit->getAttribute('rate_to_ves') : null;
                if ($vesCredit && $vesCredit > 0) {
                    $creditBs = (int) round(($amtMinor / 100.0) * $vesCredit * 100);
                }
            }
            $creditBsByCharge[$cid] = (int) (($creditBsByCharge[$cid] ?? 0) + $creditBs);
        }

        // Build local labels mapping (code • name)
        $localIds = $charges->pluck('local_id')->filter()->unique()->values()->all();
        $localsById = [];
        if (! empty($localIds)) {
            $localsById = \App\Models\Local::query()
                ->whereIn('id', $localIds)
                ->get(['id', 'code', 'name'])
                ->keyBy('id')
                ->map(function ($l) {
                    $code = (string) ($l->getAttribute('code') ?? '');
                    $name = (string) ($l->getAttribute('name') ?? '');
                    $label = trim(($code ? $code.' • ' : '').$name);

                    return $label !== '' ? $label : (string) $l->getAttribute('id');
                })
                ->toArray();
        }

        $items = [];
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
            $credited = (int) ($creditBsByCharge[(int) $c->getAttribute('id')] ?? 0);
            $outstandingBsMinor = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : null;

            // Compute applied in charge currency across ALL allocations (convert each allocation at its payment paid_on FX)
            $appliedCcyMinor = 0;
            if (in_array($currency, ['USD', 'EUR'], true)) {
                foreach ($allocRows as $row) {
                    if ((int) $row->getAttribute('charge_id') !== (int) $c->getAttribute('id')) {
                        continue;
                    }
                    $amtBs = (int) ($row->getAttribute('amount_bs_minor') ?? 0);
                    $paidOnEachRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    $paidOnEach = $paidOnEachRaw !== '' ? new \DateTimeImmutable($paidOnEachRaw) : $paidOn;
                    $rateEach = $fx->resolveAt($currency, $paidOnEach);
                    $vesEach = $rateEach ? (float) $rateEach->getAttribute('rate_to_ves') : null;
                    if ($vesEach && $vesEach > 0) {
                        $appliedCcyMinor += (int) round(($amtBs / 100.0) / $vesEach * 100);
                    }
                }
                // Convert credits applied to charge currency using their credit currency and payment date via VES pivot
                foreach ($creditRows as $row) {
                    if ((int) $row->getAttribute('charge_id') !== (int) $c->getAttribute('id')) {
                        continue;
                    }
                    $amtMinor = (int) ($row->getAttribute('amount_minor') ?? 0);
                    if ($amtMinor <= 0) {
                        continue;
                    }
                    $paidOnEachRaw = (string) ($row->getAttribute('paid_on') ?? '');
                    $paidOnEach = $paidOnEachRaw !== '' ? new \DateTimeImmutable($paidOnEachRaw) : $paidOn;
                    $ccyCredit = strtoupper((string) ($row->getAttribute('currency') ?? 'VES'));
                    // First convert credit to Bs
                    $creditBs = 0;
                    if ($ccyCredit === 'VES') {
                        $creditBs = $amtMinor;
                    } else {
                        $rateCredit = $fx->resolveAt($ccyCredit, $paidOnEach);
                        $vesCredit = $rateCredit ? (float) $rateCredit->getAttribute('rate_to_ves') : null;
                        if ($vesCredit && $vesCredit > 0) {
                            $creditBs = (int) round(($amtMinor / 100.0) * $vesCredit * 100);
                        }
                    }
                    // Then convert Bs to charge currency
                    $rateEach = $fx->resolveAt($currency, $paidOnEach);
                    $vesEach = $rateEach ? (float) $rateEach->getAttribute('rate_to_ves') : null;
                    if ($vesEach && $vesEach > 0 && $creditBs > 0) {
                        $appliedCcyMinor += (int) round(($creditBs / 100.0) / $vesEach * 100);
                    }
                }
            } elseif ($currency === 'VES') {
                // If charge is in VES, currency==Bs, so applied currency equals total Bs applied (alloc+credit)
                $appliedCcyMinor = (int) $allocated + (int) ($creditBsByCharge[(int) $c->getAttribute('id')] ?? 0);
            }
            $outstandingCcyMinor = max(0, (int) $amountMinor - (int) $appliedCcyMinor);

            $items[] = [
                'charge_id' => (int) $c->getAttribute('id'),
                'local_id' => (int) ($c->getAttribute('local_id') ?? 0),
                'local_label' => $localsById[(int) ($c->getAttribute('local_id') ?? 0)] ?? null,
                'period' => (string) $c->getAttribute('period'),
                'due_on' => (string) ($c->getAttribute('due_on') ?? ''),
                'currency' => $currency,
                'amount_minor' => $amountMinor,
                'amount_bs_minor' => $amountBsMinor,
                'allocated_bs_minor' => $allocated,
                'outstanding_bs_minor' => $outstandingBsMinor,
                'applied_currency_minor' => $appliedCcyMinor,
                'outstanding_currency_minor' => $outstandingCcyMinor,
                'fx_rate_id' => null,
                'rate_to_ves' => null,
                'kind' => (string) ($c->getAttribute('kind') ?? ''),
            ];
        }

        return response()->json(['items' => $items]);
    }

    /**
     * Preview allocations: validates posted items against outstanding and payment available.
     */
    public function previewAllocations(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $payment);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.charge_id' => ['required', 'integer'],
            'items.*.amount_bs_minor' => ['required', 'integer', 'min:0'],
            'use_credit' => ['nullable', 'boolean'],
        ]);

        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));
        $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);
        $useCredit = (bool) ($data['use_credit'] ?? false);
        $creditAvailable = 0;
        if ($useCredit) {
            $creditAvailable = (int) CustomerCredit::query()
                ->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                ->where('debtor_id', (int) $payment->getAttribute('debtor_id'))
                ->where('status', 'OPEN')
                ->sum('balance_minor');
        }

        /** @var array<int, array{charge_id:int, amount_bs_minor:int}> $itemsData */
        $itemsData = $data['items'];
        $byChargeRequested = collect($itemsData)->keyBy('charge_id');

        $charges = Charge::query()->whereIn('id', $byChargeRequested->keys())->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued']);
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        $allocByCharge = PaymentAllocation::query()
            ->whereIn('charge_id', $byChargeRequested->keys())
            ->selectRaw('charge_id, SUM(amount_bs_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()
            ->whereIn('charge_id', $byChargeRequested->keys())
            ->selectRaw('charge_id, SUM(amount_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id');

        // Convert credits to Bs by credit currency at paid_on
        $creditRowsPrev = CreditApplication::query()
            ->whereIn('charge_id', $byChargeRequested->keys())
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);
        $creditBsByChargePrev = [];
        foreach ($creditRowsPrev as $cr) {
            $cid = (int) $cr->getAttribute('charge_id');
            $amtMinor = (int) ($cr->getAttribute('amount_minor') ?? 0);
            if ($amtMinor <= 0) {
                continue;
            }
            $paidRaw = (string) ($cr->getAttribute('paid_on') ?? '');
            $atEach = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $paidOn;
            $ccyCredit = strtoupper((string) ($cr->getAttribute('currency') ?? 'VES'));
            $creditBs = 0;
            if ($ccyCredit === 'VES') {
                $creditBs = $amtMinor;
            } else {
                $rateCredit = $fx->resolveAt($ccyCredit, $atEach);
                $vesCredit = $rateCredit ? (float) $rateCredit->getAttribute('rate_to_ves') : null;
                if ($vesCredit && $vesCredit > 0) {
                    $creditBs = (int) round(($amtMinor / 100.0) * $vesCredit * 100);
                }
            }
            $creditBsByChargePrev[$cid] = (int) (($creditBsByChargePrev[$cid] ?? 0) + $creditBs);
        }

        $errors = [];
        $totalRequested = 0;
        $itemsResp = [];
        foreach ($charges as $c) {
            $cid = (int) $c->getAttribute('id');
            $req = (int) ($byChargeRequested[$cid]['amount_bs_minor'] ?? 0);
            $totalRequested += $req;
            $currency = (string) $c->getAttribute('currency');
            $amountMinor = (int) $c->getAttribute('amount_minor');
            $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
            $amountBsMinor = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
            if ($amountBsMinor === null) {
                $rate = $fx->resolveAt($currency, $paidOn);
                $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                $amountBsMinor = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : null;
            }
            $allocated = (int) ($allocByCharge[$cid] ?? 0);
            $credited = (int) ($creditBsByChargePrev[$cid] ?? 0);
            $outstanding = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : 0;

            $valid = $req <= $outstanding;
            $msg = $valid ? null : 'Monto supera saldo (Bs).';
            if (! $valid) {
                $errors[] = "Charge {$cid}: monto supera saldo (Bs).";
            }

            $itemsResp[] = [
                'charge_id' => $cid,
                'requested' => $req,
                'outstanding' => $outstanding,
                'valid' => $valid,
                'message' => $msg,
            ];
        }
        $limit = $available + ($useCredit ? $creditAvailable : 0);
        if ($totalRequested > $limit) {
            $errors[] = 'Total a aplicar supera el disponible (pago + crédito a favor).';
        }

        return response()->json([
            'ok' => empty($errors),
            'errors' => $errors,
            'available_bs_minor' => $available,
            'requested_bs_minor' => $totalRequested,
            'summary' => [
                'available_bs_minor' => $available,
                'credit_available_bs_minor' => $creditAvailable,
                'requested_bs_minor' => $totalRequested,
                'after_available_bs_minor' => max(0, $available - $totalRequested),
                'after_total_available_bs_minor' => max(0, ($available + $creditAvailable) - $totalRequested),
            ],
            'items' => $itemsResp,
        ]);
    }

    /**
     * Store allocations after validation, update payment status to APPLIED if any.
     */
    public function storeAllocations(Request $request, Payment $payment): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorize('update', $payment);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.charge_id' => ['required', 'integer'],
            'items.*.amount_bs_minor' => ['required', 'integer', 'min:0'],
            'use_credit' => ['nullable', 'boolean'],
        ]);

        try {
            $this->serviceConcrete->storeAllocations(
                $payment->getKey(),
                $data['items'],
                [
                    'use_credit' => (bool) ($data['use_credit'] ?? false),
                    'idempotency_key' => (string) ($request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key', '')),
                ]
            );
        } catch (\App\Exceptions\DomainActionException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'errors' => [$e->getMessage()]], 422);
            }

            return redirect()->route('payments.show', ['payment' => $payment->getKey(), 'tab' => 'allocations'])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('payments.show', ['payment' => $payment->getKey(), 'tab' => 'allocations'])
            ->with('success', 'Asignaciones aplicadas correctamente.');
    }

    /**
     * Suggest allocations for a payment using a strategy and optional filters.
     */
    public function suggestAllocations(Request $request, Payment $payment): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $payment);
        $data = $request->validate([
            'strategy' => ['nullable', 'string', 'in:fifo,proportional'],
            'currency' => ['nullable', 'string', 'size:3'],
            'kind' => ['nullable', 'string', 'max:20'],
            'period_from' => ['nullable', 'date_format:Y-m'],
            'period_to' => ['nullable', 'date_format:Y-m'],
            'overdue_only' => ['nullable', 'boolean'],
        ]);
        $strategy = $data['strategy'] ?? 'fifo';

        $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));
        $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
        $currentAssigned = (int) PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
        $available = max(0, $amountPayment - $currentAssigned);
        if ($available === 0) {
            return response()->json(['items' => [], 'summary' => ['available_bs_minor' => 0, 'suggested_bs_minor' => 0, 'after_available_bs_minor' => 0]]);
        }

        // Build base charges query (reuse filters from openCharges)
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
                ->pluck('l.id');
            $q = Charge::query()->where('debtor_type', 'LOCAL')->whereIn('debtor_id', $locals);
        } else {
            $q = Charge::query()->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                ->where('debtor_id', (int) $payment->getAttribute('debtor_id'));
        }
        try {
            $ids = ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
            if (! empty($ids)) {
                $q->whereIn('charge_status_id', $ids);
            }
        } catch (\Throwable $e) {
        }
        if (! empty($data['currency'])) {
            $q->where('currency', strtoupper((string) $data['currency']));
        }
        if (! empty($data['kind'])) {
            $q->where('kind', strtoupper((string) $data['kind']));
        }
        if (! empty($data['period_from'])) {
            $from = Carbon::createFromFormat('Y-m', (string) $data['period_from'])->startOfMonth()->toDateString();
            $q->whereDate('period', '>=', $from);
        }
        if (! empty($data['period_to'])) {
            $to = Carbon::createFromFormat('Y-m', (string) $data['period_to'])->endOfMonth()->toDateString();
            $q->whereDate('period', '<=', $to);
        }
        if (! empty($data['overdue_only'])) {
            $q->whereDate('due_on', '<', $paidOn->toDateString());
        }

        $charges = $q->orderBy('period')->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'period', 'due_on']);
        $ids = $charges->pluck('id')->all();
        $allocByCharge = PaymentAllocation::query()
            ->whereIn('charge_id', $ids)
            ->selectRaw('charge_id, SUM(amount_bs_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()
            ->whereIn('charge_id', $ids)
            ->selectRaw('charge_id, SUM(amount_minor) as s')
            ->groupBy('charge_id')
            ->pluck('s', 'charge_id');
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
        // Convert credits to Bs by credit currency at paid_on
        $creditRows = CreditApplication::query()
            ->whereIn('charge_id', $ids)
            ->leftJoin('payments as p', 'p.id', '=', 'credit_applications.payment_id')
            ->leftJoin('customer_credits as cc', 'cc.id', '=', 'credit_applications.customer_credit_id')
            ->get(['credit_applications.charge_id', 'credit_applications.amount_minor', 'p.paid_on', 'cc.currency']);
        $creditBsByCharge = [];
        foreach ($creditRows as $cr) {
            $cid = (int) $cr->getAttribute('charge_id');
            $amtMinor = (int) ($cr->getAttribute('amount_minor') ?? 0);
            if ($amtMinor <= 0) {
                continue;
            }
            $paidRaw = (string) ($cr->getAttribute('paid_on') ?? '');
            $atEach = $paidRaw !== '' ? new \DateTimeImmutable($paidRaw) : $paidOn;
            $ccyCredit = strtoupper((string) ($cr->getAttribute('currency') ?? 'VES'));
            $creditBs = 0;
            if ($ccyCredit === 'VES') {
                $creditBs = $amtMinor;
            } else {
                $rateCredit = $fx->resolveAt($ccyCredit, $atEach);
                $vesCredit = $rateCredit ? (float) $rateCredit->getAttribute('rate_to_ves') : null;
                if ($vesCredit && $vesCredit > 0) {
                    $creditBs = (int) round(($amtMinor / 100.0) * $vesCredit * 100);
                }
            }
            $creditBsByCharge[$cid] = (int) (($creditBsByCharge[$cid] ?? 0) + $creditBs);
        }
        $rows = [];
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
            $credited = (int) ($creditBsByCharge[(int) $c->getAttribute('id')] ?? 0);
            $outstanding = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : 0;
            $rows[] = ['charge_id' => (int) $c->getAttribute('id'), 'outstanding' => $outstanding, 'due_on' => (string) ($c->getAttribute('due_on') ?? ''), 'period' => (string) $c->getAttribute('period')];
        }

        $items = [];
        if ($strategy === 'fifo') {
            usort($rows, fn ($a, $b) => strcmp($a['due_on'] ?: $a['period'], $b['due_on'] ?: $b['period']));
            $remaining = $available;
            foreach ($rows as $r) {
                if ($remaining <= 0) {
                    break;
                }
                $take = min($remaining, (int) $r['outstanding']);
                if ($take > 0) {
                    $items[] = ['charge_id' => $r['charge_id'], 'amount_bs_minor' => $take];
                    $remaining -= $take;
                }
            }
        } else {
            $remaining = $available;
            $totalOut = array_reduce($rows, fn ($acc, $r) => $acc + (int) $r['outstanding'], 0);
            if ($totalOut > 0) {
                $shares = [];
                foreach ($rows as $r) {
                    $out = (int) $r['outstanding'];
                    if ($out <= 0) {
                        continue;
                    }
                    $share = (int) floor(($out / $totalOut) * $remaining);
                    $shares[$r['charge_id']] = min($share, $out);
                }
                $assigned = array_sum($shares);
                $residual = max(0, $remaining - $assigned);
                if ($residual > 0) {
                    foreach ($rows as $r) {
                        if ($residual <= 0) {
                            break;
                        }
                        $cid = $r['charge_id'];
                        $out = (int) $r['outstanding'];
                        $curr = $shares[$cid] ?? 0;
                        if ($curr < $out) {
                            $shares[$cid] = $curr + 1;
                            $residual--;
                        }
                    }
                }
                foreach ($shares as $cid => $amt) {
                    if ($amt > 0) {
                        $items[] = ['charge_id' => (int) $cid, 'amount_bs_minor' => (int) $amt];
                    }
                }
            }
        }

        $suggested = array_reduce($items, fn ($a, $i) => $a + (int) $i['amount_bs_minor'], 0);

        return response()->json([
            'items' => $items,
            'summary' => [
                'available_bs_minor' => $available,
                'suggested_bs_minor' => $suggested,
                'after_available_bs_minor' => max(0, $available - $suggested),
            ],
        ]);
    }
}
