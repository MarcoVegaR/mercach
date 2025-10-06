<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ChargeStatusServiceInterface;
use App\Http\Requests\ChargeStatusIndexRequest;
use App\Http\Requests\ChargeStatusStoreRequest;
use App\Http\Requests\ChargeStatusUpdateRequest;
use App\Models\ChargeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ChargeStatusController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ChargeStatusServiceInterface $serviceConcrete;

    public function __construct(ChargeStatusServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\ChargeStatus::class;
    }

    protected function view(): string
    {
        return 'catalogs/charge-status/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.charge-status.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ChargeStatusIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.charge-status.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['charge_status' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/charge-status/form';
    }

    protected function storeRequestClass(): string
    {
        return ChargeStatusStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ChargeStatusUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.charge-status.export';
    }

    /**
     * @return array{code: null|string, name: null|string, is_active: null|bool}
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, ChargeStatus $charge_status): \Inertia\Response
    {
        $this->authorize('view', $charge_status);

        $data = [
            'item' => $this->service->toItem($charge_status),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/charge-status/show', $data);
    }

    public function setActive(Request $request, ChargeStatus $charge_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $charge_status);
        $desired = (bool) $request->boolean('active');
        $charge_status->setAttribute('is_active', $desired);
        $charge_status->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.charge-status.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(ChargeStatus $charge_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $charge_status);
        $this->service->delete($charge_status);

        return redirect()->route('catalogs.charge-status.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
