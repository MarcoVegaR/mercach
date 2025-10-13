<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\FxRateServiceInterface;
use App\Http\Requests\FxRateIndexRequest;
use App\Http\Requests\FxRateStoreRequest;
use App\Http\Requests\FxRateUpdateRequest;
use App\Models\FxRate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class FxRateController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private FxRateServiceInterface $serviceConcrete;

    public function __construct(FxRateServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\FxRate::class;
    }

    protected function view(): string
    {
        return 'catalogs/fx-rate/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.fx-rate.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return FxRateIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.fx-rate.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['fx_rate' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/fx-rate/form';
    }

    protected function storeRequestClass(): string
    {
        return FxRateStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return FxRateUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.fx-rate.export';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'currency_code' => null,
            'rate_date' => null,
            'value_date' => null,
            'published_at' => null,
            'rate_to_ves' => null,
            'operational_from' => null,
            'operational_to' => null,
            'source' => null,
            'is_official' => null,
            'is_active' => null,
        ];
    }

    public function sync(Request $request): \Illuminate\Http\RedirectResponse
    {
        // Permission already enforced by route middleware
        $result = $this->serviceConcrete->ingestFromBcv();

        return redirect()->route('catalogs.fx-rate.index')
            ->with('success', sprintf('BCV sincronizado: insertados %d, actualizados %d', (int) $result['inserted'], (int) $result['updated']));
    }

    public function show(Request $request, FxRate $fx_rate): \Inertia\Response
    {
        $this->authorize('view', $fx_rate);

        $data = [
            'item' => $this->service->toItem($fx_rate),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/fx-rate/show', $data);
    }

    public function setActive(Request $request, FxRate $fx_rate): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $fx_rate);
        $desired = (bool) $request->boolean('active');
        $fx_rate->setAttribute('is_active', $desired);
        $fx_rate->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.fx-rate.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(FxRate $fx_rate): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $fx_rate);
        // Guard: do not allow delete if rate is referenced by payments
        $inUse = \App\Models\Payment::query()->where('fx_rate_id', $fx_rate->getKey())->exists();
        if ($inUse) {
            return redirect()->route('catalogs.fx-rate.index')
                ->with('error', 'No se puede eliminar: la tasa está en uso por pagos.');
        }
        $this->service->delete($fx_rate);

        return redirect()->route('catalogs.fx-rate.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
