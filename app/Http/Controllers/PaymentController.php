<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Http\Requests\PaymentIndexRequest;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Requests\PaymentUpdateRequest;
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
        $trxTypeStr = strtoupper((string) $request->string('sTrxType', '300'));
        $bankId = (string) $request->string('sBankId', '156');
        $docId = (string) $request->string('sDocumentId', 'V12345678');
        $amount = (float) $request->float('nAmount', 1500.00);
        $dateTrx = (string) $request->string('sDateTrx', gmdate('Y-m-d'));
        $trxId = (string) $request->string('sTrxId', gmdate('Ymd').'00000001');

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
            // Transferencia: cuentas (20 dígitos) y referencia 6–12 dígitos
            $from = $digits($request->string('sFromAcctNo', '01560011223344556677'));
            $to = $digits($request->string('sToAcctNo', '01560099887766554433'));
            $refRaw = $digits($request->string('sReferenceNo', '123456'));
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
            // Pago Móvil: teléfonos (58XXXXXXXXXX) y referencia "0"
            $from = $normalizePhone($request->string('sFromAcctNo', '584241112233'));
            $to = $normalizePhone($request->string('sToAcctNo', '584242223334'));

            $payload = [
                'sMerchantId' => $merchantId,
                'sTrxId' => $trxId,
                'sTrxType' => '300',
                'sBankId' => $bankId,
                'sDocumentId' => $docId,
                'sFromAcctNo' => $from,
                'sToAcctNo' => $to,
                'nAmount' => $amount,
                'sReferenceNo' => '0',
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
        return 'catalogs/payment/form';
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

                return [
                    'id' => $acc->id,
                    'label' => $label,
                ];
            })
            ->all();

        // Banks list
        $banks = \App\Models\Bank::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
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
            /** @var \App\Models\Payment $model */
            $model = $this->service->create($validated);
            Log::info('payments.store created', [
                'payment_id' => $model->getKey(),
            ]);

            // Auto-verify logic: run when gateway credentials are present OR payment method is DEB (debit auto-confirm)
            $hasCredentials = (string) config('services.bank_gateway.key') !== ''
                && (string) config('services.bank_gateway.secret') !== ''
                && (string) config('services.bank_gateway.merchant_id') !== ''
                && (string) config('services.bank_gateway.terminal_id') !== '';

            // Resolve method code from model
            $methodCode = (string) ($model->getAttribute('method') ?? '');
            if ($methodCode === '') {
                try {
                    $ptId = (int) ($model->getAttribute('payment_type_id') ?? 0);
                    if ($ptId > 0) {
                        $pt = \App\Models\PaymentType::query()->find($ptId);
                        $methodCode = (string) ($pt?->getAttribute('code') ?? '');
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            $shouldVerify = $hasCredentials || (strtoupper($methodCode) === 'DEB');

            if ($shouldVerify) {
                try {
                    $result = $this->serviceConcrete->verify($model->getKey());
                    $status = (string) ($result['status'] ?? 'REGISTERED');
                    $code = (string) ($result['gateway_resp_code'] ?? ($result['code'] ?? ''));
                    Log::info('payments.store auto-verify attempted', [
                        'payment_id' => $model->getKey(),
                        'status' => $status,
                        'gateway_resp_code' => $code,
                    ]);
                    if ($status === 'CONFIRMED' || $code === '00') {
                        return redirect()->route('payments.show', ['payment' => $model->getKey(), 'tab' => 'apply'])
                            ->with('success', 'Pago creado y verificado. Puede proceder a aplicar.');
                    }
                } catch (\Throwable $e) {
                    Log::error('payments.store auto-verify failed', [
                        'payment_id' => $model->getKey(),
                        'error' => $e->getMessage(),
                    ]);
                    // keep silent, proceed with created state
                }
            }

            return redirect()->route('payments.show', ['payment' => $model->getKey()])
                ->with('success', 'Pago creado. Puede intentar verificar.');
        } catch (\App\Exceptions\DomainActionException $e) {
            Log::error('payments.store domain exception', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('payments.create')->with('error', $e->getMessage());
        } catch (\Exception $e) {
            Log::error('payments.store unhandled exception', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('payments.create')->with('error', 'Error al crear el pago.');
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

        $data = [
            'item' => $this->service->toItem($payment),
            'hasEditRoute' => true,
            'customer_credit_bs_minor' => $creditSum,
            'allocations' => $allocations,
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
        $result = $this->serviceConcrete->apply($payment->getKey());

        return redirect()->route('payments.show', ['payment' => $payment->getKey()])
            ->with('success', 'Pago aplicado. Estado: '.($result['status'] ?? 'N/A'));
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
            $credited = (int) ($creditByCharge[(int) $c->getAttribute('id')] ?? 0);
            $outstandingBsMinor = $amountBsMinor !== null ? max(0, $amountBsMinor - $allocated - $credited) : null;

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
            $credited = (int) ($creditByCharge[$cid] ?? 0);
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
    public function storeAllocations(Request $request, Payment $payment): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $payment);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.charge_id' => ['required', 'integer'],
            'items.*.amount_bs_minor' => ['required', 'integer', 'min:0'],
        ]);

        // Optional idempotency based on header + payload hash
        $idempotencyKey = $request->header('Idempotency-Key') ?? $request->header('X-Idempotency-Key') ?? (string) $request->input('idempotency_key', '');
        $normalized = array_map(fn ($it) => [
            'charge_id' => (int) $it['charge_id'],
            'amount_bs_minor' => (int) $it['amount_bs_minor'],
        ], $data['items']);
        usort($normalized, fn ($a, $b) => $a['charge_id'] <=> $b['charge_id']);
        $payloadHash = hash('sha256', json_encode($normalized));
        $cacheKey = $idempotencyKey ? ('payments:allocations:'.$payment->getKey().':'.$idempotencyKey.':'.$payloadHash) : null;
        if ($cacheKey && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            Log::info('payments.allocations.idempotent_hit', [
                'payment_id' => (int) $payment->getKey(),
                'cache_key' => $cacheKey,
            ]);

            return redirect()->route('payments.show', ['payment' => $payment->getKey(), 'tab' => 'allocations'])
                ->with('success', 'Asignaciones aplicadas correctamente.');
        }

        DB::transaction(function () use ($payment, $data, $cacheKey) {
            Log::info('payments.allocations.begin', [
                'payment_id' => (int) $payment->getKey(),
                'items_count' => is_countable($data['items'] ?? null) ? count($data['items']) : null,
            ]);
            // Lock payment row to avoid concurrent modifications
            DB::table('payments')->where('id', $payment->getKey())->lockForUpdate()->first();

            // Define paidOn for FX conversions and settled_on timestamps within this scope
            $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));

            $preview = $this->previewAllocations(new Request($data), $payment)->getData(true);
            if (! ($preview['ok'] ?? false)) {
                throw new \RuntimeException('Validación de asignaciones falló.');
            }

            // Available from payment funds
            $amountPayment = (int) $payment->getAttribute('amount_bs_minor');
            $currentAssigned = (int) PaymentAllocation::query()->where('payment_id', $payment->getKey())->sum('amount_bs_minor');
            $available = max(0, $amountPayment - $currentAssigned);
            $useCredit = (bool) ($data['use_credit'] ?? false);
            Log::info('payments.allocations.context', [
                'payment_id' => (int) $payment->getKey(),
                'amount_payment' => $amountPayment,
                'current_assigned' => $currentAssigned,
                'available_start' => $available,
                'use_credit' => $useCredit,
            ]);

            $applied = 0; // from payment funds
            $creditUsed = 0;

            // Preload open credits if needed
            $credits = collect();
            if ($useCredit) {
                $credits = CustomerCredit::query()
                    ->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                    ->where('debtor_id', (int) $payment->getAttribute('debtor_id'))
                    ->where('status', 'OPEN')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
            }

            $touched = [];
            foreach ($data['items'] as $it) {
                $amt = (int) $it['amount_bs_minor'];
                if ($amt <= 0) {
                    continue;
                }
                /** @var Charge|null $charge */
                $charge = Charge::query()->find((int) $it['charge_id']);
                if (! $charge) {
                    continue;
                }
                $touched[] = (int) $charge->getKey();

                // Allocate from payment funds first
                $fromPayment = min($amt, $available);
                if ($fromPayment > 0) {
                    $existing = PaymentAllocation::query()
                        ->where('payment_id', (int) $payment->getKey())
                        ->where('charge_id', (int) $it['charge_id'])
                        ->lockForUpdate()
                        ->first();
                    if ($existing) {
                        $existing->increment('amount_bs_minor', $fromPayment);
                        Log::info('payments.allocations.increment_allocation', [
                            'payment_id' => (int) $payment->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'inc_amount' => $fromPayment,
                        ]);
                    } else {
                        $alloc = new PaymentAllocation([
                            'payment_id' => (int) $payment->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'local_id' => (int) $charge->getAttribute('local_id'),
                            'debtor_type' => (string) $charge->getAttribute('debtor_type'),
                            'debtor_id' => (int) $charge->getAttribute('debtor_id'),
                            'amount_bs_minor' => $fromPayment,
                        ]);
                        $alloc->save();
                        Log::info('payments.allocations.create_allocation', [
                            'payment_id' => (int) $payment->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'amount' => $fromPayment,
                        ]);
                    }
                    $applied += $fromPayment;
                    $available -= $fromPayment;
                }

                // Remainder from credits
                $remain = $amt - $fromPayment;
                if ($remain > 0 && $useCredit && $credits->isNotEmpty()) {
                    Log::info('payments.allocations.use_credit_start', [
                        'payment_id' => (int) $payment->getKey(),
                        'charge_id' => (int) $it['charge_id'],
                        'needed' => $remain,
                    ]);
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
                        $app = new CreditApplication([
                            'customer_credit_id' => (int) $credit->getKey(),
                            'payment_id' => (int) $payment->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'amount_minor' => (int) $use,
                        ]);
                        $app->save();
                        $credit->decrement('balance_minor', $use);
                        if (((int) $credit->getAttribute('balance_minor')) <= 0) {
                            $credit->setAttribute('status', 'CLOSED');
                            $credit->save();
                        }
                        $needed -= $use;
                        $creditUsed += $use;
                        Log::info('payments.allocations.credit_applied', [
                            'payment_id' => (int) $payment->getKey(),
                            'credit_id' => (int) $credit->getKey(),
                            'charge_id' => (int) $it['charge_id'],
                            'amount' => (int) $use,
                            'remaining_needed' => $needed,
                        ]);
                    }
                }
                Log::info('payments.allocations.item_done', [
                    'payment_id' => (int) $payment->getKey(),
                    'charge_id' => (int) $it['charge_id'],
                    'requested' => $amt,
                    'from_payment' => $fromPayment,
                    'from_credit' => max(0, $amt - $fromPayment - max(0, $remain)),
                    'available_now' => $available,
                ]);
            }

            // Recompute available after applying
            $totalApplied = (int) PaymentAllocation::query()->where('payment_id', (int) $payment->getKey())->sum('amount_bs_minor');
            $amountBs = (int) ($payment->getAttribute('amount_bs_minor') ?? 0);
            $afterAvailable = max(0, $amountBs - $totalApplied);
            Log::info('payments.allocations.after_totals', [
                'payment_id' => (int) $payment->getKey(),
                'total_applied' => $totalApplied,
                'after_available' => $afterAvailable,
                'credit_used' => $creditUsed,
            ]);

            $createdCredit = false;
            // If leftover and no open charges remain, create customer credit (VES)
            if ($afterAvailable > 0) {
                $paidOn = Carbon::parse((string) $payment->getAttribute('paid_on'));
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
                        ->pluck('l.id')
                        ->unique()
                        ->values()
                        ->all();
                    $cq = Charge::query()->where('debtor_type', 'LOCAL')->whereIn('debtor_id', $locals);
                } else {
                    $cq = Charge::query()->where('debtor_type', (string) $payment->getAttribute('debtor_type'))
                        ->where('debtor_id', (int) $payment->getAttribute('debtor_id'));
                }
                try {
                    $ids = ChargeStatus::query()->whereIn('code', ['ISSUED', 'PARTIAL'])->pluck('id')->filter()->values()->all();
                    if (! empty($ids)) {
                        $cq->whereIn('charge_status_id', $ids);
                    }
                } catch (\Throwable $e) {
                }
                $charges = $cq->limit(500)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued']);
                $ids = $charges->pluck('id')->all();
                $allocByCharge = PaymentAllocation::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_bs_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
                $creditByCharge = CreditApplication::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
                /** @var FxRateServiceInterface $fx */
                $fx = app(FxRateServiceInterface::class);
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
                    $credit = new \App\Models\CustomerCredit([
                        'debtor_type' => (string) $payment->getAttribute('debtor_type'),
                        'debtor_id' => (int) $payment->getAttribute('debtor_id'),
                        'source_payment_id' => (int) $payment->getKey(),
                        'currency' => 'VES',
                        'balance_minor' => (int) $afterAvailable,
                        'status' => 'OPEN',
                        'created_from' => 'overpayment',
                    ]);
                    $credit->save();
                    Log::info('payments.allocations.created_customer_credit', [
                        'payment_id' => (int) $payment->getKey(),
                        'customer_credit_id' => (int) $credit->getKey(),
                        'balance_minor' => (int) $afterAvailable,
                    ]);
                    $createdCredit = true;
                }
            }

            // Update charge statuses for touched charges (ISSUED -> PARTIAL/SETTLED)
            if (! empty($touched)) {
                $statusIds = [
                    'ISSUED' => (int) (ChargeStatus::query()->where('code', 'ISSUED')->value('id') ?? 0),
                    'PARTIAL' => (int) (ChargeStatus::query()->where('code', 'PARTIAL')->value('id') ?? 0),
                    'SETTLED' => (int) (ChargeStatus::query()->where('code', 'SETTLED')->value('id') ?? 0),
                ];
                $chargesTouched = Charge::query()->whereIn('id', $touched)->get(['id', 'currency', 'amount_minor', 'amount_bs_minor_issued', 'charge_status_id']);
                foreach ($chargesTouched as $c) {
                    $cid = (int) $c->getAttribute('id');
                    $amountMinor = (int) $c->getAttribute('amount_minor');
                    $amountBsMinorIssued = $c->getAttribute('amount_bs_minor_issued');
                    $baseline = is_numeric($amountBsMinorIssued) ? (int) $amountBsMinorIssued : null;
                    if ($baseline === null) {
                        // Fallback: compute with payment paid_on FX (rare)
                        $currency = (string) $c->getAttribute('currency');
                        $rate = app(FxRateServiceInterface::class)->resolveAt($currency, $paidOn);
                        $rateToVes = $rate ? (float) $rate->getAttribute('rate_to_ves') : null;
                        $baseline = $rateToVes !== null ? (int) round(($amountMinor / 100.0) * $rateToVes * 100) : 0;
                    }
                    $allocated = (int) PaymentAllocation::query()->where('charge_id', $cid)->sum('amount_bs_minor');
                    $credited = (int) CreditApplication::query()->where('charge_id', $cid)->sum('amount_minor');
                    $outstanding = max(0, $baseline - $allocated - $credited);
                    $newStatusId = (int) $c->getAttribute('charge_status_id');
                    if ($outstanding === 0) {
                        $newStatusId = $statusIds['SETTLED'] ?: $newStatusId;
                        Charge::query()->where('id', $cid)->update(['charge_status_id' => $newStatusId, 'settled_on' => $paidOn->toDateString()]);
                    } else {
                        if (($allocated + $credited) > 0) {
                            $newStatusId = $statusIds['PARTIAL'] ?: $newStatusId;
                            Charge::query()->where('id', $cid)->update(['charge_status_id' => $newStatusId]);
                        }
                    }
                }
            }

            // Final status transition: mark APPLIED when full distribution done (no available left OR leftover converted to credit with no open charges)
            if (($afterAvailable === 0 && $totalApplied > 0) || $createdCredit) {
                $payment->setAttribute('status', 'APPLIED');
                $payment->save();
                Log::info('payments.allocations.set_status_applied_final', [
                    'payment_id' => (int) $payment->getKey(),
                    'total_applied' => $totalApplied,
                ]);
            } else {
                Log::info('payments.allocations.status_remains', [
                    'payment_id' => (int) $payment->getKey(),
                    'current_status' => (string) ($payment->getAttribute('status') ?? ''),
                    'after_available' => $afterAvailable,
                ]);
            }

            if ($cacheKey) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, true, 15 * 60);
                Log::info('payments.allocations.cached_idempotency', [
                    'payment_id' => (int) $payment->getKey(),
                    'cache_key' => $cacheKey,
                ]);
            }
        });

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
        $allocByCharge = PaymentAllocation::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_bs_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
        $creditByCharge = CreditApplication::query()->whereIn('charge_id', $ids)->selectRaw('charge_id, SUM(amount_minor) as s')->groupBy('charge_id')->pluck('s', 'charge_id');
        /** @var FxRateServiceInterface $fx */
        $fx = app(FxRateServiceInterface::class);
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
            $credited = (int) ($creditByCharge[(int) $c->getAttribute('id')] ?? 0);
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
