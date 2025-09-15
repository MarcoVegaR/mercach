<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ContractTypeServiceInterface;
use App\Http\Requests\ContractTypeIndexRequest;
use App\Http\Requests\ContractTypeStoreRequest;
use App\Http\Requests\ContractTypeUpdateRequest;
use App\Models\ContractType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ContractTypeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ContractTypeServiceInterface $serviceConcrete;

    public function __construct(ContractTypeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\ContractType::class;
    }

    protected function view(): string
    {
        return 'catalogs/contract-type/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.contract-type.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ContractTypeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.contract-type.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['contract_type' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/contract-type/form';
    }

    protected function storeRequestClass(): string
    {
        return ContractTypeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ContractTypeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.contract-type.export';
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

    public function show(Request $request, ContractType $contract_type): \Inertia\Response
    {
        $this->authorize('view', $contract_type);

        $data = [
            'item' => $this->service->toItem($contract_type),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/contract-type/show', $data);
    }

    public function setActive(Request $request, ContractType $contract_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $contract_type);
        $desired = (bool) $request->boolean('active');
        $contract_type->setAttribute('is_active', $desired);
        $contract_type->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.contract-type.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(ContractType $contract_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $contract_type);
        $this->service->delete($contract_type);

        return redirect()->route('catalogs.contract-type.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
