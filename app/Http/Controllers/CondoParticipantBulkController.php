<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Services\CondoParticipantServiceInterface;
use App\Contracts\Services\CondoPeriodServiceInterface;
use App\Http\Requests\CondoParticipantBulkRequest;
use App\Http\Requests\CondoParticipantSeedRequest;
use Illuminate\Http\RedirectResponse;

class CondoParticipantBulkController extends Controller
{
    public function __construct(
        private readonly CondoPeriodServiceInterface $periods,
        private readonly CondoParticipantServiceInterface $participants,
    ) {}

    public function seedDefaults(CondoParticipantSeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $period = $this->periods->upsertByMarketAndPeriod((int) $validated['market_id'], (string) $validated['period']);
        $this->authorize('update', $period);

        $count = $this->participants->seedDefaults($period->getKey());

        return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
            ->with('success', sprintf('%d participantes sembrados.', $count));
    }

    public function store(CondoParticipantBulkRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $period = $this->periods->upsertByMarketAndPeriod((int) $validated['market_id'], (string) $validated['period']);
        $this->authorize('update', $period);

        $count = $this->participants->bulkStore($period->getKey(), $validated['items']);

        return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
            ->with('success', sprintf('%d participante(s) actualizados.', $count));
    }

    public function bulkExcludeFiltered(CondoParticipantSeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $filters = (array) $request->input('filters', []);
        $period = $this->periods->upsertByMarketAndPeriod((int) $validated['market_id'], (string) $validated['period']);
        $this->authorize('update', $period);

        $count = $this->participants->bulkExcludeFiltered($period->getKey(), $filters);

        return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
            ->with('success', sprintf('%d participante(s) excluidos.', $count));
    }

    public function bulkIncludeFiltered(CondoParticipantSeedRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $filters = (array) $request->input('filters', []);
        $period = $this->periods->upsertByMarketAndPeriod((int) $validated['market_id'], (string) $validated['period']);
        $this->authorize('update', $period);

        $count = $this->participants->bulkIncludeFiltered($period->getKey(), $filters);

        return redirect()->route('condo.periods.show', ['condo_period' => $period->getKey()])
            ->with('success', sprintf('%d participante(s) incluidos.', $count));
    }
}
