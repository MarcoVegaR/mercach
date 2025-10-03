<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CondoExpenseStoreRequest;
use App\Http\Requests\CondoExpenseUpdateRequest;
use App\Models\CondoExpense;
use App\Models\CondoPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CondoExpenseController extends Controller
{
    public function index(Request $request, CondoPeriod $condo_period): JsonResponse
    {
        $this->authorize('view', $condo_period);

        $pageIndex = max(0, (int) $request->integer('pageIndex', 0));
        $pageSize = max(1, (int) $request->integer('pageSize', 10));
        $sortBy = (string) $request->input('sortBy', 'expense_date');
        $desc = (bool) $request->boolean('desc', false);
        $search = trim((string) $request->input('q', ''));

        $allowedSorts = [
            'id' => 'ce.id',
            'expense_date' => 'ce.expense_date',
            'invoice_number' => 'ce.invoice_number',
            'amount_usd_minor' => 'ce.amount_usd_minor',
            'type_name' => 'et.name',
        ];
        $sortCol = $allowedSorts[$sortBy] ?? 'ce.expense_date';
        $direction = $desc ? 'desc' : 'asc';

        $base = DB::table('condo_expenses as ce')
            ->leftJoin('expense_types as et', 'et.id', '=', 'ce.expense_type_id')
            ->where('ce.condo_period_id', $condo_period->getKey())
            ->whereNull('ce.deleted_at');

        if ($search !== '') {
            // Case + accent-insensitive search (Postgres): lower + translate
            $needle = mb_strtolower($search, 'UTF-8');
            $needle = strtr($needle, [
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
                'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
                'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c', 'ã' => 'a', 'õ' => 'o',
            ]);
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $needle).'%';

            $normalizeSql = function (string $column): string {
                // LOWER first, then strip common accents via translate (lowercase set)
                // Note: target length must match source length
                return "translate(lower($column), 'áéíóúüäëïöàèìòùâêîôûñçãõ', 'aeiouuaeioaeiouaeiouncao')";
            };

            $base->where(function ($q) use ($like, $normalizeSql) {
                $q->whereRaw($normalizeSql('et.name').' LIKE ?', [$like])
                    ->orWhereRaw($normalizeSql('ce.invoice_number').' LIKE ?', [$like])
                    ->orWhereRaw($normalizeSql('ce.note').' LIKE ?', [$like])
                    // Date formatted as text in Postgres
                    ->orWhereRaw("to_char(ce.expense_date, 'YYYY-MM-DD') LIKE ?", [$like]);
            });
        }

        $total = (clone $base)->count();

        $rows = $base
            ->orderBy($sortCol, $direction)
            ->offset($pageIndex * $pageSize)
            ->limit($pageSize)
            ->get([
                'ce.id',
                'ce.condo_period_id',
                'ce.expense_type_id',
                'et.name as type_name',
                'ce.amount_usd_minor',
                'ce.invoice_number',
                'ce.expense_date',
                'ce.attachment_path',
                'ce.note',
                'ce.is_active',
            ])
            ->map(function ($r) {
                $attachment = $r->attachment_path ? (string) $r->attachment_path : null;
                $url = $attachment ? (string) Storage::disk('public')->url($attachment) : null;
                // Force relative URL to avoid incorrect hosts in different environments
                $relative = $url ? (parse_url($url, PHP_URL_PATH) ?: $url) : null;

                return [
                    'id' => (int) $r->id,
                    'condo_period_id' => (int) $r->condo_period_id,
                    'expense_type_id' => (int) $r->expense_type_id,
                    'type_name' => (string) ($r->type_name ?? ''),
                    'amount_usd_minor' => (int) ($r->amount_usd_minor ?? 0),
                    'invoice_number' => $r->invoice_number ? (string) $r->invoice_number : null,
                    'expense_date' => $r->expense_date ? (string) $r->expense_date : null,
                    'attachment_url' => $relative,
                    'note' => $r->note ? (string) $r->note : null,
                    'is_active' => (bool) $r->is_active,
                ];
            })
            ->all();

        return response()->json([
            'rows' => $rows,
            'meta' => [
                'total' => $total,
                'pageIndex' => $pageIndex,
                'pageSize' => $pageSize,
            ],
        ]);
    }

    public function store(CondoExpenseStoreRequest $request, CondoPeriod $condo_period): JsonResponse
    {
        // Period state validation is now in CondoExpenseStoreRequest::after()
        $data = $request->validated();

        /** @var \App\Contracts\Services\CondoExpenseServiceInterface $svc */
        $svc = app(\App\Contracts\Services\CondoExpenseServiceInterface::class);
        $expense = $svc->createOne($condo_period, $data);

        // Updated totals for live KPI refresh
        $totals = [
            'expenses_count' => (int) DB::table('condo_expenses')->where('condo_period_id', $condo_period->getKey())->whereNull('deleted_at')->count(),
            'total_usd_minor' => (int) DB::table('condo_expenses')->where('condo_period_id', $condo_period->getKey())->whereNull('deleted_at')->sum('amount_usd_minor'),
        ];

        return response()->json([
            'success' => true,
            'id' => $expense->getKey(),
            'totals' => $totals,
        ]);
    }

    public function update(CondoExpenseUpdateRequest $request, CondoExpense $condo_expense): JsonResponse
    {
        // Period state validation is now in CondoExpenseUpdateRequest::after()
        /** @var CondoPeriod $period */
        $period = $condo_expense->period;

        $data = $request->validated();
        /** @var \App\Contracts\Services\CondoExpenseServiceInterface $svc */
        $svc = app(\App\Contracts\Services\CondoExpenseServiceInterface::class);
        $svc->updateOne($condo_expense, $data);

        $totals = [
            'expenses_count' => (int) DB::table('condo_expenses')->where('condo_period_id', $period->getKey())->whereNull('deleted_at')->count(),
            'total_usd_minor' => (int) DB::table('condo_expenses')->where('condo_period_id', $period->getKey())->whereNull('deleted_at')->sum('amount_usd_minor'),
        ];

        return response()->json(['success' => true, 'totals' => $totals]);
    }

    public function destroy(Request $request, CondoExpense $condo_expense): JsonResponse
    {
        /** @var CondoPeriod $period */
        $period = $condo_expense->period;
        if ($period->isFinal()) {
            return response()->json(['success' => false, 'message' => 'El período está finalizado y no puede modificarse.'], 422);
        }
        if ($period->hasCharges()) {
            return response()->json(['success' => false, 'message' => 'El período tiene cargos generados y no puede modificarse.'], 422);
        }
        // Check permission
        $this->authorize('update', $period);

        /** @var \App\Contracts\Services\CondoExpenseServiceInterface $svc */
        $svc = app(\App\Contracts\Services\CondoExpenseServiceInterface::class);
        $svc->deleteOne($condo_expense);

        $totals = [
            'expenses_count' => (int) DB::table('condo_expenses')->where('condo_period_id', $period->getKey())->whereNull('deleted_at')->count(),
            'total_usd_minor' => (int) DB::table('condo_expenses')->where('condo_period_id', $period->getKey())->whereNull('deleted_at')->sum('amount_usd_minor'),
        ];

        return response()->json(['success' => true, 'totals' => $totals]);
    }
}
