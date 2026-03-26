<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Services\EconomicProfileServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\EconomicProfileExportRequest;
use App\Http\Requests\EconomicProfileSearchRequest;
use App\Http\Requests\EconomicProfileShowRequest;
use App\Http\Requests\EconomicProfileStatementRequest;
use App\Services\EconomicProfilePaymentHistoryPdfGenerator;
use App\Services\EconomicProfileStatementPdfGenerator;
use Inertia\Inertia;

class EconomicProfileController extends Controller
{
    public function __construct(private EconomicProfileServiceInterface $service) {}

    public function index(): \Inertia\Response
    {
        return Inertia::render('admin/economic-profile/index-modern');
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

        return Inertia::render('admin/economic-profile/concessionaire-ultra', $data);
    }

    public function showLocal(int $id, EconomicProfileShowRequest $request): \Inertia\Response
    {
        $at = $request->date('at');
        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only']);
        $data = $this->service->forLocal($id, $at, $filters);

        return Inertia::render('admin/economic-profile/local-ultra', $data);
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

    public function statement(EconomicProfileStatementRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $scope = (string) $request->string('scope');
        $id = (int) $request->integer('id');
        $document = (string) $request->string('document', 'statement');

        $tz = (string) config('app.timezone', 'America/Caracas');
        $at = $request->date('at');
        $atTs = $at
            ? \Illuminate\Support\Carbon::parse($at->format('Y-m-d'), $tz)->startOfDay()
            : \Illuminate\Support\Carbon::now($tz)->startOfDay();

        $filters = $request->only(['currency', 'kind', 'period_from', 'period_to', 'overdue_only', 'local_ids']);

        if ($document === 'payment_history') {
            $data = $scope === 'local'
                ? $this->service->paymentHistoryForLocal($id, $atTs, $filters)
                : $this->service->paymentHistoryForConcessionaire($id, $atTs, $filters);
        } else {
            $data = $scope === 'local'
                ? $this->service->forLocal($id, $atTs, $filters)
                : $this->service->forConcessionaire($id, $atTs, $filters);
        }

        $localIds = [];
        if ($scope !== 'local') {
            $localIds = is_array($filters['local_ids'] ?? null) ? $filters['local_ids'] : [];
        }

        $gen = $document === 'payment_history'
            ? app(EconomicProfilePaymentHistoryPdfGenerator::class)->render($data, $scope, $id, $atTs, $localIds)
            : app(EconomicProfileStatementPdfGenerator::class)->render($data, $scope, $id, $atTs, $localIds);

        return response($gen['raw'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$gen['filename'].'"',
        ]);
    }
}
