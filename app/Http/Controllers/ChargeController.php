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
                ['value' => 'RENT_EUR_FIXED', 'label' => 'Alquiler fijo (EUR)'],
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

        $response->with('filterOptions', [
            'statuses' => $statuses,
            'locals' => $locals,
            'concessionaires' => $concessionaires,
            'types' => [
                ['value' => 'RENT_EUR_M2', 'label' => 'Alquiler por m² (EUR)'],
                ['value' => 'RENT_EUR_FIXED', 'label' => 'Alquiler fijo (EUR)'],
                ['value' => 'CONDO_USD', 'label' => 'Condominio (USD)'],
            ],
        ]);

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
}
