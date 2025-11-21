<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\CompanyBankAccount;
use App\Models\Contract;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    /**
     * Show the bank validations report page.
     */
    public function bankValidations(Request $request): Response
    {
        $this->authorize('viewBankValidations', 'Report');

        // DataTable query params
        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 10), 100);
        $q = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'paid_on');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Nested filters
        $filters = (array) $request->input('filters', []);
        // Backward compatibility (date_from/date_to)
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $filters['paid_between'] = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }
        if ($request->filled('response_code')) {
            $filters['response_code'] = $request->input('response_code');
        }
        if ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        $allRows = $this->getBankValidationBaseRows($filters, $q);

        $sorted = $allRows->sortBy(function (array $row) use ($sort) {
            switch ($sort) {
                case 'reference':
                    return (string) ($row['reference'] ?? '');
                case 'amount_bs':
                    return (float) ($row['amount_bs'] ?? 0.0);
                case 'gateway_resp_code':
                    return (string) ($row['gateway_resp_code'] ?? '');
                case 'status':
                    return (string) ($row['status'] ?? '');
                case 'paid_on':
                default:
                    return (string) ($row['paid_on'] ?? '');
            }
        }, SORT_NATURAL, $dir === 'desc');

        $total = $sorted->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        if ($page > $lastPage) {
            $page = $lastPage;
        }
        $offset = ($page - 1) * $perPage;
        $pageRows = $sorted->slice($offset, $perPage)->values();

        $from = $total === 0 ? null : ($offset + 1);
        $to = $total === 0 ? null : ($offset + $pageRows->count());

        return Inertia::render('reports/bank-validations/index', [
            'rows' => $pageRows,
            'meta' => [
                'current_page' => $page,
                'from' => $from,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $to,
                'total' => $total,
            ],
            'responseCodes' => $this->getResponseCodes(),
        ]);
    }

    /**
     * Export bank validations report.
     */
    public function exportBankValidations(Request $request): SymfonyResponse
    {
        $this->authorize('exportBankValidations', 'Report');

        $q = trim((string) $request->input('q', ''));
        $filters = (array) $request->input('filters', []);

        // Backwards compatibility date_from/date_to
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $filters['paid_between'] = [
                'from' => $request->input('date_from'),
                'to' => $request->input('date_to'),
            ];
        }
        if ($request->filled('response_code')) {
            $filters['response_code'] = $request->input('response_code');
        }
        if ($request->filled('status')) {
            $filters['status'] = $request->input('status');
        }

        $rows = $this->getBankValidationBaseRows($filters, $q);

        $sorted = $rows->sortBy(function (array $row) {
            return (string) ($row['paid_on'] ?? '').'|'.(string) ($row['reference'] ?? '');
        }, SORT_NATURAL, true);

        if ($sorted->count() > 5000) {
            $sorted = $sorted->slice(0, 5000)->values();
        }

        $data = $sorted->map(function (array $row) {
            return [
                'Fecha de pago' => $row['paid_on'] ?? null,
                'Nro. Referencia' => $row['reference'] ?? null,
                'Cuenta/Origen' => $row['origin_account'] ?? null,
                'Cuenta/Destino' => $row['destination_account'] ?? null,
                'Monto' => number_format((float) ($row['amount_bs'] ?? 0.0), 2, ',', '.'),
                'Cedula/RIF' => $row['payer_document'] ?? null,
                'Codigo/Respuesta' => $row['gateway_resp_code'] ?? null,
                'Respuesta' => $row['gateway_message'] ?? null,
                'ReqId' => $row['req_id'] ?? null,
            ];
        });

        $format = (string) $request->input('format', 'csv');

        if ($format === 'json') {
            return response()->json($data);
        }

        // CSV export
        $filename = 'validaciones_bancarias_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Show the unsigned contracts report page.
     */
    public function contractsUnsigned(Request $request): Response
    {
        $this->authorize('viewContractsUnsigned', 'Report');

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 10), 100);
        $q = trim((string) $request->input('q', ''));
        $sort = (string) $request->input('sort', 'start_date');
        $dir = strtolower((string) $request->input('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $filters = (array) $request->input('filters', []);

        $query = Contract::query()
            ->with(['type:id,name', 'status:id,name,code'])
            ->whereNull('signed_at')
            ->whereNull('deleted_at');

        if ($q !== '') {
            $query->where('number', 'like', "%{$q}%");
        }

        if (! empty($filters['contract_type_id'])) {
            $query->where('contract_type_id', (int) $filters['contract_type_id']);
        }

        if (! empty($filters['contract_status_id'])) {
            $query->where('contract_status_id', (int) $filters['contract_status_id']);
        }

        $startBetween = (array) ($filters['start_between'] ?? []);
        if (! empty($startBetween['from'])) {
            $query->whereDate('start_date', '>=', (string) $startBetween['from']);
        }
        if (! empty($startBetween['to'])) {
            $query->whereDate('start_date', '<=', (string) $startBetween['to']);
        }

        $sortable = [
            'number' => 'number',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
        ];
        $orderCol = $sortable[$sort] ?? 'start_date';
        $query->orderBy($orderCol, $dir)->orderBy('id', 'desc');

        $contracts = $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();

        $rows = $contracts->getCollection()->map(function (Contract $c): array {
            return [
                'id' => $c->id,
                'number' => (string) ($c->number ?? ''),
                'contract_type' => (string) ($c->type->name ?? ''),
                'contract_status' => (string) ($c->status->name ?? ''),
                'contract_status_code' => (string) ($c->status->code ?? ''),
                'start_date' => (string) $c->start_date,
                'end_date' => $c->end_date ? (string) $c->end_date : null,
            ];
        })->all();

        // Filter options
        $types = DB::table('contract_types')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        $statuses = DB::table('contract_statuses')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($m) => ['id' => (int) $m->id, 'name' => (string) $m->name])
            ->all();

        return Inertia::render('reports/contracts-unsigned', [
            'rows' => $rows,
            'meta' => [
                'current_page' => $contracts->currentPage(),
                'from' => $contracts->firstItem(),
                'last_page' => $contracts->lastPage(),
                'per_page' => $contracts->perPage(),
                'to' => $contracts->lastItem(),
                'total' => $contracts->total(),
            ],
            'filterOptions' => [
                'contract_types' => $types,
                'contract_statuses' => $statuses,
            ],
        ]);
    }

    /**
     * Export unsigned contracts report.
     */
    public function exportContractsUnsigned(Request $request): SymfonyResponse
    {
        $this->authorize('exportContractsUnsigned', 'Report');

        $q = trim((string) $request->input('q', ''));
        $filters = (array) $request->input('filters', []);

        $query = Contract::query()
            ->with(['type:id,name', 'status:id,name,code'])
            ->whereNull('signed_at')
            ->whereNull('deleted_at');

        if ($q !== '') {
            $query->where('number', 'like', "%{$q}%");
        }

        if (! empty($filters['contract_type_id'])) {
            $query->where('contract_type_id', (int) $filters['contract_type_id']);
        }

        if (! empty($filters['contract_status_id'])) {
            $query->where('contract_status_id', (int) $filters['contract_status_id']);
        }

        $startBetween = (array) ($filters['start_between'] ?? []);
        if (! empty($startBetween['from'])) {
            $query->whereDate('start_date', '>=', (string) $startBetween['from']);
        }
        if (! empty($startBetween['to'])) {
            $query->whereDate('start_date', '<=', (string) $startBetween['to']);
        }

        $contracts = $query->limit(5000)->get();

        $data = $contracts->map(function (Contract $c): array {
            return [
                'Número' => (string) ($c->number ?? ''),
                'Tipo' => (string) ($c->type->name ?? ''),
                'Estado' => (string) ($c->status->name ?? ''),
                'Fecha inicio' => (string) $c->start_date,
                'Fecha fin' => $c->end_date ? (string) $c->end_date : '',
            ];
        });

        $format = (string) $request->input('format', 'csv');

        if ($format === 'json') {
            return response()->json($data);
        }

        $filename = 'contratos_sin_firma_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Show concessionaire changes per local report.
     */
    public function concessionaireChanges(Request $request): Response
    {
        $this->authorize('viewConcessionaireChanges', 'Report');

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 25);
        $perPage = min(max($perPage, 10), 200);

        $filters = (array) $request->input('filters', []);
        $changedBetween = (array) ($filters['changed_between'] ?? []);

        $rows = DB::table('contract_local as cl')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
            ->join('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'c.id')->where('cc.is_primary', true);
            })
            ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNull('cn.deleted_at')
            ->select([
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'c.id as contract_id',
                'c.number as contract_number',
                'c.start_date as contract_start_date',
                'cn.id as concessionaire_id',
                'cn.full_name as concessionaire_name',
            ])
            ->orderBy('l.id')
            ->orderBy('c.start_date')
            ->get();

        $events = [];
        $grouped = $rows->groupBy('local_id');
        foreach ($grouped as $localId => $list) {
            $sorted = $list->sortBy('contract_start_date')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = $sorted[$i - 1];
                $curr = $sorted[$i];
                if ((int) $prev->concessionaire_id === (int) $curr->concessionaire_id) {
                    continue;
                }

                $changeDate = (string) $curr->contract_start_date;

                $events[] = [
                    'local_id' => (int) $localId,
                    'local_code' => (string) ($curr->local_code ?? ''),
                    'local_name' => (string) ($curr->local_name ?? ''),
                    'change_date' => $changeDate,
                    'old_concessionaire_id' => (int) $prev->concessionaire_id,
                    'old_concessionaire_name' => (string) $prev->concessionaire_name,
                    'new_concessionaire_id' => (int) $curr->concessionaire_id,
                    'new_concessionaire_name' => (string) $curr->concessionaire_name,
                    'old_contract_id' => (int) $prev->contract_id,
                    'old_contract_number' => (string) $prev->contract_number,
                    'new_contract_id' => (int) $curr->contract_id,
                    'new_contract_number' => (string) $curr->contract_number,
                ];
            }
        }

        // Apply date filter in memory
        $events = array_values(array_filter($events, function (array $e) use ($changedBetween): bool {
            $date = (string) $e['change_date'];
            if ($date === '') {
                return false;
            }
            if (! empty($changedBetween['from']) && $date < (string) $changedBetween['from']) {
                return false;
            }
            if (! empty($changedBetween['to']) && $date > (string) $changedBetween['to']) {
                return false;
            }

            return true;
        }));

        // Simple array pagination
        $total = count($events);
        $lastPage = (int) max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $pageItems = array_slice($events, $offset, $perPage);

        return Inertia::render('reports/concessionaire-changes', [
            'rows' => $pageItems,
            'meta' => [
                'current_page' => $page,
                'from' => $total > 0 ? $offset + 1 : 0,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'to' => $total > 0 ? min($offset + $perPage, $total) : 0,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Export concessionaire changes report.
     */
    public function exportConcessionaireChanges(Request $request): SymfonyResponse
    {
        $this->authorize('exportConcessionaireChanges', 'Report');

        $filters = (array) $request->input('filters', []);
        $changedBetween = (array) ($filters['changed_between'] ?? []);

        $rows = DB::table('contract_local as cl')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
            ->join('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'c.id')->where('cc.is_primary', true);
            })
            ->join('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at')
            ->whereNull('cn.deleted_at')
            ->select([
                'l.id as local_id',
                'l.code as local_code',
                'l.name as local_name',
                'c.id as contract_id',
                'c.number as contract_number',
                'c.start_date as contract_start_date',
                'cn.id as concessionaire_id',
                'cn.full_name as concessionaire_name',
            ])
            ->orderBy('l.id')
            ->orderBy('c.start_date')
            ->get();

        $events = [];
        $grouped = $rows->groupBy('local_id');
        foreach ($grouped as $localId => $list) {
            $sorted = $list->sortBy('contract_start_date')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $prev = $sorted[$i - 1];
                $curr = $sorted[$i];
                if ((int) $prev->concessionaire_id === (int) $curr->concessionaire_id) {
                    continue;
                }

                $changeDate = (string) $curr->contract_start_date;

                $events[] = [
                    'Fecha cambio' => $changeDate,
                    'Local' => (string) ($curr->local_code ?: $curr->local_name ?: $localId),
                    'Cesionario anterior' => (string) $prev->concessionaire_name,
                    'Cesionario nuevo' => (string) $curr->concessionaire_name,
                    'Contrato anterior' => (string) $prev->contract_number,
                    'Contrato nuevo' => (string) $curr->contract_number,
                ];
            }
        }

        // Apply date filter
        $events = array_values(array_filter($events, function (array $e) use ($changedBetween): bool {
            $date = (string) $e['Fecha cambio'];
            if ($date === '') {
                return false;
            }
            if (! empty($changedBetween['from']) && $date < (string) $changedBetween['from']) {
                return false;
            }
            if (! empty($changedBetween['to']) && $date > (string) $changedBetween['to']) {
                return false;
            }

            return true;
        }));

        $data = collect($events);

        $format = (string) $request->input('format', 'csv');

        if ($format === 'json') {
            return response()->json($data);
        }

        $filename = 'cambios_cesionarios_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Show recovered locals report.
     */
    public function localsRecovered(Request $request): Response
    {
        $this->authorize('viewLocalsRecovered', 'Report');

        $page = max(1, (int) $request->input('page', 1));
        $perPage = (int) $request->input('per_page', 25);
        $perPage = min(max($perPage, 10), 200);

        $filters = (array) $request->input('filters', []);
        $recoveredBetween = (array) ($filters['recovered_between'] ?? []);

        $query = DB::table('contract_status_history as csh')
            ->join('contracts as c', 'c.id', '=', 'csh.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->leftJoin('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'c.id')->where('cc.is_primary', true);
            })
            ->leftJoin('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->where('csh.to_code', '=', 'TERM')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at');

        if (! empty($recoveredBetween['from'])) {
            $query->whereDate('csh.occurred_at', '>=', (string) $recoveredBetween['from']);
        }
        if (! empty($recoveredBetween['to'])) {
            $query->whereDate('csh.occurred_at', '<=', (string) $recoveredBetween['to']);
        }

        $query->orderBy('csh.occurred_at', 'desc')->orderBy('l.id');

        $paginator = $query->paginate($perPage, [
            'csh.occurred_at as recovered_at',
            'l.id as local_id',
            'l.code as local_code',
            'l.name as local_name',
            'm.name as market_name',
            'c.id as contract_id',
            'c.number as contract_number',
            'cn.full_name as concessionaire_name',
        ], 'page', $page)->withQueryString();

        $rows = $paginator->getCollection()->map(static function ($row): array {
            return [
                'recovered_at' => (string) $row->recovered_at,
                'local_id' => (int) $row->local_id,
                'local_code' => (string) ($row->local_code ?? ''),
                'local_name' => (string) ($row->local_name ?? ''),
                'market_name' => (string) ($row->market_name ?? ''),
                'contract_id' => (int) $row->contract_id,
                'contract_number' => (string) ($row->contract_number ?? ''),
                'concessionaire_name' => (string) ($row->concessionaire_name ?? ''),
            ];
        })->all();

        return Inertia::render('reports/locals-recovered', [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Export recovered locals report.
     */
    public function exportLocalsRecovered(Request $request): SymfonyResponse
    {
        $this->authorize('exportLocalsRecovered', 'Report');

        $filters = (array) $request->input('filters', []);
        $recoveredBetween = (array) ($filters['recovered_between'] ?? []);

        $query = DB::table('contract_status_history as csh')
            ->join('contracts as c', 'c.id', '=', 'csh.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('locals as l', 'l.id', '=', 'cl.local_id')
            ->leftJoin('markets as m', 'm.id', '=', 'l.market_id')
            ->leftJoin('concessionaire_contract as cc', function ($join): void {
                $join->on('cc.contract_id', '=', 'c.id')->where('cc.is_primary', true);
            })
            ->leftJoin('concessionaires as cn', 'cn.id', '=', 'cc.concessionaire_id')
            ->where('csh.to_code', '=', 'TERM')
            ->whereNull('c.deleted_at')
            ->whereNull('l.deleted_at');

        if (! empty($recoveredBetween['from'])) {
            $query->whereDate('csh.occurred_at', '>=', (string) $recoveredBetween['from']);
        }
        if (! empty($recoveredBetween['to'])) {
            $query->whereDate('csh.occurred_at', '<=', (string) $recoveredBetween['to']);
        }

        $rows = $query
            ->orderBy('csh.occurred_at', 'desc')
            ->limit(5000)
            ->get([
                'csh.occurred_at as recovered_at',
                'l.code as local_code',
                'l.name as local_name',
                'm.name as market_name',
                'c.number as contract_number',
                'cn.full_name as concessionaire_name',
            ]);

        $data = $rows->map(static function ($row): array {
            return [
                'Fecha recuperación' => (string) $row->recovered_at,
                'Local' => (string) ($row->local_code ?: $row->local_name ?: ''),
                'Mercado' => (string) ($row->market_name ?? ''),
                'Contrato' => (string) ($row->contract_number ?? ''),
                'Cesionario' => (string) ($row->concessionaire_name ?? ''),
            ];
        });

        $format = (string) $request->input('format', 'csv');

        if ($format === 'json') {
            return response()->json($data);
        }

        $filename = 'locales_recuperados_'.date('Y-m-d_His').'.csv';

        $callback = function () use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            if ($data->isNotEmpty()) {
                fputcsv($file, array_keys($data->first()));
                foreach ($data as $row) {
                    fputcsv($file, $row);
                }
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function getBankValidationBaseRows(array $filters, string $q)
    {
        $q = trim($q);

        $rows = collect();

        $paymentsQuery = Payment::query()
            ->with(['companyBankAccount.bank', 'originBank'])
            ->where(function ($w) {
                $w->whereNotNull('gateway_response')
                    ->orWhereNotNull('gateway_resp_code')
                    ->orWhereNotNull('gateway_message');
            });

        if ($q !== '') {
            $paymentsQuery->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('payer_document_number', 'like', "%{$q}%")
                    ->orWhere('payer_account_number', 'like', "%{$q}%");
            });
        }

        $paidBetween = (array) ($filters['paid_between'] ?? []);
        if (! empty($paidBetween['from'])) {
            $paymentsQuery->whereDate('paid_on', '>=', (string) $paidBetween['from']);
        }
        if (! empty($paidBetween['to'])) {
            $paymentsQuery->whereDate('paid_on', '<=', (string) $paidBetween['to']);
        }
        if (! empty($filters['response_code'])) {
            $paymentsQuery->where('gateway_resp_code', (string) $filters['response_code']);
        }

        $payments = $paymentsQuery->get();

        $paymentRows = $payments->map(function ($payment) {
            $reqId = null;
            $raw = $payment->getAttribute('gateway_response');
            if (is_array($raw)) {
                $reqId = $raw['sReqId'] ?? null;
            } elseif (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $reqId = $decoded['sReqId'] ?? null;
                }
            }

            $companyAccount = null;
            if ($payment->companyBankAccount) {
                $bank = $payment->companyBankAccount->bank;
                $bankName = $bank ? (string) $bank->name : '';
                $accountNumber = (string) ($payment->companyBankAccount->account_number ?? '');
                $companyAccount = trim(($bankName !== '' ? $bankName.' ' : '').$accountNumber);
            }

            $payerAccount = $payment->payer_account_number;
            $payerPhone = $payment->payer_phone_e164;

            $origin = null;
            if (strtoupper($payment->method ?? '') === 'PMOV') {
                $origin = $payerPhone;
            } else {
                $origin = $payerAccount;
            }

            return [
                'id' => (int) $payment->id,
                'paid_on' => (string) $payment->paid_on,
                'reference' => (string) $payment->reference,
                'origin_account' => $origin,
                'destination_account' => $companyAccount,
                'amount_bs' => (float) $payment->amount_bs_minor / 100.0,
                'payer_document' => (string) $payment->payer_document_number,
                'gateway_resp_code' => (string) ($payment->gateway_resp_code ?? ''),
                'gateway_message' => (string) ($payment->gateway_message ?? ''),
                'req_id' => $reqId,
                'status' => (string) ($payment->status ?? ''),
                'method' => (string) ($payment->method ?? ''),
            ];
        });

        $rows = $rows->concat($paymentRows);

        $auditQuery = Audit::query()
            ->where('event', 'payment.verify_failed')
            ->where('auditable_type', Payment::class)
            ->where('auditable_id', 0)
            ->orderByDesc('id');

        $audits = $auditQuery->limit(2000)->get();

        $paidFrom = (string) ($paidBetween['from'] ?? '');
        $paidTo = (string) ($paidBetween['to'] ?? '');
        $responseCodeFilter = (string) ($filters['response_code'] ?? '');

        // Build audit rows grouped by logical attempt key to avoid duplicates
        $auditRowsByKey = [];

        foreach ($audits as $audit) {
            $nvRaw = $audit->new_values;
            $nv = is_array($nvRaw) ? $nvRaw : [];
            $input = (array) ($nv['input'] ?? []);
            $message = (string) ($nv['message'] ?? '');

            // Try to resolve ReqId from top-level new_values or nested __verify_result
            $auditReqId = $nv['req_id'] ?? null;
            if ($auditReqId === null && isset($input['__verify_result']) && is_array($input['__verify_result'])) {
                $vr = $input['__verify_result'];
                if (! empty($vr['req_id']) && is_string($vr['req_id'])) {
                    $auditReqId = $vr['req_id'];
                }
            }

            $paidOn = (string) ($input['paid_on'] ?? '');
            if ($paidFrom !== '' && $paidOn !== '' && $paidOn < $paidFrom) {
                continue;
            }
            if ($paidTo !== '' && $paidOn !== '' && $paidOn > $paidTo) {
                continue;
            }

            $reference = (string) ($input['reference'] ?? '');
            $payerDocument = (string) ($input['payer_document_number'] ?? '');
            $payerAccount = (string) ($input['payer_account_number'] ?? '');

            if ($q !== '') {
                $haystack = [$reference, $payerDocument, $payerAccount];
                $matched = false;
                foreach ($haystack as $field) {
                    if ($field !== '' && stripos($field, $q) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (! $matched) {
                    continue;
                }
            }

            $code = null;
            if (preg_match('/c[oó]digo\s+([0-9A-Z]+)/iu', $message, $m)) {
                $code = strtoupper((string) $m[1]);
            }
            if ($responseCodeFilter !== '' && $code !== $responseCodeFilter) {
                continue;
            }

            $companyAccount = null;
            $companyId = (int) ($input['company_bank_account_id'] ?? 0);
            if ($companyId > 0) {
                /** @var null|CompanyBankAccount $acc */
                $acc = CompanyBankAccount::query()->with('bank')->find($companyId);
                if ($acc) {
                    $bank = $acc->bank;
                    $bankName = $bank ? (string) $bank->name : '';
                    $accountNumber = (string) ($acc->account_number ?? '');
                    $companyAccount = trim(($bankName !== '' ? $bankName.' ' : '').$accountNumber);
                }
            }

            $method = strtoupper((string) ($input['method'] ?? ''));
            $payerPhone = (string) ($input['payer_phone_e164'] ?? '');

            $origin = null;
            if ($method === 'PMOV') {
                $origin = $payerPhone;
            } else {
                $origin = $payerAccount;
            }

            $amountMinor = (int) ($input['amount_bs_minor'] ?? 0);

            $row = [
                'id' => -((int) $audit->id),
                'paid_on' => $paidOn !== '' ? $paidOn : (string) $audit->created_at,
                'reference' => $reference,
                'origin_account' => $origin,
                'destination_account' => $companyAccount,
                'amount_bs' => (float) $amountMinor / 100.0,
                'payer_document' => $payerDocument,
                'gateway_resp_code' => $code,
                'gateway_message' => $message,
                'req_id' => $auditReqId !== null ? (string) $auditReqId : null,
                'status' => 'NOT_SAVED',
                'method' => $method,
            ];

            // Logical key for an attempt: date + reference + debtor + account + method + amount
            $keyParts = [
                (string) $row['paid_on'],
                $reference,
                $payerDocument,
                $payerAccount,
                $method,
                (string) $amountMinor,
            ];
            $attemptKey = implode('|', $keyParts);

            if (! array_key_exists($attemptKey, $auditRowsByKey)) {
                $auditRowsByKey[$attemptKey] = $row;
            } else {
                $existing = $auditRowsByKey[$attemptKey];
                $existingReq = (string) ($existing['req_id'] ?? '');
                $newReq = (string) ($row['req_id'] ?? '');
                if ($newReq !== '' && $existingReq === '') {
                    $auditRowsByKey[$attemptKey] = $row;
                }
            }
        }

        foreach ($auditRowsByKey as $row) {
            $rows->push($row);
        }

        if (! empty($filters['status'])) {
            $statusFilter = (string) $filters['status'];
            $rows = $rows->filter(function (array $row) use ($statusFilter) {
                return (string) ($row['status'] ?? '') === $statusFilter;
            })->values();
        }

        return $rows;
    }

    /**
     * Get common response codes for filter dropdown.
     */
    /**
     * @return array<int, array{code:string,label:string}>
     */
    private function getResponseCodes(): array
    {
        return [
            ['code' => 'ACCP', 'label' => 'ACCP - Transacción Aprobada'],
            ['code' => '00', 'label' => '00 - Aprobado'],
            ['code' => '831', 'label' => '831 - Transacción ya fue validada'],
            ['code' => '830', 'label' => '830 - Descripción de error no disponible'],
            ['code' => 'BE11', 'label' => 'BE11 - Id. Emisor no corresponde'],
            ['code' => '706', 'label' => '706 - Cod. Banco de numero cuenta invalido'],
            ['code' => '707', 'label' => '707 - Transacción duplicada'],
            ['code' => '708', 'label' => '708 - Cuenta destino inválida'],
            ['code' => '709', 'label' => '709 - Referencia no encontrada'],
            ['code' => '710', 'label' => '710 - Monto no coincide'],
        ];
    }
}
