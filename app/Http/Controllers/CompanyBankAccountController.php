<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\CompanyBankAccountServiceInterface;
use App\Http\Requests\CompanyBankAccountIndexRequest;
use App\Http\Requests\CompanyBankAccountStoreRequest;
use App\Http\Requests\CompanyBankAccountUpdateRequest;
use App\Models\CompanyBankAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class CompanyBankAccountController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private CompanyBankAccountServiceInterface $serviceConcrete;

    public function __construct(CompanyBankAccountServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\CompanyBankAccount::class;
    }

    protected function view(): string
    {
        return 'catalogs/company-bank-account/index';
    }

    /**
     * Eager-load relations for index/listing to avoid N+1.
     *
     * @return array<string>
     */
    protected function with(): array
    {
        return ['bank'];
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
        $response->with('hasEditRoute', Route::has('catalogs.company-bank-account.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return CompanyBankAccountIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.company-bank-account.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['company_bank_account' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/company-bank-account/form';
    }

    /**
     * Provide options for selects in the form (e.g., banks).
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $banks = \App\Models\Bank::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])
            ->all();

        return [
            'options' => [
                'banks' => $banks,
            ],
        ];
    }

    protected function storeRequestClass(): string
    {
        return CompanyBankAccountStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return CompanyBankAccountUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.company-bank-account.export';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'bank_id' => null,
            'account_number' => null,
            'phone_number' => null,
            'account_holder_name' => null,
            'document_type' => null,
            'document_number' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, CompanyBankAccount $company_bank_account): \Inertia\Response
    {
        $this->authorize('view', $company_bank_account);

        $data = [
            'item' => $this->service->toItem($company_bank_account),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/company-bank-account/show', $data);
    }

    public function setActive(Request $request, CompanyBankAccount $company_bank_account): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $company_bank_account);
        $desired = (bool) $request->boolean('active');
        $company_bank_account->setAttribute('is_active', $desired);
        $company_bank_account->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.company-bank-account.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(CompanyBankAccount $company_bank_account): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $company_bank_account);
        $this->service->delete($company_bank_account);

        return redirect()->route('catalogs.company-bank-account.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
