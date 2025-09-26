<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ConcessionaireServiceInterface;
use App\Http\Requests\ConcessionaireIndexRequest;
use App\Http\Requests\ConcessionaireStoreRequest;
use App\Http\Requests\ConcessionaireUpdateRequest;
use App\Http\Requests\DeleteConcessionaireRequest;
use App\Http\Requests\SetConcessionaireActiveRequest;
use App\Models\Concessionaire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ConcessionaireController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ConcessionaireServiceInterface $serviceConcrete;

    public function __construct(ConcessionaireServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\Concessionaire::class;
    }

    protected function view(): string
    {
        return 'catalogs/concessionaire/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.concessionaire.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ConcessionaireIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.concessionaire.index';
    }

    /**
     * Provide options for form selects (active-only catalogs)
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $types = \App\Models\ConcessionaireType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $docTypes = \App\Models\DocumentType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $areaCodes = \App\Models\PhoneAreaCode::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->code])
            ->toArray();

        return [
            'options' => [
                'concessionaire_types' => $types,
                'document_types' => $docTypes,
                'phone_area_codes' => $areaCodes,
            ],
        ];
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['concessionaire' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/concessionaire/form';
    }

    protected function storeRequestClass(): string
    {
        return ConcessionaireStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ConcessionaireUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.concessionaire.export';
    }

    /**
     * @return array{
     *     concessionaire_type_id: int|null,
     *     full_name: string|null,
     *     document_type_id: int|null,
     *     document_number: string|null,
     *     fiscal_address: string|null,
     *     email: string|null,
     *     phone_area_code_id: int|null,
     *     phone_number: string|null,
     *     photo_path: string|null,
     *     id_document_path: string|null,
     *     is_active: bool|null
     * }
     */
    protected function getEmptyModel(): array
    {
        return [
            'concessionaire_type_id' => null,
            'full_name' => null,
            'document_type_id' => null,
            'document_number' => null,
            'fiscal_address' => null,
            'email' => null,
            'phone_area_code_id' => null,
            'phone_number' => null,
            'photo_path' => null,
            'id_document_path' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, Concessionaire $concessionaire): \Inertia\Response
    {
        $this->authorize('view', $concessionaire);

        // Eager-load relations used in the show mapping so friendly names are available
        $concessionaire->loadMissing(['concessionaireType', 'documentType']);

        $data = [
            'item' => $this->service->toItem($concessionaire),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/concessionaire/show', $data);
    }

    public function setActive(SetConcessionaireActiveRequest $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $concessionaire);
        $desired = (bool) $request->boolean('active');
        $concessionaire->setAttribute('is_active', $desired);
        $concessionaire->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.concessionaire.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(DeleteConcessionaireRequest $request, Concessionaire $concessionaire): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $concessionaire);
        $this->service->delete($concessionaire);

        return redirect()->route('catalogs.concessionaire.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
