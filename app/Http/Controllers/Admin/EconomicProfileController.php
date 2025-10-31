<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\EconomicProfileServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\EconomicProfileExportRequest;
use App\Http\Requests\EconomicProfileSearchRequest;
use App\Http\Requests\EconomicProfileShowRequest;
use Inertia\Inertia;

class EconomicProfileController extends Controller
{
    public function __construct(private EconomicProfileServiceInterface $service) {}

    public function index(): \Inertia\Response
    {
        return Inertia::render('admin/economic-profile/index');
    }

    public function search(EconomicProfileSearchRequest $request): \Illuminate\Http\JsonResponse
    {
        $type = (string) $request->string('type');
        $q = (string) $request->string('q');
        $limit = (int) $request->integer('limit', 20);
        $items = $type === 'local' ? $this->service->searchLocals($q, $limit) : $this->service->searchConcessionaires($q, $limit);

        return response()->json(['items' => $items]);
    }

    public function showConcessionaire(int $id, EconomicProfileShowRequest $request): \Inertia\Response
    {
        $at = $request->date('at');
        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only']);
        $data = $this->service->forConcessionaire($id, $at, $filters);

        return Inertia::render('admin/economic-profile/concessionaire', $data);
    }

    public function showLocal(int $id, EconomicProfileShowRequest $request): \Inertia\Response
    {
        $at = $request->date('at');
        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only']);
        $data = $this->service->forLocal($id, $at, $filters);

        return Inertia::render('admin/economic-profile/local', $data);
    }

    public function export(EconomicProfileExportRequest $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $scope = (string) $request->string('scope');
        $id = (int) $request->integer('id');
        $format = (string) $request->string('format', 'csv');
        $at = $request->date('at');
        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only']);

        return $this->service->export($scope, $id, $format, $at, $filters);
    }
}
