<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CondoExpenseRepositoryInterface;
use App\Contracts\Services\CondoExpenseServiceInterface;
use App\Exceptions\DomainActionException;
use App\Models\CondoExpense;
use App\Models\CondoPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CondoExpenseService extends BaseService implements CondoExpenseServiceInterface
{
    public function __construct(
        CondoExpenseRepositoryInterface $repo,
        \Psr\Container\ContainerInterface $container,
    ) {
        parent::__construct($repo, $container);
    }

    /**
     * Create a single expense for a period.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createOne(CondoPeriod $period, array $payload): CondoExpense
    {
        return $this->transaction(function () use ($period, $payload) {
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }

            $dir = sprintf('condo/expenses/%d/%s', (int) $period->getAttribute('market_id'), Carbon::parse($period->getAttribute('period'))->format('Y-m'));

            $data = [
                'condo_period_id' => $period->getKey(),
                'expense_type_id' => (int) $payload['expense_type_id'],
                'amount_usd_minor' => (int) $payload['amount_usd_minor'],
                'invoice_number' => $payload['invoice_number'] ?? null,
                'expense_date' => $payload['expense_date'] ?? null,
                'note' => $payload['note'] ?? null,
                'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
            ];

            $file = $payload['attachment'] ?? null;
            if ($file instanceof UploadedFile) {
                $data['attachment_path'] = $this->storeAttachment($file, $dir);
            }

            /** @var CondoExpense $created */
            $created = CondoExpense::query()->create($data);

            return $created;
        });
    }

    /**
     * Update a single expense.
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateOne(CondoExpense $expense, array $payload): CondoExpense
    {
        return $this->transaction(function () use ($expense, $payload) {
            /** @var CondoPeriod $period */
            $period = $expense->period;
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }

            $dir = sprintf('condo/expenses/%d/%s', (int) $period->getAttribute('market_id'), Carbon::parse($period->getAttribute('period'))->format('Y-m'));
            $disk = config('filesystems.uploads_disk', 'public');

            $data = [
                'expense_type_id' => (int) $payload['expense_type_id'],
                'amount_usd_minor' => (int) $payload['amount_usd_minor'],
                'invoice_number' => $payload['invoice_number'] ?? null,
                'expense_date' => $payload['expense_date'] ?? null,
                'note' => $payload['note'] ?? null,
                'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : $expense->getAttribute('is_active'),
            ];

            $file = $payload['attachment'] ?? null;
            if ($file instanceof UploadedFile) {
                $prev = (string) ($expense->getAttribute('attachment_path') ?? '');
                if ($prev !== '' && Storage::disk($disk)->exists($prev)) {
                    Storage::disk($disk)->delete($prev);
                }
                $data['attachment_path'] = $this->storeAttachment($file, $dir);
            }

            $expense->fill($data);
            $expense->save();

            return $expense->fresh();
        });
    }

    public function deleteOne(CondoExpense $expense): void
    {
        $this->transaction(function () use ($expense) {
            /** @var CondoPeriod $period */
            $period = $expense->period;
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }
            $expense->delete();
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function bulkStore(int $periodId, array $items): int
    {
        return $this->transaction(function () use ($periodId, $items) {
            /** @var CondoPeriod $period */
            $period = CondoPeriod::query()->findOrFail($periodId);
            if ($period->isFinal() || $period->hasCharges()) {
                throw new DomainActionException('Período bloqueado para edición (FINAL o con cargos).');
            }

            $processed = 0;
            $dir = sprintf('condo/expenses/%d/%s', (int) $period->getAttribute('market_id'), Carbon::parse($period->getAttribute('period'))->format('Y-m'));
            $disk = config('filesystems.uploads_disk', 'public');

            foreach ($items as $payload) {
                // Normalize minimal fields
                $data = [
                    'condo_period_id' => $period->getKey(),
                    'expense_type_id' => (int) $payload['expense_type_id'],
                    'amount_usd_minor' => (int) $payload['amount_usd_minor'],
                    'invoice_number' => $payload['invoice_number'] ?? null,
                    'expense_date' => $payload['expense_date'] ?? null,
                    'note' => $payload['note'] ?? null,
                    'is_active' => array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true,
                ];

                $file = $payload['attachment'] ?? null;

                // Update existing by id or create new
                if (! empty($payload['id'])) {
                    /** @var CondoExpense $model */
                    $model = CondoExpense::query()->where('condo_period_id', $period->getKey())->findOrFail((int) $payload['id']);

                    if ($file instanceof UploadedFile) {
                        // Delete previous if any
                        $prev = (string) ($model->getAttribute('attachment_path') ?? '');
                        if ($prev !== '' && Storage::disk($disk)->exists($prev)) {
                            Storage::disk($disk)->delete($prev);
                        }
                        $data['attachment_path'] = $this->storeAttachment($file, $dir);
                    }

                    $model->fill($data);
                    $model->save();
                    $processed++;
                } else {
                    if ($file instanceof UploadedFile) {
                        $data['attachment_path'] = $this->storeAttachment($file, $dir);
                    }

                    CondoExpense::query()->create($data);
                    $processed++;
                }
            }

            return $processed;
        });
    }

    private function storeAttachment(UploadedFile $file, string $dir): string
    {
        $ext = $file->getClientOriginalExtension() ?: $file->extension();
        $name = Str::uuid()->toString().'.'.strtolower($ext ?: 'bin');
        $disk = config('filesystems.uploads_disk', 'public');
        $path = Storage::disk($disk)->putFileAs($dir, $file, $name);

        return $path; // relative to the public disk
    }
}
