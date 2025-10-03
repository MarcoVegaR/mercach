<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CondoPeriodRepositoryInterface;
use App\Contracts\Services\CondoPeriodServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\CondoPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CondoPeriodService extends BaseService implements CondoPeriodServiceInterface
{
    public function __construct(
        CondoPeriodRepositoryInterface $repo,
        \Psr\Container\ContainerInterface $container,
    ) {
        parent::__construct($repo, $container);
    }

    public function upsertByMarketAndPeriod(int $marketId, string $period): CondoPeriod
    {
        return $this->transaction(function () use ($marketId, $period) {
            /** @var CondoPeriod $model */
            $model = CondoPeriod::query()->firstOrCreate(
                ['market_id' => $marketId, 'period' => $period],
                ['status' => 'DRAFT', 'is_active' => true]
            );

            return $model;
        });
    }

    public function finalize(CondoPeriod $period, User $by): CondoPeriod
    {
        if ($period->isFinal()) {
            throw new DomainActionException('El período ya está FINAL.');
        }
        if (! $period->isDraft()) {
            throw new DomainActionException('Solo se puede confirmar un período en DRAFT.');
        }
        if ($period->hasCharges()) {
            throw new DomainActionException('No se puede confirmar: el período tiene cargos.');
        }
        if (! $period->expenses()->exists()) {
            throw new DomainActionException('No se puede confirmar un período sin gastos.');
        }

        return $this->transaction(function () use ($period, $by) {
            $period->fill([
                'status' => 'FINAL',
                'finalized_at' => Carbon::now(),
                'finalized_by_id' => $by->getKey(),
            ]);
            $period->save();

            return $period->fresh();
        });
    }

    public function reopen(CondoPeriod $period, User $by): CondoPeriod
    {
        if ($period->isDraft()) {
            throw new DomainActionException('El período ya está en DRAFT.');
        }
        if (! $period->isFinal()) {
            throw new DomainActionException('Solo se puede reabrir un período FINAL.');
        }
        if ($period->hasCharges()) {
            throw new DomainActionException('No se puede reabrir: el período tiene cargos.');
        }

        return $this->transaction(function () use ($period, $by) {
            $period->fill([
                'status' => 'DRAFT',
                'reopened_at' => Carbon::now(),
                'reopened_by_id' => $by->getKey(),
            ]);
            $period->save();

            return $period->fresh();
        });
    }

    public function deleteCascade(Model|int|string $modelOrId): bool
    {
        return $this->transaction(function () use ($modelOrId) {
            /** @var CondoPeriod $period */
            $period = $modelOrId instanceof Model ? $modelOrId : $this->repo->findOrFailById($modelOrId);

            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('No se puede eliminar: período FINAL o con cargos.');
            }

            // Soft-delete children first to preserve traceability
            $period->expenses()->delete();
            $period->participants()->delete();

            return (bool) $period->delete();
        });
    }

    /**
     * @param  CondoPeriod  $model
     * @param  array<string>  $with
     * @param  array<string>  $withCount
     * @return array{item: array<string,mixed>, meta: array<string,mixed>}
     */
    public function loadShowData(Model $model, array $with = [], array $withCount = []): array
    {
        // Allow only specific relations
        $allowedWith = array_intersect($with, ['expenses', 'participants', 'participants.local']);
        $allowedWithCount = array_intersect($withCount, ['expenses', 'participants']);

        if (! empty($allowedWith)) {
            // For expenses, limit selected columns for performance
            $load = [];
            if (in_array('expenses', $allowedWith, true)) {
                $load['expenses'] = function ($q) {
                    $q->select(['id', 'condo_period_id', 'expense_type_id', 'amount_usd_minor', 'invoice_number', 'expense_date', 'attachment_path', 'note', 'is_active']);
                };
            }
            if (in_array('participants', $allowedWith, true)) {
                $load['participants'] = function ($q) {
                    $q->select(['id', 'condo_period_id', 'local_id', 'area_m2_snapshot', 'included', 'is_active']);
                };
            }
            if (in_array('participants.local', $allowedWith, true)) {
                $load['participants.local'] = function ($q) {
                    $q->select(['id', 'code', 'name']);
                };
            }
            $model->load($load);
        }

        $counts = ['expenses', 'participants'];
        if (! empty($allowedWithCount)) {
            $counts = array_unique(array_merge($counts, $allowedWithCount));
        }
        $model->loadCount($counts);

        // Append totals if not present (avoid calling relations on generic Model for PHPStan)
        /** @var CondoPeriod $model */
        $attrTotal = $model->getAttribute('total_usd_minor');
        $totalMinor = is_null($attrTotal)
            ? (int) \Illuminate\Support\Facades\DB::table('condo_expenses')->where('condo_period_id', $model->getKey())->whereNull('deleted_at')->sum('amount_usd_minor')
            : (int) $attrTotal;
        $attrIncluded = $model->getAttribute('participants_included_count');
        $includedCount = is_null($attrIncluded)
            ? (int) \Illuminate\Support\Facades\DB::table('condo_participants')->where('condo_period_id', $model->getKey())->where('included', true)->whereNull('deleted_at')->count()
            : (int) $attrIncluded;

        $item = $this->toItem($model);
        $item['total_usd_minor'] = $totalMinor;
        $item['participants_included'] = $includedCount;

        return [
            'item' => $item,
            'meta' => [
                'loaded_relations' => array_keys($model->getRelations()),
                'loaded_counts' => $counts,
            ],
        ];
    }

    /**
     * Override toItem to include loaded relations (expenses, participants) for workspace.
     *
     * @return array<string, mixed>
     */
    public function toItem(Model $model): array
    {
        $arr = $model->toArray(); // includes relations if loaded
        // Ensure totals and counts are present
        if (! isset($arr['expenses_count'])) {
            $expAttr = $model->getAttribute('expenses_count');
            $arr['expenses_count'] = is_null($expAttr) ? 0 : (int) $expAttr;
        }
        if (! isset($arr['participants_count'])) {
            $parAttr = $model->getAttribute('participants_count');
            $arr['participants_count'] = is_null($parAttr) ? 0 : (int) $parAttr;
        }
        if (! isset($arr['total_usd_minor'])) {
            $arr['total_usd_minor'] = 0;
        }

        return $arr;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        $arr = $model->attributesToArray();
        // Ensure totals and counts are present for index
        $expensesCountAttr = $model->getAttribute('expenses_count');
        $participantsCountAttr = $model->getAttribute('participants_count');
        if (! isset($arr['expenses_count'])) {
            $arr['expenses_count'] = is_null($expensesCountAttr)
                ? (int) \Illuminate\Support\Facades\DB::table('condo_expenses')->where('condo_period_id', $model->getKey())->whereNull('deleted_at')->count()
                : (int) $expensesCountAttr;
        }
        if (! isset($arr['participants_count'])) {
            $arr['participants_count'] = is_null($participantsCountAttr)
                ? (int) \Illuminate\Support\Facades\DB::table('condo_participants')->where('condo_period_id', $model->getKey())->whereNull('deleted_at')->count()
                : (int) $participantsCountAttr;
        }
        // withSum alias set in repository; fallback compute via DB
        $arr['total_usd_minor'] = $arr['total_usd_minor'] ?? (int) \Illuminate\Support\Facades\DB::table('condo_expenses')->where('condo_period_id', $model->getKey())->whereNull('deleted_at')->sum('amount_usd_minor');

        // Attach market minimal info for index rendering if relation is loaded
        try {
            /** @var null|\App\Models\Market $market */
            $market = $model->getRelation('market');
            if ($market) {
                $arr['market'] = [
                    'id' => (int) $market->getAttribute('id'),
                    'name' => (string) ($market->getAttribute('name') ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Details for index popovers (similar UX to roles.users)
        try {
            // Expenses: list all expense type names
            $arr['expenses'] = DB::table('condo_expenses as ce')
                ->join('expense_types as et', 'et.id', '=', 'ce.expense_type_id')
                ->where('ce.condo_period_id', $model->getKey())
                ->orderBy('et.name')
                ->pluck('et.name')
                ->map(fn ($v) => (string) $v)
                ->all();

            // Participants: list all local codes (exclusions model)
            $arr['participants'] = DB::table('condo_participants as cp')
                ->join('locals as l', 'l.id', '=', 'cp.local_id')
                ->where('cp.condo_period_id', $model->getKey())
                ->whereNull('cp.deleted_at')
                ->orderBy('l.code')
                ->pluck('l.code')
                ->map(fn ($v) => (string) $v)
                ->all();
        } catch (\Throwable $e) {
            // In case of any DB issues, provide safe defaults
            $arr['expenses'] = $arr['expenses'] ?? [];
            $arr['participants'] = $arr['participants'] ?? [];
        }

        return $arr;
    }

    protected function repoModelClass(): string
    {
        return CondoPeriod::class;
    }

    /**
     * @param  array<int>  $ids
     */
    public function bulkDeleteCascadeByIds(array $ids): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            try {
                if ($this->deleteCascade($id)) {
                    $deleted++;
                }
            } catch (DomainActionException $e) {
                // skip
            }
        }

        return $deleted;
    }
}
