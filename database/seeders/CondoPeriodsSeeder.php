<?php

namespace Database\Seeders;

use App\Models\CondoExpense;
use App\Models\CondoParticipant;
use App\Models\CondoPeriod;
use App\Models\ExpenseType;
use App\Models\Local;
use App\Models\Market;
use Illuminate\Database\Seeder;

class CondoPeriodsSeeder extends Seeder
{
    /**
     * Seed 1 complete CondoPeriod with expenses and exclusions (participants).
     * Follows exclusions-only model: only excluded locals have rows in condo_participants.
     */
    public function run(): void
    {
        // Get first active market
        $market = Market::query()->where('is_active', true)->first();
        if (! $market) {
            $this->command->warn('No active market found. Skipping CondoPeriodsSeeder.');

            return;
        }

        // Get all active locals for the market
        $locals = Local::query()
            ->where('market_id', $market->id)
            ->where('is_active', true)
            ->get();

        if ($locals->isEmpty()) {
            $this->command->warn("No active locals found for market {$market->name}. Skipping CondoPeriodsSeeder.");

            return;
        }

        // Get expense types
        $expenseTypes = ExpenseType::query()->where('is_active', true)->get();
        if ($expenseTypes->isEmpty()) {
            $this->command->warn('No active expense types found. Skipping CondoPeriodsSeeder.');

            return;
        }

        // Create a period for current month
        $periodDate = now()->startOfMonth()->format('Y-m-d');
        $period = CondoPeriod::withTrashed()->updateOrCreate(
            [
                'market_id' => $market->id,
                'period' => $periodDate,
            ],
            [
                'status' => 'FINAL',
                'finalized_at' => now(),
                'is_active' => true,
            ]
        );

        if ($period->trashed()) {
            $period->restore();
        }

        $this->command->info("✓ Created CondoPeriod #{$period->id} for {$market->name} ({$periodDate})");

        // Requested expenses in BS -> convert to USD using rate 126.28 and seed
        $rate = 126.28;
        $parseBs = function (string $s): float {
            $clean = str_replace(['.', ' '], ['', ''], trim($s));
            $clean = str_replace(',', '.', $clean);

            return (float) $clean; // BS major
        };

        // Only providers with provided amounts
        $providerAmountsBs = [
            'HIDROCAPITAL' => '59.561,85',
            'CANTV' => '13.967,31',
            'MOVISTAR' => '9.974,32',
            'CORPOELEC' => '158.464,51',
            'DESOMI' => '24.816,46',
            'WOW' => '74.903,50',
        ];

        foreach ($providerAmountsBs as $code => $bsStr) {
            $bs = $parseBs($bsStr);
            $usd = $bs / $rate;
            $minor = (int) round($usd * 100);
            $typeId = ExpenseType::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id')
                ?? ($expenseTypes->first()->id);

            CondoExpense::create([
                'condo_period_id' => $period->id,
                'expense_type_id' => $typeId,
                'amount_usd_minor' => $minor,
                'invoice_number' => null,
                'expense_date' => now()->toDateString(),
                'note' => $code.' convertido desde BS a USD a tasa '.$rate,
                'is_active' => true,
            ]);
        }

        $this->command->info('✓ Creados gastos solicitados (BS/126.28) en USD');

        // No exclusions: ensure all locals participate (exclusions-only model => delete any exclusions)
        CondoParticipant::query()->where('condo_period_id', $period->id)->delete();
        $this->command->info('✓ Sin exclusiones: todos los locales participan');

        $this->command->info('✓ CondoPeriod seeding complete!');
    }
}
