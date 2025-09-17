<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\BankServiceInterface;
use App\Http\Requests\BankIndexRequest;
use App\Http\Requests\BankStoreRequest;
use App\Http\Requests\BankUpdateRequest;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Models\Bank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class BankController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private BankServiceInterface $serviceConcrete;

    public function __construct(BankServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\Bank::class;
    }

    protected function view(): string
    {
        return 'catalogs/bank/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.bank.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return BankIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.bank.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['bank' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/bank/form';
    }

    protected function storeRequestClass(): string
    {
        return BankStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return BankUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.bank.export';
    }

    /**
     * @return array{code: string|null, name: string|null, swift_bic: string|null, is_active: bool|null}
     */
    protected function getEmptyModel(): array
    {
        return [
            'code' => null,
            'name' => null,
            'swift_bic' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, Bank $bank): \Inertia\Response
    {
        $this->authorize('view', $bank);

        $data = [
            'item' => $this->service->toItem($bank),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/bank/show', $data);
    }

    public function setActive(SetCatalogActiveRequest $request, Bank $bank): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $bank);
        $desired = (bool) $request->boolean('active');
        $bank->setAttribute('is_active', $desired);
        $bank->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.bank.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(Bank $bank): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $bank);
        $this->service->delete($bank);

        return redirect()->route('catalogs.bank.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
