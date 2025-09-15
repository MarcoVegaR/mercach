<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\LocalStatusServiceInterface;
use App\Http\Requests\LocalStatusIndexRequest;
use App\Http\Requests\LocalStatusStoreRequest;
use App\Http\Requests\LocalStatusUpdateRequest;
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
        $response = parent::index($request);

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

        $data = [
            'item' => $this->service->toItem($local_status),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/local-status/show', $data);
    }

    public function setActive(Request $request, LocalStatus $local_status): \Illuminate\Http\RedirectResponse
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
