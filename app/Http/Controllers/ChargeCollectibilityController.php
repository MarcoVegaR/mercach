<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\DomainActionException;
use App\Http\Requests\MarkChargesUncollectibleRequest;
use App\Http\Requests\RestoreChargesCollectibleRequest;
use App\Models\Charge;
use App\Services\ChargeCollectibilityService;
use Illuminate\Http\RedirectResponse;

class ChargeCollectibilityController extends Controller
{
    public function __construct(
        private ChargeCollectibilityService $service,
    ) {}

    public function mark(MarkChargesUncollectibleRequest $request, Charge $charge): RedirectResponse
    {
        return $this->markMany($request, (int) $charge->getKey());
    }

    public function markMany(MarkChargesUncollectibleRequest $request, ?int $routeChargeId = null): RedirectResponse
    {
        try {
            $result = $this->service->markUncollectible(
                $request->chargeIds($routeChargeId),
                (string) $request->validated('reason'),
                $request->user(),
            );

            return redirect()->back()->with('success', sprintf('%d cargo(s) declarados incobrables.', (int) $result['count']));
        } catch (DomainActionException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function restore(RestoreChargesCollectibleRequest $request, Charge $charge): RedirectResponse
    {
        return $this->restoreMany($request, (int) $charge->getKey());
    }

    public function restoreMany(RestoreChargesCollectibleRequest $request, ?int $routeChargeId = null): RedirectResponse
    {
        try {
            $result = $this->service->restoreCollectible(
                $request->chargeIds($routeChargeId),
                (string) $request->validated('reason'),
                $request->user(),
            );

            return redirect()->back()->with('success', sprintf('%d cargo(s) restaurados como cobrables.', (int) $result['count']));
        } catch (DomainActionException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
