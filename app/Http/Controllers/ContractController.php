<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\ContractServiceInterface;
use App\Http\Requests\ContractIndexRequest;
use App\Http\Requests\Contracts\ConfirmContractRequest;
use App\Http\Requests\Contracts\ExtendContractRequest;
use App\Http\Requests\Contracts\SignContractRequest;
use App\Http\Requests\Contracts\TerminateContractRequest;
use App\Http\Requests\ContractStoreRequest;
use App\Http\Requests\ContractUpdateRequest;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ContractController extends BaseIndexController
{
    use \App\Http\Controllers\Concerns\HandlesForm;

    private ContractServiceInterface $serviceConcrete;

    public function __construct(ContractServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    public function sign(SignContractRequest $request, Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $contract);
        try {
            $validated = $request->validated();

            /** @var \Illuminate\Http\UploadedFile|null $pdf */
            $pdf = $request->file('pdf');
            $number = isset($validated['number']) ? (string) $validated['number'] : null;
            $endDate = isset($validated['end_date']) ? (string) $validated['end_date'] : null;

            $this->serviceConcrete->sign($contract, $pdf, $number, $endDate);

            return redirect()->route('catalogs.contract.index')->with('success', 'Contrato firmado.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.contract.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Securely download the main contract PDF.
     */
    public function downloadPdf(Contract $contract): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('view', $contract);

        $pdfPath = (string) ($contract->getAttribute('pdf_path') ?? '');
        if ($pdfPath === '') {
            abort(404);
        }

        // Convert public URL path (storage/...) to disk path (public/...)
        $diskPath = Str::startsWith($pdfPath, 'storage/') ? ('public/'.substr($pdfPath, 8)) : $pdfPath;
        if (! Storage::exists($diskPath)) {
            abort(404);
        }

        $filename = basename($pdfPath) ?: ('contract-'.$contract->getKey().'.pdf');

        // Serve inline so it can visualizarse embebido en <object> o en una nueva pestaña
        return Storage::response($diskPath, $filename, ['Content-Type' => 'application/pdf']);
    }

    protected function policyModel(): string
    {
        return \App\Models\Contract::class;
    }

    protected function view(): string
    {
        return 'catalogs/contract/index';
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
        $response->with('hasEditRoute', Route::has('catalogs.contract.edit'));

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return ContractIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'catalogs.contract.index';
    }

    /**
     * Get route parameters for the model (override HandlesForm default to use snake param).
     *
     * @return array<string, mixed>
     */
    protected function getRouteParameters(Model $model): array
    {
        return ['contract' => $model->getKey()];
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    protected function formView(string $mode): string
    {
        return 'catalogs/contract/form';
    }

    /**
     * Provide options for form selects (active-only catalogs)
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        $contractTypes = \App\Models\ContractType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $contractStatuses = \App\Models\ContractStatus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name, 'code' => (string) $m->code])
            ->toArray();

        $contractModalities = \App\Models\ContractModality::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name, 'code' => (string) $m->code])
            ->toArray();

        $tradeCategories = \App\Models\TradeCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        $concessionaires = \App\Models\Concessionaire::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->with(['documentType:id,code'])
            ->get(['id', 'full_name', 'document_number', 'document_type_id'])
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'name' => (string) $m->full_name,
                'document_number' => (string) $m->document_number,
                'document_type_code' => (string) ($m->documentType?->code ?: ''),
            ])
            ->toArray();

        $dispStatusId = \App\Models\LocalStatus::query()->whereRaw('UPPER(code) = ?', ['DISP'])->value('id');
        $locals = \App\Models\Local::query()
            ->where('is_active', true)
            ->when($dispStatusId, fn ($q) => $q->where('local_status_id', $dispStatusId))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->toArray();

        return [
            'options' => [
                'contract_types' => $contractTypes,
                'contract_statuses' => $contractStatuses,
                'contract_modalities' => $contractModalities,
                'trade_categories' => $tradeCategories,
                'concessionaires' => $concessionaires,
                'locals' => $locals,
            ],
        ];
    }

    protected function storeRequestClass(): string
    {
        return ContractStoreRequest::class;
    }

    protected function updateRequestClass(): string
    {
        return ContractUpdateRequest::class;
    }

    /**
     * Override export permission to match catalogs prefix (e.g., catalogs.tipo-documento.export).
     */
    protected function exportPermission(): string
    {
        return 'catalogs.contract.export';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getEmptyModel(): array
    {
        return [
            'number' => null,
            'contract_type_id' => null,
            'contract_status_id' => null,
            'contract_modality_id' => null,
            'trade_category_id' => null,
            'start_date' => null,
            'end_date' => null,
            'billing_day' => null,
            'monthly_price_eur' => null,
            'is_active' => null,
        ];
    }

    public function show(Request $request, Contract $contract): \Inertia\Response
    {
        $this->authorize('view', $contract);

        $item = $this->service->toItem($contract);

        $code = strtoupper((string) ($contract->status?->code ?: ''));
        $isSigned = ! empty($contract->getAttribute('signed_at'));
        $allowed = [
            'canEdit' => Gate::allows('update', $contract) && $code === 'BORR',
            // Delete permission only; service enforces BORR-only delete with domain error for UX
            'canDelete' => Gate::allows('delete', $contract),
            'canConfirm' => Gate::allows('update', $contract) && $code === 'BORR',
            'canTerminate' => Gate::allows('update', $contract) && in_array($code, ['VIG', 'EXT', 'VENC'], true),
            'canExtend' => Gate::allows('update', $contract) && in_array($code, ['VIG', 'EXT', 'VENC'], true) && $isSigned,
            'canSign' => Gate::allows('update', $contract) && $code === 'VIG' && ! $isSigned,
            'canToggleProcedure' => Gate::allows('update', $contract),
        ];

        $data = [
            'item' => $item,
            'hasEditRoute' => true,
            'canDelete' => $allowed['canDelete'],
            'allowedActions' => $allowed,
        ];

        return Inertia::render('catalogs/contract/show', $data);
    }

    public function setProcedure(Request $request, Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $contract);

        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $active = (bool) $validated['active'];
        $contract->setAttribute('has_active_procedure', $active);
        $contract->save();

        $message = $active
            ? 'Procedimiento marcado como activo para el contrato.'
            : 'Procedimiento marcado como inactivo para el contrato.';

        return redirect()
            ->route('catalogs.contract.show', $contract)
            ->with('success', $message);
    }

    public function setActive(Request $request, Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('setActive', $contract);
        $desired = (bool) $request->boolean('active');
        try {
            $this->serviceConcrete->setActive($contract, $desired);
            $actionText = $desired ? 'activado' : 'desactivado';

            return redirect()->route('catalogs.contract.index')
                ->with('success', 'El registro ha sido '.$actionText.' correctamente.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.contract.index')->with('error', $e->getMessage());
        }
    }

    public function destroy(Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('delete', $contract);
        try {
            $this->serviceConcrete->delete($contract);

            return redirect()->route('catalogs.contract.index')
                ->with('success', 'Registro eliminado correctamente.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.contract.index')->with('error', $e->getMessage());
        }
    }

    public function confirm(ConfirmContractRequest $request, Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $contract);
        try {
            $this->serviceConcrete->confirm($contract);

            return redirect()->route('catalogs.contract.index')->with('success', 'Contrato confirmado.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.contract.index')->with('error', $e->getMessage());
        }
    }

    public function terminate(TerminateContractRequest $request, Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $contract);
        try {
            $this->serviceConcrete->terminate($contract);

            return redirect()->route('catalogs.contract.index')->with('success', 'Contrato terminado.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.contract.index')->with('error', $e->getMessage());
        }
    }

    public function extend(ExtendContractRequest $request, Contract $contract): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $contract);
        try {
            $validated = $request->validated();
            $this->serviceConcrete->extend($contract, (string) $validated['new_end_date'], $validated['extension_pdf'] ?? null);

            return redirect()->route('catalogs.contract.index')->with('success', 'Contrato prorrogado.');
        } catch (\App\Exceptions\DomainActionException $e) {
            return redirect()->route('catalogs.contract.index')->with('error', $e->getMessage());
        }
    }

    /**
     * Securely download an extension PDF.
     */
    public function downloadExtension(Contract $contract, int $extension): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('view', $contract);
        $ext = DB::table('contract_extensions')
            ->where('id', $extension)
            ->where('contract_id', $contract->getKey())
            ->first(['id', 'pdf_path']);
        if (! $ext) {
            abort(404);
        }
        $pdfPath = (string) ($ext->pdf_path ?? '');
        if ($pdfPath === '') {
            abort(404);
        }
        // Convert public URL path (storage/...) to disk path (public/...)
        $diskPath = Str::startsWith($pdfPath, 'storage/') ? ('public/'.substr($pdfPath, 8)) : $pdfPath;
        if (! Storage::exists($diskPath)) {
            abort(404);
        }
        $filename = basename($pdfPath) ?: ('extension-'.$extension.'.pdf');

        return Storage::download($diskPath, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Override bulk to support contract-specific actions: confirm, terminate.
     */
    public function bulk(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,restore,forceDelete,setActive,confirm,terminate',
            'ids' => 'array|nullable',
            'ids.*' => 'integer|min:1',
            'active' => 'boolean|nullable',
        ]);

        $action = (string) $validated['action'];
        $ids = (array) ($validated['ids'] ?? []);
        $active = (bool) ($validated['active'] ?? true);

        if (empty($ids)) {
            return $this->fail($this->indexRouteName(), [], 'Se requieren IDs para la operación');
        }

        // Authorize bulk update-like operations
        if (in_array($action, ['confirm', 'terminate'], true)) {
            $this->authorize('bulk', [\App\Models\Contract::class, 'update']);
        } else {
            $this->authorize('bulk', [\App\Models\Contract::class, $action]);
        }

        try {
            $count = 0;
            switch ($action) {
                case 'confirm':
                    foreach ($ids as $id) {
                        $c = \App\Models\Contract::find($id);
                        if ($c) {
                            $this->serviceConcrete->confirm($c);
                            $count++;
                        }
                    }

                    return $this->ok($this->indexRouteName(), [], sprintf('%d contrato(s) confirmados', $count));
                case 'terminate':
                    foreach ($ids as $id) {
                        $c = \App\Models\Contract::find($id);
                        if ($c) {
                            $this->serviceConcrete->terminate($c);
                            $count++;
                        }
                    }

                    return $this->ok($this->indexRouteName(), [], sprintf('%d contrato(s) terminados', $count));
                case 'delete':
                    $count = $this->service->bulkDeleteByIds($ids);

                    return $this->ok($this->indexRouteName(), [], sprintf('%d registro(s) eliminados exitosamente', $count));
                case 'restore':
                    $count = $this->service->bulkRestoreByIds($ids);

                    return $this->ok($this->indexRouteName(), [], sprintf('%d registro(s) restaurados exitosamente', $count));
                case 'forceDelete':
                    $count = $this->service->bulkForceDeleteByIds($ids);

                    return $this->ok($this->indexRouteName(), [], sprintf('%d registro(s) eliminados permanentemente', $count));
                case 'setActive':
                    $count = $this->service->bulkSetActiveByIds($ids, $active);

                    return $this->ok($this->indexRouteName(), [], sprintf('%d registro(s) %s exitosamente', $count, $active ? 'activados' : 'desactivados'));
                default:
                    throw new \InvalidArgumentException('Acción no soportada');
            }
        } catch (\App\Exceptions\DomainActionException $e) {
            return $this->fail($this->indexRouteName(), [], $e->getMessage());
        } catch (\Throwable $e) {
            return $this->fail($this->indexRouteName(), [], 'Error durante la operación masiva. Inténtelo nuevamente.');
        }
    }
}
