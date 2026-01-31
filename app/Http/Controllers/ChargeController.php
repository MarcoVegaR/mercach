<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ChargeServiceInterface;
use App\Http\Requests\ChargeIndexRequest;
use Illuminate\Http\Request;

class ChargeController extends BaseIndexController
{
    public function __construct(ChargeServiceInterface $service)
    {
        parent::__construct($service);
    }

    protected function policyModel(): string
    {
        return \App\Models\Charge::class;
    }

    protected function view(): string
    {
        return 'charges/index';
    }

    protected function indexRequestClass(): string
    {
        return ChargeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'charges.index';
    }

    /**
     * Optionally inject basic stats (skeleton).
     */
    public function index(Request $request): \Inertia\Response
    {
        $response = parent::index($request);
        $response->with('stats', [
            'total' => (int) \App\Models\Charge::count(),
        ]);

        // Options for Run modal (types + markets)
        $markets = \App\Models\Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();
        $response->with('runOptions', [
            'types' => [
                ['value' => 'ALL', 'label' => 'Todos (M2, Fijo, Condominio)'],
                ['value' => 'RENT_EUR_M2', 'label' => 'Alquiler por m² (EUR)'],
                ['value' => 'RENT_EUR_FIXED', 'label' => 'Alquiler fijo (USD)'],
                ['value' => 'CONDO_USD', 'label' => 'Condominio (USD)'],
            ],
            'markets' => $markets,
        ]);

        // Filter options: statuses, locals, concessionaires, kinds
        $statuses = \App\Models\ChargeStatus::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($s) => [
                'id' => (int) $s->getAttribute('id'),
                'code' => (string) ($s->getAttribute('code') ?? ''),
                'name' => (string) ($s->getAttribute('name') ?? ''),
            ])->values()->all();

        $locals = \App\Models\Local::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($l) => [
                'id' => (int) $l->getAttribute('id'),
                'name' => (string) ($l->getAttribute('name') ?? ''),
            ])->values()->all();

        $concessionaires = \App\Models\Concessionaire::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn ($c) => [
                'id' => (int) $c->getAttribute('id'),
                'name' => (string) ($c->getAttribute('full_name') ?? ''),
            ])->values()->all();

        $knownTypeOptions = [
            ['value' => 'RENT_EUR_M2', 'label' => 'Tasa de uso por convenio'],
            ['value' => 'RENT_EUR_FIXED', 'label' => 'Alquiler fijo'],
            ['value' => 'CONDO_USD', 'label' => 'Gastos Comunes'],
            ['value' => 'FINE', 'label' => 'Multa'],
            ['value' => 'ADJ', 'label' => 'Ajuste'],
        ];

        $existingKinds = \App\Models\Charge::query()
            ->select('kind')
            ->distinct()
            ->orderBy('kind')
            ->pluck('kind')
            ->map(fn ($k) => (string) $k)
            ->all();

        $kindLabels = [];
        foreach ($knownTypeOptions as $opt) {
            $code = (string) $opt['value'];
            $kindLabels[$code] = (string) $opt['label'];
        }

        $typeOptions = $knownTypeOptions;
        foreach ($existingKinds as $code) {
            $typeOptions[] = [
                'value' => $code,
                'label' => $kindLabels[$code] ?? $code,
            ];
        }
        $seen = [];
        $typeOptions = array_values(array_filter($typeOptions, function ($opt) use (&$seen) {
            $v = (string) $opt['value'];
            if ($v === '' || isset($seen[$v])) {
                return false;
            }
            $seen[$v] = true;

            return true;
        }));

        $response->with('filterOptions', [
            'statuses' => $statuses,
            'locals' => $locals,
            'concessionaires' => $concessionaires,
            'types' => $typeOptions,
        ]);

        // Extra/manual charge kinds (for modal). Start from DB so options reflect real data.
        $extraKinds = $typeOptions;

        $response->with('extraKinds', $extraKinds);

        // Inject current FX rates (operational window) for USD and EUR to convert to VES at view time
        try {
            /** @var \App\Contracts\Services\FxRateServiceInterface $fx */
            $fx = app(\App\Contracts\Services\FxRateServiceInterface::class);
            $now = \Illuminate\Support\Carbon::now();
            $usd = $fx->resolveAt('USD', $now);
            $eur = $fx->resolveAt('EUR', $now);
            $response->with('fxNow', [
                'USD' => $usd ? (float) $usd->getAttribute('rate_to_ves') : null,
                'EUR' => $eur ? (float) $eur->getAttribute('rate_to_ves') : null,
            ]);
        } catch (\Throwable $e) {
            // Best effort: don't block page if FX lookup fails
            $response->with('fxNow', ['USD' => null, 'EUR' => null]);
        }

        return $response;
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    public function cancel(Request $request, \App\Models\Charge $charge): \Illuminate\Http\RedirectResponse
    {
        /** @var ChargeServiceInterface $svc */
        $svc = $this->service;

        try {
            $note = (string) $request->input('note', '');
            $svc->cancel($charge->getKey(), $note !== '' ? $note : null);

            return redirect()->route('charges.index')
                ->with('success', 'Cargo anulado correctamente.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function storeExtra(Request $request): \Illuminate\Http\RedirectResponse
    {
        /** @var ChargeServiceInterface $svc */
        $svc = $this->service;

        $data = $request->validate([
            'debtor_type' => ['nullable', 'string', 'in:CONCESSIONAIRE,LOCAL'],
            'debtor_id' => ['nullable', 'integer'],
            'local_id' => ['nullable', 'integer'],
            'market_id' => ['nullable', 'integer'],
            'kind' => ['nullable', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'period' => ['nullable', 'date'],
            'issued_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
            'contract_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:20'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $kind = strtoupper((string) ($data['kind'] ?? 'FINE'));
        if (($data['kind'] ?? null) === null || (string) ($data['kind'] ?? '') === '') {
            $data['kind'] = 'FINE';
        }
        if (($data['debtor_type'] ?? null) === null && in_array($kind, ['FINE', 'ADJ'], true)) {
            $data['debtor_type'] = 'CONCESSIONAIRE';
        }
        if (($data['debtor_type'] ?? null) === null) {
            $data['debtor_type'] = 'LOCAL';
        }
        if ((string) $data['debtor_type'] === 'LOCAL') {
            if (! isset($data['local_id']) || (int) $data['local_id'] <= 0) {
                return redirect()->back()->withInput()->with('error', 'El local es requerido para crear un cargo extraordinario.');
            }
        } else {
            if (! isset($data['debtor_id']) || (int) $data['debtor_id'] <= 0) {
                return redirect()->back()->withInput()->with('error', 'El cesionario es requerido para crear un cargo extraordinario.');
            }
        }

        try {
            $svc->createExtra($data);

            return redirect()->route('charges.index')
                ->with('success', 'Cargo extraordinario creado correctamente.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function bulkCancel(Request $request): \Illuminate\Http\RedirectResponse
    {
        /** @var ChargeServiceInterface $svc */
        $svc = $this->service;

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'] ?? [])));
        if ($ids === []) {
            return redirect()->route('charges.index')->with('error', 'No se seleccionaron cargos para anular.');
        }

        $success = 0;
        $errors = [];

        $note = isset($data['note']) && $data['note'] !== '' ? (string) $data['note'] : null;

        foreach ($ids as $id) {
            try {
                $svc->cancel($id, $note);
                $success++;
            } catch (\App\Exceptions\DomainActionException $e) {
                $errors[] = "Cargo {$id}: ".$e->getMessage();
            } catch (\Throwable $e) {
                $errors[] = "Cargo {$id}: error inesperado al anular.";
            }
        }

        if ($success === 0) {
            return redirect()->route('charges.index')->with('error', implode("\n", $errors));
        }

        $message = sprintf('%d cargo(s) anulados correctamente.', $success);
        $redirect = redirect()->route('charges.index')->with('success', $message);

        if (! empty($errors)) {
            $redirect->with('warning', implode("\n", $errors));
        }

        return $redirect;
    }
}
