<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\LocalServiceInterface;
use App\Http\Requests\LocalIndexRequest;
use App\Http\Requests\LocalStoreRequest;
use App\Http\Requests\LocalUpdateRequest;
use App\Models\Local;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class LocalController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private LocalServiceInterface $serviceConcrete;

    public function __construct(LocalServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\Local::class;
    }

    protected function view(): string
    {
        return 'catalogs/local/index';
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
        if (isset($extras['filterOptions'])) {
            $response->with('filterOptions', $extras['filterOptions']);
        }

        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('catalogs.local.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return LocalIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.local.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['local' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/local/form';
    }

    /**
     * Provide options for form selects (active-only catalogs)
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $markets = \App\Models\Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $localTypes = \App\Models\LocalType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $localStatuses = \App\Models\LocalStatus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $localLocations = \App\Models\LocalLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        return [
            'options' => [
                'markets' => $markets,
                'local_types' => $localTypes,
                'local_statuses' => $localStatuses,
                'local_locations' => $localLocations,
            ],
        ];
    }

    protected function storeRequestClass(): string
    {
        return LocalStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return LocalUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.local.export';
    }

    /**
     * @return array{
     *   code: null|string,
     *   name: null|string,
     *   market_id: null|int,
     *   local_type_id: null|int,
     *   local_status_id: null|int,
     *   local_location_id: null|int,
     *   area_m2: null|float|string,
     *   is_active: null|bool
     * }
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'market_id' => null,
            'local_type_id' => null,
            'local_status_id' => null,
            'local_location_id' => null,
            'area_m2' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, Local $local): \Inertia\Response
    {
        $this->authorize('view', $local);

        // Load relations to provide friendly names in the show view
        $local->load([
            'market:id,name',
            'localType:id,name',
            'localStatus:id,name',
            'localLocation:id,name',
        ]);

        $data = [
            'item' => $this->service->toItem($local),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/local/show', $data);
    }

    public function setActive(Request $request, Local $local): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $local);
        $desired = (bool) $request->boolean('active');
        $local->setAttribute('is_active', $desired);
        $local->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.local.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(Local $local): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $local);
        $this->service->delete($local);

        return redirect()->route('catalogs.local.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
