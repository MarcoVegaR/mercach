<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\MarketTariffServiceInterface;
use App\Http\Requests\MarketTariffIndexRequest;
use App\Http\Requests\MarketTariffStoreRequest;
use App\Http\Requests\MarketTariffUpdateRequest;
use App\Models\MarketTariff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class MarketTariffController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private MarketTariffServiceInterface $serviceConcrete;

    public function __construct(MarketTariffServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\MarketTariff::class;
    }

    protected function view(): string
    {
        return 'catalogs/market-tariff/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.market-tariff.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return MarketTariffIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.market-tariff.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['market_tariff' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/market-tariff/form';
    }

    /**
     * Additional options for form (markets select)
     *
     * @return array{options: array{markets: array<int, array{id: int, name: string}>}}
     */
    protected function formOptions(): array
    {
        $markets = \App\Models\Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        return [
            'options' => [
                'markets' => $markets,
            ],
        ];
    }

    protected function storeRequestClass(): string
    {
        return MarketTariffStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return MarketTariffUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.market-tariff.export';
    }

    /**
     * @return array{market_id: null|int, valid_from: null|string, price_per_m2_eur_minor: null|int, is_current: bool, is_active: bool}
     */
    protected function getEmptyModel(): array
    {
        return [
            'market_id' => null,
            'valid_from' => null,
            'price_per_m2_eur_minor' => null,
            'is_current' => false,
            'is_active' => true,
        ];
    }

    public function show(Request $request, MarketTariff $market_tariff): \Inertia\Response
    {
        $this->authorize('view', $market_tariff);

        $data = [
            'item' => $this->service->toItem($market_tariff),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/market-tariff/show', $data);
    }

    public function setActive(Request $request, MarketTariff $market_tariff): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $market_tariff);
        $desired = (bool) $request->boolean('active');

        // If attempting to deactivate last active tariff for this market, block
        if ($desired === false) {
            $otherActives = (int) DB::table('market_tariffs')
                ->where('market_id', (int) $market_tariff->getAttribute('market_id'))
                ->where('id', '!=', (int) $market_tariff->getKey())
                ->where('is_active', true)
                ->count();
            if ($otherActives === 0) {
                return redirect()->route('catalogs.market-tariff.index')
                    ->with('error', 'Debe existir al menos una tarifa activa para el mercado.');
            }
        }

        // Allow multiple actives (vigente se deriva por fecha)

        $market_tariff->setAttribute('is_active', $desired);
        $market_tariff->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.market-tariff.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(MarketTariff $market_tariff): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $market_tariff);
        // Prevent deleting last active tariff for this market
        if ((bool) $market_tariff->getAttribute('is_active') === true) {
            $otherActives = (int) DB::table('market_tariffs')
                ->where('market_id', (int) $market_tariff->getAttribute('market_id'))
                ->where('id', '!=', (int) $market_tariff->getKey())
                ->where('is_active', true)
                ->count();
            if ($otherActives === 0) {
                return redirect()->route('catalogs.market-tariff.index')
                    ->with('error', 'No se puede eliminar la última tarifa activa del mercado.');
            }
        }

        $this->service->delete($market_tariff);

        return redirect()->route('catalogs.market-tariff.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
