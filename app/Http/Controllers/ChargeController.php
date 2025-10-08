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

        return $response;
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }
}
