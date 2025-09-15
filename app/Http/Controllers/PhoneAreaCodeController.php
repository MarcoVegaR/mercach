<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\PhoneAreaCodeServiceInterface;
use App\Http\Requests\PhoneAreaCodeIndexRequest;
use App\Http\Requests\PhoneAreaCodeStoreRequest;
use App\Http\Requests\PhoneAreaCodeUpdateRequest;
use App\Models\PhoneAreaCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PhoneAreaCodeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private PhoneAreaCodeServiceInterface $serviceConcrete;

    public function __construct(PhoneAreaCodeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\PhoneAreaCode::class;
    }

    protected function view(): string
    {
        return 'catalogs/phone-area-code/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.phone-area-code.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return PhoneAreaCodeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.phone-area-code.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['phone_area_code' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/phone-area-code/form';
    }

    protected function storeRequestClass(): string
    {
        return PhoneAreaCodeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return PhoneAreaCodeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.phone-area-code.export';
    }

    /**
     * @return array{code: string|null, is_active: bool|null}
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, PhoneAreaCode $phone_area_code): \Inertia\Response
    {
        $this->authorize('view', $phone_area_code);

        $data = [
            'item' => $this->service->toItem($phone_area_code),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/phone-area-code/show', $data);
    }

    public function setActive(Request $request, PhoneAreaCode $phone_area_code): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $phone_area_code);
        $desired = (bool) $request->boolean('active');
        $phone_area_code->setAttribute('is_active', $desired);
        $phone_area_code->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.phone-area-code.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(PhoneAreaCode $phone_area_code): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $phone_area_code);
        $this->service->delete($phone_area_code);

        return redirect()->route('catalogs.phone-area-code.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
