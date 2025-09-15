<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\PaymentStatusServiceInterface;
use App\Http\Requests\PaymentStatusIndexRequest;
use App\Http\Requests\PaymentStatusStoreRequest;
use App\Http\Requests\PaymentStatusUpdateRequest;
use App\Models\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PaymentStatusController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private PaymentStatusServiceInterface $serviceConcrete;

    public function __construct(PaymentStatusServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\PaymentStatus::class;
    }

    protected function view(): string
    {
        return 'catalogs/payment-status/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.payment-status.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return PaymentStatusIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.payment-status.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['payment_status' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/payment-status/form';
    }

    protected function storeRequestClass(): string
    {
        return PaymentStatusStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return PaymentStatusUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.payment-status.export';
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

    public function show(Request $request, PaymentStatus $payment_status): \Inertia\Response
    {
        $this->authorize('view', $payment_status);

        $data = [
            'item' => $this->service->toItem($payment_status),
            'hasEditRoute' => true,
        ];

        return Inertia::render('catalogs/payment-status/show', $data);
    }

    public function setActive(Request $request, PaymentStatus $payment_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $payment_status);
        $desired = (bool) $request->boolean('active');
        $payment_status->setAttribute('is_active', $desired);
        $payment_status->save();
        $actionText = $desired ? 'activado' : 'desactivado';

        return redirect()->route('catalogs.payment-status.index')
            ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
    }

    public function destroy(PaymentStatus $payment_status): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $payment_status);
        $this->service->delete($payment_status);

        return redirect()->route('catalogs.payment-status.index')
            ->with('success', 'Registro eliminado correctamente.');
    }
}
