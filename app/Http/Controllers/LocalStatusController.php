<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\LocalStatusServiceInterface;
use App\Http\Requests\LocalStatusIndexRequest;
use App\Http\Requests\LocalStatusStoreRequest;
use App\Http\Requests\LocalStatusUpdateRequest;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Models\LocalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class LocalStatusController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private LocalStatusServiceInterface $serviceConcrete;

    public function __construct(LocalStatusServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\LocalStatus::class;
    }

    protected function view(): string
    {
        return 'catalogs/local-status/index';
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
        $with = ['locals:id,local_status_id,code'];
        $result = $this->service->list($query, $with);

        $response = Inertia::render($this->view(), $result);

        // Inject stats (and other extras) from service
        $extras = $this->serviceConcrete->getIndexExtras();
        if (isset($extras['stats'])) {
            $response->with('stats', $extras['stats']);
        }

        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('catalogs.local-status.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return LocalStatusIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.local-status.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['local_status' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/local-status/form';
    }

    protected function storeRequestClass(): string
    {
        return LocalStatusStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return LocalStatusUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.local-status.export';
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

    public function show(Request $request, LocalStatus $local_status): \Inertia\Response
    {
        $this->authorize('view', $local_status);

        // Load locals count by default, but not the full relation
        $local_status->loadCount('locals');

        $data = [
            'item' => $this->service->toItem($local_status),
            'meta' => [
                'loaded_relations' => [],
                'loaded_counts' => ['locals'],
                'appended' => [],
            ],
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/local-status/show', $data);
    }

    /**
     * Load additional data for show page (API endpoint for dynamic loading)
     */
    public function showData(Request $request, LocalStatus $local_status): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $local_status);

        $with = $request->input('with', []);
        $withCount = $request->input('withCount', []);

        if (! empty($with)) {
            // Only allow loading locals relation
            $allowedWith = array_intersect($with, ['locals']);
            if (! empty($allowedWith)) {
                // Load locals with only id and code fields for efficiency
                $local_status->load(['locals:id,local_status_id,code']);
            }
        }

        if (! empty($withCount)) {
            // Only allow counting locals
            $allowedWithCount = array_intersect($withCount, ['locals']);
            if (! empty($allowedWithCount)) {
                $local_status->loadCount($allowedWithCount);
            }
        }

        return response()->json([
            'item' => $this->service->toItem($local_status),
            'meta' => [
                'loaded_relations' => $with,
                'loaded_counts' => $withCount,
                'appended' => [],
            ],
        ]);
    }

    public function setActive(SetCatalogActiveRequest $request, LocalStatus $local_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $local_status);
        $desired = (bool) $request->boolean('active');
        $local_status->setAttribute('is_active', $desired);
        $local_status->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.local-status.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(LocalStatus $local_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $local_status);
        $this->service->delete($local_status);

        return redirect()->route('catalogs.local-status.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
