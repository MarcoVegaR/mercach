<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\CondoPeriodServiceInterface;
use App\Exceptions\DomainActionException;
use App\Http\Requests\CondoPeriodFinalizeRequest;
use App\Http\Requests\CondoPeriodIndexRequest;
use App\Http\Requests\CondoPeriodUpsertRequest;
use App\Models\CondoPeriod;
use App\Models\ExpenseType;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CondoPeriodController extends BaseIndexController
{
    private CondoPeriodServiceInterface $serviceConcrete;

    public function __construct(CondoPeriodServiceInterface $service)
    {
        parent::__construct($service);
        $this->serviceConcrete = $service;
    }

    protected function policyModel(): string
    {
        return \App\Models\CondoPeriod::class;
    }

    protected function view(): string
    {
        return 'condo/periods/index';
    }

    /**
     * Display a listing of periods with market options for creation dialog.
     */
    public function index(Request $request): \Inertia\Response
    {
        $this->authorize('viewAny', $this->policyModel());

        $requestClass = $this->indexRequestClass();
        $validatedRequest = $requestClass::createFrom($request);
        $validatedRequest->setContainer(app());
        $validatedRequest->setRedirector(app('redirect'));
        $validatedRequest->validateResolved();

        $query = $validatedRequest->toListQuery();

        $result = $this->service->list($query);

        $response = Inertia::render($this->view(), $result);

        // Stats: total periods, active periods, and total expenses amount (excluding soft-deleted)
        $stats = [
            'total' => \App\Models\CondoPeriod::count(),
            'active' => \App\Models\CondoPeriod::where('is_active', true)->count(),
            'total_usd_minor' => (int) DB::table('condo_expenses')
                ->whereNull('deleted_at')
                ->sum('amount_usd_minor'),
        ];

        // Compute USD/m² for selected market + period when filters provided
        try {
            // Use the validated request (it maps period_month -> period in prepareForValidation)
            $filters = (array) $validatedRequest->input('filters', []);
            $marketId = (int) ($filters['market_id'] ?? 0);
            $period = (string) ($filters['period'] ?? ''); // YYYY-MM-01
            if ($marketId > 0 && $period !== '') {
                /** @var \App\Services\Charges\CondoUsdCalculator $calc */
                $calc = app(\App\Services\Charges\CondoUsdCalculator::class);
                $rows = $calc->calculate(['market_id' => $marketId, 'period' => $period]);
                $unit = null;
                if (! empty($rows)) {
                    foreach ($rows as $r) {
                        if (array_key_exists('meta_unit_minor', $r) && $r['meta_unit_minor'] !== null) {
                            $unit = (int) $r['meta_unit_minor'];
                            break;
                        }
                    }
                }
                if ($unit !== null) {
                    $stats['unit_usd_minor'] = $unit; // integer minor units
                }
            }
        } catch (\Throwable $e) {
            // ignore unit stat errors
        }

        // Provide markets for creation combobox (only active ones)
        $markets = Market::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn ($m) => [
                'id' => (int) $m->getAttribute('id'),
                'code' => (string) ($m->getAttribute('code') ?? ''),
                'name' => (string) ($m->getAttribute('name') ?? ''),
            ])->values();

        $response->with('options', [
            'markets' => $markets,
        ]);
        $response->with('stats', $stats);

        return $response;
    }

    protected function indexRequestClass(): string
    {
        return CondoPeriodIndexRequest::class;
    }

    protected function indexRouteName(): string
    {
        return 'condo.periods.index';
    }

    protected function exportPermission(): string
    {
        return 'condo_period.export';
    }

    protected function allowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'json'];
    }

    public function upsert(CondoPeriodUpsertRequest $request): RedirectResponse
    {
        $this->authorize('create', $this->policyModel());
        $validated = $request->validated();
        $period = $this->serviceConcrete->upsertByMarketAndPeriod((int) $validated['market_id'], (string) $validated['period']);

        return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()]);
    }

    public function show(Request $request, CondoPeriod $condo_period): \Inertia\Response
    {
        $this->authorize('view', $condo_period);

        $with = (array) $request->input('with', []);
        if (empty($with)) {
            $with = ['expenses', 'participants', 'participants.local'];
        }
        $withCount = (array) $request->input('withCount', []);

        $showData = $this->serviceConcrete->loadShowData($condo_period, $with, $withCount);
        $data = array_merge($showData, [
            'hasEditRoute' => false,
        ]);

        // Options for workspace tabs
        $expenseTypes = ExpenseType::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($e) => [
                'id' => (int) $e->getAttribute('id'),
                'name' => (string) ($e->getAttribute('name') ?? ''),
                'code' => (string) ($e->getAttribute('code') ?? ''),
            ])->values();

        $data['options'] = [
            'expense_types' => $expenseTypes,
        ];

        return Inertia::render('condo/periods/workspace', $data);
    }

    public function finalize(CondoPeriodFinalizeRequest $request, CondoPeriod $condo_period): RedirectResponse
    {
        $this->authorize('finalize', $condo_period);
        // Execute domain rule (will throw if not allowed)
        $this->serviceConcrete->finalize($condo_period, $request->user());

        return redirect()->route('condo.periods.show', ['condo_period' => $condo_period->getKey()])
            ->with('success', 'Período confirmado a FINAL.');
    }

    public function reopen(Request $request, CondoPeriod $condo_period): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('reopen', $condo_period);
        try {
            $this->serviceConcrete->reopen($condo_period, $request->user());

            return redirect()->route('condo.periods.show', ['condo_period' => $condo_period->getKey()])
                ->with('success', 'Período reabierto a DRAFT.');
        } catch (DomainActionException $e) {
            return redirect()->route('condo.periods.show', ['condo_period' => $condo_period->getKey()])
                ->with('error', $e->getMessage());
        }
    }

    public function setActive(Request $request, CondoPeriod $condo_period): RedirectResponse
    {
        $this->authorize('setActive', $condo_period);
        $desired = (bool) $request->boolean('active');
        $this->service->setActive($condo_period, $desired);

        return redirect()->route('condo.periods.index')
            ->with('success', $desired ? 'Período activado.' : 'Período desactivado.');
    }

    public function destroy(CondoPeriod $condo_period): RedirectResponse
    {
        $this->authorize('delete', $condo_period);
        $this->serviceConcrete->deleteCascade($condo_period);

        return redirect()->route('condo.periods.index')
            ->with('success', 'Período eliminado (con gastos y participantes).');
    }

    /**
     * Bulk delete with cascade (only 'delete' action is supported for CondoPeriod).
     */
    public function bulk(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);

        $this->authorize('bulk', [$this->policyModel(), 'delete']);

        $deleted = method_exists($this->serviceConcrete, 'bulkDeleteCascadeByIds')
            ? $this->serviceConcrete->bulkDeleteCascadeByIds($validated['ids'])
            : $this->service->bulkDeleteByIds($validated['ids']);

        return redirect()->route('condo.periods.index')
            ->with('success', sprintf('%d período(s) eliminados.', $deleted));
    }
}
