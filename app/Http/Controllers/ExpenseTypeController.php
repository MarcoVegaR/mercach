<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ExpenseTypeServiceInterface;
use App\Http\Requests\ExpenseTypeIndexRequest;
use App\Http\Requests\ExpenseTypeStoreRequest;
use App\Http\Requests\ExpenseTypeUpdateRequest;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Models\ExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ExpenseTypeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ExpenseTypeServiceInterface $serviceConcrete;

    public function __construct(ExpenseTypeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\ExpenseType::class;
    }

    protected function view(): string
    {
        return 'catalogs/expense-type/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.expense-type.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ExpenseTypeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.expense-type.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['expense_type' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/expense-type/form';
    }

    protected function storeRequestClass(): string
    {
        return ExpenseTypeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ExpenseTypeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.expense-type.export';
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

    public function show(Request $request, ExpenseType $expense_type): \Inertia\Response
    {
        $this->authorize('view', $expense_type);

        $data = [
            'item' => $this->service->toItem($expense_type),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/expense-type/show', $data);
    }

    public function setActive(SetCatalogActiveRequest $request, ExpenseType $expense_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $expense_type);
        $desired = (bool) $request->boolean('active');
        $expense_type->setAttribute('is_active', $desired);
        $expense_type->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.expense-type.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(ExpenseType $expense_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $expense_type);
        $this->service->delete($expense_type);

        return redirect()->route('catalogs.expense-type.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
