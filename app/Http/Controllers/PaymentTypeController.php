<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\PaymentTypeServiceInterface;
use App\Http\Requests\PaymentTypeIndexRequest;
use App\Http\Requests\PaymentTypeStoreRequest;
use App\Http\Requests\PaymentTypeUpdateRequest;
use App\Http\Requests\SetCatalogActiveRequest;
use App\Models\PaymentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PaymentTypeController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private PaymentTypeServiceInterface $serviceConcrete;

    public function __construct(PaymentTypeServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\PaymentType::class;
    }

    protected function view(): string
    {
        return 'catalogs/payment-type/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.payment-type.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return PaymentTypeIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.payment-type.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['payment_type' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/payment-type/form';
    }

    protected function storeRequestClass(): string
    {
        return PaymentTypeStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return PaymentTypeUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.payment-type.export';
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

    public function show(Request $request, PaymentType $payment_type): \Inertia\Response
    {
        $this->authorize('view', $payment_type);

        $data = [
            'item' => $this->service->toItem($payment_type),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/payment-type/show', $data);
    }

    public function setActive(SetCatalogActiveRequest $request, PaymentType $payment_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $payment_type);
        $desired = (bool) $request->boolean('active');
        $payment_type->setAttribute('is_active', $desired);
        $payment_type->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.payment-type.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(PaymentType $payment_type): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $payment_type);
        $this->service->delete($payment_type);

        return redirect()->route('catalogs.payment-type.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
