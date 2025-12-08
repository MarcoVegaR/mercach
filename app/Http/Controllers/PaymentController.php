<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\FxRateServiceInterface;
use App\Contracts\Services\PaymentServiceInterface;
use App\Http\Requests\PaymentIndexRequest;
use App\Http\Requests\PaymentStoreRequest;
use App\Http\Requests\PaymentUpdateRequest;
use App\Models\Audit;
use App\Models\Payment;
use App\Services\Bank\GatewayProbeService;
use App\Services\Payments\PaymentShowQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     * Connectivity probe for Bank Gateway (no DB writes).
     */
    public function gatewayProbe(Request $request): \Illuminate\Http\JsonResponse
    {
        $probeService = new GatewayProbeService;

        $result = $probeService->probe([
            'trx_type' => $request->input('sTrxType', $request->input('trx_type', '300')),
            'bank_id' => $request->input('sBankId', $request->input('bank_id', '156')),
            'document_id' => $request->input('sDocumentId', $request->input('document_id', 'V12345678')),
            'amount' => $request->input('nAmount', $request->input('amount', 1500.00)),
            'date_trx' => $request->input('sDateTrx', $request->input('date_trx', gmdate('Y-m-d'))),
            'trx_id' => $request->input('sTrxId', gmdate('Ymd').'00000001'),
            'from_acct' => $request->input('sFromAcctNo', $request->input('from_acct', '01560011223344556677')),
            'to_acct' => $request->input('sToAcctNo', $request->input('to_acct', '01560099887766554433')),
            'from_phone' => $request->input('sFromAcctNo', $request->input('from_phone')),
            'to_phone' => $request->input('sToAcctNo', $request->input('to_phone')),
            'reference' => $request->input('sReferenceNo', $request->input('reference', '123456')),
        ]);

        return response()->json($result);
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
                    'allow_transfer' => (bool) $acc->getAttribute('allow_transfer'),
                    'allow_pmov' => (bool) $acc->getAttribute('allow_pmov'),
                    'allow_debit' => (bool) $acc->getAttribute('allow_debit'),
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

        $showQuery = new PaymentShowQuery;
        $showData = $showQuery->forPayment($payment)->execute();

        $data = array_merge([
            'item' => $this->service->toItem($payment),
            'hasEditRoute' => true,
        ], $showData);

        return Inertia::render('catalogs/payment/show-modern', $data);
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
            // Optional filters
            'currency' => ['nullable', 'string', 'size:3'],
            'kind' => ['nullable', 'string', 'max:20'],
            'period_from' => ['nullable', 'date_format:Y-m'],
            'period_to' => ['nullable', 'date_format:Y-m'],
            'overdue_only' => ['nullable', 'boolean'],
        ]);

        /** @var \App\Services\Payments\OpenChargesQuery $query */
        $query = app(\App\Services\Payments\OpenChargesQuery::class);

        $result = $query
            ->forDebtor((string) $data['debtor_type'], (int) $data['debtor_id'])
            ->atDate((string) $data['paid_on'])
            ->filterLocal($data['local_id'] ?? null)
            ->filterCurrency($data['currency'] ?? null)
            ->filterKind($data['kind'] ?? null)
            ->filterPeriod($data['period_from'] ?? null, $data['period_to'] ?? null)
            ->overdueOnly((bool) ($data['overdue_only'] ?? false))
            ->execute();

        return response()->json($result);
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

        $result = $this->serviceConcrete->previewAllocations(
            $payment->getKey(),
            $data['items'],
            ['use_credit' => (bool) ($data['use_credit'] ?? false)]
        );

        return response()->json($result);
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

        $result = $this->serviceConcrete->suggestAllocations($payment->getKey(), [
            'strategy' => $data['strategy'] ?? 'fifo',
            'currency' => $data['currency'] ?? null,
            'kind' => $data['kind'] ?? null,
            'period_from' => $data['period_from'] ?? null,
            'period_to' => $data['period_to'] ?? null,
            'overdue_only' => (bool) ($data['overdue_only'] ?? false),
        ]);

        return response()->json($result);
    }
}
