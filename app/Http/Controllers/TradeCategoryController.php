<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\TradeCategoryServiceInterface;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Http\Requests\TradeCategoryIndexRequest;
use App\Http\Requests\TradeCategoryStoreRequest;
use App\Http\Requests\TradeCategoryUpdateRequest;
use App\Models\TradeCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class TradeCategoryController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private TradeCategoryServiceInterface $serviceConcrete;

    public function __construct(TradeCategoryServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\TradeCategory::class;
    }

    protected function view(): string
    {
        return 'catalogs/trade-category/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.trade-category.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return TradeCategoryIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.trade-category.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['trade_category' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/trade-category/form';
    }

    protected function storeRequestClass(): string
    {
        return TradeCategoryStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return TradeCategoryUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.trade-category.export';
    }

    /**
     * @return array{code: string|null, name: string|null, description: string|null, is_active: bool|null}
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'description' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, TradeCategory $trade_category): \Inertia\Response
    {
        $this->authorize('view', $trade_category);

        $data = [
            'item' => $this->service->toItem($trade_category),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/trade-category/show', $data);
    }

    public function setActive(SetCatalogActiveRequest $request, TradeCategory $trade_category): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $trade_category);
        $desired = (bool) $request->boolean('active');
        $trade_category->setAttribute('is_active', $desired);
        $trade_category->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.trade-category.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(TradeCategory $trade_category): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $trade_category);
        $this->service->delete($trade_category);

        return redirect()->route('catalogs.trade-category.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
