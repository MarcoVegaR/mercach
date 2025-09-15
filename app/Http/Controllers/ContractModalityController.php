<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ContractModalityServiceInterface;
use App\Http\Requests\ContractModalityIndexRequest;
use App\Http\Requests\ContractModalityStoreRequest;
use App\Http\Requests\ContractModalityUpdateRequest;
use App\Models\ContractModality;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ContractModalityController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ContractModalityServiceInterface $serviceConcrete;

    public function __construct(ContractModalityServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\ContractModality::class;
    }

    protected function view(): string
    {
        return 'catalogs/contract-modality/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.contract-modality.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ContractModalityIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.contract-modality.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['contract_modality' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/contract-modality/form';
    }

    protected function storeRequestClass(): string
    {
        return ContractModalityStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ContractModalityUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.contract-modality.export';
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

    public function show(Request $request, ContractModality $contract_modality): \Inertia\Response
    {
        $this->authorize('view', $contract_modality);

        $data = [
            'item' => $this->service->toItem($contract_modality),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/contract-modality/show', $data);
    }

    public function setActive(Request $request, ContractModality $contract_modality): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $contract_modality);
        $desired = (bool) $request->boolean('active');
        $contract_modality->setAttribute('is_active', $desired);
        $contract_modality->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.contract-modality.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(ContractModality $contract_modality): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $contract_modality);
        $this->service->delete($contract_modality);

        return redirect()->route('catalogs.contract-modality.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
