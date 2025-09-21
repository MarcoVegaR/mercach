<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\LocalTypeServiceInterface;
use App\Http\Requests\LocalTypeIndexRequest;
use App\Http\Requests\LocalTypeStoreRequest;
use App\Http\Requests\LocalTypeUpdateRequest;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Models\LocalType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class LocalTypeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private LocalTypeServiceInterface $serviceConcrete;

    public function __construct(LocalTypeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\LocalType::class;
    }

    protected function view(): string
    {
        return 'catalogs/local-type/index';
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
        $with = ['locals:id,local_type_id,code'];
        $result = $this->service->list($query, $with);

        $response = Inertia::render($this->view(), $result);

        // Inject stats (and other extras) from service
        $extras = $this->serviceConcrete->getIndexExtras();
        if (isset($extras['stats'])) {
            $response->with('stats', $extras['stats']);
        }

        // Expose whether the edit route exists so the UI can hide Edit buttons if missing
        $response->with('hasEditRoute', Route::has('catalogs.local-type.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return LocalTypeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.local-type.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['local_type' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/local-type/form';
    }

    protected function storeRequestClass(): string
    {
        return LocalTypeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return LocalTypeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.local-type.export';
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

    public function show(Request $request, LocalType $local_type): \Inertia\Response
    {
        $this->authorize('view', $local_type);

        // Load locals count by default, but not the full relation
        $local_type->loadCount('locals');

        $data = [
            'item' => $this->service->toItem($local_type),
            'meta' => [
                'loaded_relations' => [],
                'loaded_counts' => ['locals'],
                'appended' => [],
            ],
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/local-type/show', $data);
    }

    /**
     * Load additional data for show page (API endpoint for dynamic loading)
     */
    public function showData(Request $request, LocalType $local_type): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $local_type);

        $with = $request->input('with', []);
        $withCount = $request->input('withCount', []);

        if (! empty($with)) {
            // Only allow loading locals relation
            $allowedWith = array_intersect($with, ['locals']);
            if (! empty($allowedWith)) {
                // Load locals with only id and code fields for efficiency
                $local_type->load(['locals:id,local_type_id,code']);
            }
        }

        if (! empty($withCount)) {
            // Only allow counting locals
            $allowedWithCount = array_intersect($withCount, ['locals']);
            if (! empty($allowedWithCount)) {
                $local_type->loadCount($allowedWithCount);
            }
        }

        return response()->json([
            'item' => $this->service->toItem($local_type),
            'meta' => [
                'loaded_relations' => $with,
                'loaded_counts' => $withCount,
                'appended' => [],
            ],
        ]);
    }

    public function setActive(SetCatalogActiveRequest $request, LocalType $local_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $local_type);
        $desired = (bool) $request->boolean('active');
        $local_type->setAttribute('is_active', $desired);
        $local_type->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.local-type.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(LocalType $local_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $local_type);
        $this->service->delete($local_type);

        return redirect()->route('catalogs.local-type.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
