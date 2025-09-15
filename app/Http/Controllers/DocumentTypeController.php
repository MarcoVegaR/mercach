<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\DocumentTypeServiceInterface;
use App\Http\Requests\DocumentTypeIndexRequest;
use App\Http\Requests\DocumentTypeStoreRequest;
use App\Http\Requests\DocumentTypeUpdateRequest;
use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class DocumentTypeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private DocumentTypeServiceInterface $serviceConcrete;

    public function __construct(DocumentTypeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\DocumentType::class;
    }

    protected function view(): string
    {
        return 'catalogs/document-type/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.document-type.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return DocumentTypeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.document-type.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['document_type' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/document-type/form';
    }

    protected function storeRequestClass(): string
    {
        return DocumentTypeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return DocumentTypeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.document-type.export';
    }

    /**
     * @return array{code: string|null, name: string|null, mask: string|null, is_active: bool|null}
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'mask' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, DocumentType $document_type): \Inertia\Response
    {
        $this->authorize('view', $document_type);

        $data = [
            'item' => $this->service->toItem($document_type),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/document-type/show', $data);
    }

    public function setActive(Request $request, DocumentType $document_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $document_type);
        $desired = (bool) $request->boolean('active');
        $document_type->setAttribute('is_active', $desired);
        $document_type->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.document-type.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(DocumentType $document_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $document_type);
        $this->service->delete($document_type);

        return redirect()->route('catalogs.document-type.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
