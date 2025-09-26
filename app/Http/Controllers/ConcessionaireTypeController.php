<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ConcessionaireTypeServiceInterface;
use App\Http\Requests\ConcessionaireTypeIndexRequest;
use App\Http\Requests\ConcessionaireTypeStoreRequest;
use App\Http\Requests\ConcessionaireTypeUpdateRequest;
use App\Http\Requests\DeleteConcessionaireTypeRequest;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Models\ConcessionaireType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ConcessionaireTypeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ConcessionaireTypeServiceInterface $serviceConcrete;

    public function __construct(ConcessionaireTypeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\ConcessionaireType::class;
    }

    protected function view(): string
    {
        return 'catalogs/concessionaire-type/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.concessionaire-type.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ConcessionaireTypeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.concessionaire-type.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['concessionaire_type' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/concessionaire-type/form';
    }

    protected function storeRequestClass(): string
    {
        return ConcessionaireTypeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ConcessionaireTypeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.concessionaire-type.export';
    }

    /**
     * @return array{code: string|null, name: string|null, is_active: bool|null}
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, ConcessionaireType $concessionaire_type): \Inertia\Response
    {
        $this->authorize('view', $concessionaire_type);

        $data = [
            'item' => $this->service->toItem($concessionaire_type),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/concessionaire-type/show', $data);
    }

    public function setActive(SetCatalogActiveRequest $request, ConcessionaireType $concessionaire_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $concessionaire_type);
        $desired = (bool) $request->boolean('active');
        $concessionaire_type->setAttribute('is_active', $desired);
        $concessionaire_type->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.concessionaire-type.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(DeleteConcessionaireTypeRequest $request, ConcessionaireType $concessionaire_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $concessionaire_type);
        $this->service->delete($concessionaire_type);

        return redirect()->route('catalogs.concessionaire-type.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
