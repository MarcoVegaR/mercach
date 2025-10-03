<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CondoParticipant;
use App\Models\CondoPeriod;
use App\Models\Local;
use App\Models\LocalStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CondoParticipantController extends Controller
{
    /**
     * Bulk upsert active participants without relying on ON CONFLICT against a partial index.
     * Updates existing active rows (deleted_at IS NULL) and inserts missing ones.
     *
     * @param  array<int, array{condo_period_id:int,local_id:int,area_m2_snapshot:string,included:bool,is_active:bool,created_at:\DateTimeInterface|string,updated_at:\DateTimeInterface|string}>  $rows
     */
    private function bulkUpsertActiveParticipants(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $cols = ['condo_period_id', 'local_id', 'area_m2_snapshot', 'included', 'is_active', 'created_at', 'updated_at'];
        $placeholders = [];
        $bindings = [];
        foreach ($rows as $r) {
            $placeholders[] = '('.implode(', ', array_fill(0, 7, '?')).')';
            foreach ($cols as $c) {
                $bindings[] = $r[$c];
            }
        }

        $valuesSql = implode(', ', $placeholders);
        $sql = "
WITH data AS (
  SELECT
    v.condo_period_id::bigint,
    v.local_id::bigint,
    v.area_m2_snapshot::numeric(8,2),
    v.included::boolean,
    v.is_active::boolean,
    v.created_at::timestamp,
    v.updated_at::timestamp
  FROM (VALUES $valuesSql) AS v(condo_period_id, local_id, area_m2_snapshot, included, is_active, created_at, updated_at)
),
updated AS (
  UPDATE condo_participants cp
  SET area_m2_snapshot = data.area_m2_snapshot,
      included = data.included,
      is_active = data.is_active,
      updated_at = data.updated_at
  FROM data
  WHERE cp.condo_period_id = data.condo_period_id
    AND cp.local_id = data.local_id
    AND cp.deleted_at IS NULL
  RETURNING cp.id
)
INSERT INTO condo_participants (condo_period_id, local_id, area_m2_snapshot, included, is_active, created_at, updated_at)
SELECT d.condo_period_id, d.local_id, d.area_m2_snapshot, d.included, d.is_active, d.created_at, d.updated_at
FROM data d
WHERE NOT EXISTS (
  SELECT 1
  FROM condo_participants cp
  WHERE cp.condo_period_id = d.condo_period_id
    AND cp.local_id = d.local_id
    AND cp.deleted_at IS NULL
);
";

        DB::statement($sql, $bindings);
    }

    public function index(Request $request, CondoPeriod $condo_period): JsonResponse
    {
        $this->authorize('view', $condo_period);

        $pageIndex = max(0, (int) $request->integer('pageIndex', 0));
        $pageSize = max(1, (int) $request->integer('pageSize', 10));
        $sortBy = (string) $request->input('sortBy', 'local_code');
        $desc = (bool) $request->boolean('desc', false);
        $search = trim((string) $request->input('q', ''));

        $allowedSorts = [
            'id' => 'cp.id',
            'local_id' => 'cp.local_id',
            'local_code' => 'l.code',
            'local_name' => 'l.name',
            'area_m2_snapshot' => 'cp.area_m2_snapshot',
            'created_at' => 'cp.created_at',
        ];
        $sortCol = $allowedSorts[$sortBy] ?? 'l.code';
        $direction = $desc ? 'desc' : 'asc';

        $base = DB::table('condo_participants as cp')
            ->join('locals as l', 'l.id', '=', 'cp.local_id')
            ->where('cp.condo_period_id', $condo_period->getKey())
            ->where('cp.included', false)
            ->whereNull('cp.deleted_at');

        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $base->where(function ($q) use ($like) {
                $q->where('l.code', 'ILIKE', $like)
                    ->orWhere('l.name', 'ILIKE', $like);
            });
        }

        $total = (clone $base)->count();

        $rows = $base
            ->orderBy($sortCol, $direction)
            ->offset($pageIndex * $pageSize)
            ->limit($pageSize)
            ->get([
                'cp.id',
                'cp.condo_period_id',
                'cp.local_id',
                'l.code as local_code',
                'l.name as local_name',
                'cp.area_m2_snapshot',
                'cp.included',
                'cp.is_active',
            ])
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'condo_period_id' => (int) $r->condo_period_id,
                    'local_id' => (int) $r->local_id,
                    'local_code' => (string) ($r->local_code ?? ''),
                    'local_name' => (string) ($r->local_name ?? ''),
                    'area_m2_snapshot' => (string) ($r->area_m2_snapshot ?? ''),
                    'included' => (bool) $r->included,
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

    public function excludeAll(Request $request, CondoPeriod $condo_period): JsonResponse
    {
        if ($condo_period->isFinal()) {
            return response()->json(['success' => false, 'message' => 'El período está finalizado y no puede modificarse.'], 422);
        }
        if ($condo_period->hasCharges()) {
            return response()->json(['success' => false, 'message' => 'El período tiene cargos generados y no puede modificarse.'], 422);
        }
        $this->authorize('update', $condo_period);

        // Only exclude locals with status DISP (Disponible)
        $availableStatusId = (int) (LocalStatus::query()->where('code', 'DISP')->value('id') ?? 0);
        $locals = Local::query()
            ->where('market_id', (int) $condo_period->getAttribute('market_id'))
            ->where('is_active', true)
            ->when($availableStatusId > 0, fn ($q) => $q->where('local_status_id', $availableStatusId))
            ->get(['id', 'area_m2']);

        if ($locals->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Sin locales activos en el mercado'], 422);
        }

        $now = now();
        $rows = [];
        foreach ($locals as $local) {
            $rows[] = [
                'condo_period_id' => $condo_period->getKey(),
                'local_id' => (int) $local->getAttribute('id'),
                'area_m2_snapshot' => (string) $local->getAttribute('area_m2'),
                'included' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->bulkUpsertActiveParticipants($rows);

        $participantsCount = (int) DB::table('condo_participants')
            ->where('condo_period_id', $condo_period->getKey())
            ->where('included', false)
            ->whereNull('deleted_at')
            ->count();

        return response()->json(['success' => true, 'totals' => ['participants_count' => $participantsCount]]);
    }

    public function store(Request $request, CondoPeriod $condo_period): JsonResponse
    {
        if ($condo_period->isFinal()) {
            return response()->json(['success' => false, 'message' => 'El período está finalizado y no puede modificarse.'], 422);
        }
        if ($condo_period->hasCharges()) {
            return response()->json(['success' => false, 'message' => 'El período tiene cargos generados y no puede modificarse.'], 422);
        }
        $this->authorize('update', $condo_period);

        $validated = $request->validate([
            'local_ids' => ['required', 'array', 'min:1'],
            'local_ids.*' => ['integer', 'min:1'],
        ]);

        $localIds = array_values(array_unique(array_map('intval', (array) $validated['local_ids'])));
        if (empty($localIds)) {
            return response()->json(['success' => false, 'message' => 'Sin locales'], 422);
        }

        $locals = Local::query()->whereIn('id', $localIds)->get(['id', 'area_m2']);
        if ($locals->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Locales no válidos'], 422);
        }

        $now = now();
        $rows = [];
        foreach ($locals as $local) {
            $rows[] = [
                'condo_period_id' => $condo_period->getKey(),
                'local_id' => (int) $local->getAttribute('id'),
                'area_m2_snapshot' => (string) $local->getAttribute('area_m2'),
                'included' => false,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk upsert without ON CONFLICT against partial index
        $this->bulkUpsertActiveParticipants($rows);

        $participantsCount = (int) DB::table('condo_participants')
            ->where('condo_period_id', $condo_period->getKey())
            ->where('included', false)
            ->whereNull('deleted_at')
            ->count();

        return response()->json(['success' => true, 'totals' => ['participants_count' => $participantsCount]]);
    }

    public function destroy(Request $request, CondoParticipant $condo_participant): JsonResponse
    {
        /** @var CondoPeriod $period */
        $period = $condo_participant->period;
        if ($period->isFinal()) {
            return response()->json(['success' => false, 'message' => 'El período está finalizado y no puede modificarse.'], 422);
        }
        if ($period->hasCharges()) {
            return response()->json(['success' => false, 'message' => 'El período tiene cargos generados y no puede modificarse.'], 422);
        }
        $this->authorize('update', $period);

        $condo_participant->delete();

        $participantsCount = (int) DB::table('condo_participants')
            ->where('condo_period_id', $period->getKey())
            ->where('included', false)
            ->whereNull('deleted_at')
            ->count();

        return response()->json(['success' => true, 'totals' => ['participants_count' => $participantsCount]]);
    }
}
