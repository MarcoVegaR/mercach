<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\CondoExpenseServiceInterface;
use App\Contracts\Services\CondoPeriodServiceInterface;
use App\Http\Requests\CondoExpenseBulkRequest;
use Illuminate\Http\RedirectResponse;

class CondoExpenseBulkController extends Controller
{
    public function __construct(
        private readonly CondoPeriodServiceInterface $periods,
        private readonly CondoExpenseServiceInterface $expenses,
    ) {}

    public function store(CondoExpenseBulkRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Resolve or create the CondoPeriod for (market, period)
        $period = $this->periods->upsertByMarketAndPeriod((int) $validated['market_id'], (string) $validated['period']);

        if ($period->isFinal()) {
            return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
                ->with('error', 'El período está finalizado y no puede modificarse.');
        }
        if ($period->hasCharges()) {
            return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
                ->with('error', 'El período tiene cargos generados y no puede modificarse.');
        }

        // Reuse parent policy: authorize updating the period to allow expense changes
        $this->authorize('update', $period);

        $count = $this->expenses->bulkStore($period->getKey(), $validated['items']);

        return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
            ->with('success', sprintf('%d gasto(s) procesados exitosamente.', $count));
    }
}
