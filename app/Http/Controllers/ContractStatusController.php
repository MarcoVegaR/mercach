<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ContractStatusServiceInterface;
use App\Http\Requests\ContractStatusIndexRequest;
use App\Http\Requests\ContractStatusStoreRequest;
use App\Http\Requests\ContractStatusUpdateRequest;
use App\Models\ContractStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ContractStatusController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ContractStatusServiceInterface $serviceConcrete;

    public function __construct(ContractStatusServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\ContractStatus::class;
    }

    protected function view(): string
    {
        return 'catalogs/contract-status/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.contract-status.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ContractStatusIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.contract-status.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['contract_status' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/contract-status/form';
    }

    protected function storeRequestClass(): string
    {
        return ContractStatusStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ContractStatusUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.contract-status.export';
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

    public function show(Request $request, ContractStatus $contract_status): \Inertia\Response
    {
        $this->authorize('view', $contract_status);

        $data = [
            'item' => $this->service->toItem($contract_status),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/contract-status/show', $data);
    }

    public function setActive(Request $request, ContractStatus $contract_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $contract_status);
        $desired = (bool) $request->boolean('active');
        $contract_status->setAttribute('is_active', $desired);
        $contract_status->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.contract-status.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(ContractStatus $contract_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $contract_status);
        $this->service->delete($contract_status);

        return redirect()->route('catalogs.contract-status.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
