<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\MarketServiceInterface;
use App\Http\Requests\MarketIndexRequest;
use App\Http\Requests\MarketStoreRequest;
use App\Http\Requests\MarketUpdateRequest;
use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class MarketController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private MarketServiceInterface $serviceConcrete;

    public function __construct(MarketServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\Market::class;
    }

    protected function view(): string
    {
        return 'catalogs/market/index';
    }

    /**
     * Display a listing of the resource with extras injected.
     */
    public function index(Request $request): \Inertia\Response
    {
        // Override to load locals relationship for the count/codes display
        $this->authorize('viewAny', $this->policyModel());

        $requestClass = $this->indexRequestClass();
        $validatedRequest = $requestClass::createFrom($request);
        $validatedRequest->setContainer(app());
        $validatedRequest->setRedirector(app('redirect'));
        $validatedRequest->validateResolved();

        $query = $validatedRequest->toListQuery();

        // Load locals relationship with only code field for efficiency
        $with = ['locals:id,market_id,code'];
        $result = $this->service->list($query, $with);

        $response = Inertia::render($this->view(), $result);

        // Inject stats (and other extras) from service
        $extras = $this->serviceConcrete->getIndexExtras();
        if (isset($extras['stats'])) {
            $response->with('stats', $extras['stats']);
        }

        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('catalogs.market.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return MarketIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.market.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['market' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/market/form';
    }

    protected function storeRequestClass(): string
    {
        return MarketStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return MarketUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.market.export';
    }

    /**
     * Override form routes to use the full catalogs.market.* names.
     */
    protected function createRouteName(): string
    {
        return 'catalogs.market.create';
    }

    protected function editRouteName(Model $model): string
    {
        return 'catalogs.market.edit';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'address' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, Market $market): \Inertia\Response
    {
        $this->authorize('view', $market);

        // Handle dynamic loading via query parameters (for Inertia partial reloads)
        $with = $request->input('with', []);
        $withCount = $request->input('withCount', []);

        // Use service to load data
        $showData = $this->serviceConcrete->loadShowData($market, $with, $withCount);

        $data = array_merge($showData, [
            'hasEditRoute' => true,
        ]);

        return Inertia::render('catalogs/market/show', $data);
    }

    public function setActive(Request $request, Market $market): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $market);
        $desired = (bool) $request->boolean('active');
        $market->setAttribute('is_active', $desired);
        $market->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.market.index')
            ->with('success', "El registro ha sido {$actionText} correctamente.");
    }

    public function destroy(Market $market): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $market);
        $this->service->delete($market);

        return redirect()->route('catalogs.market.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
