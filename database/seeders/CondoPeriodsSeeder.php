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
                'status' => 'DRAFT',
                'is_active' => true,
            ]
        );

        if ($period->trashed()) {
            $period->restore();
        }

        $this->command->info("✓ Created CondoPeriod #{$period->id} for {$market->name} ({$periodDate})");

        // Create sample expenses (3 expenses)
        $expensesData = [
            [
                'expense_type_id' => $expenseTypes->first()->id,
                'amount_usd' => 1250.50,
                'invoice_number' => 'INV-2025-001',
                'expense_date' => now()->subDays(5)->format('Y-m-d'),
                'note' => 'Electricidad del mes',
            ],
            [
                'expense_type_id' => $expenseTypes->skip(1)->first()->id ?? $expenseTypes->first()->id,
                'amount_usd' => 850.00,
                'invoice_number' => 'INV-2025-002',
                'expense_date' => now()->subDays(3)->format('Y-m-d'),
                'note' => 'Agua del mes',
            ],
            [
                'expense_type_id' => $expenseTypes->skip(2)->first()->id ?? $expenseTypes->first()->id,
                'amount_usd' => 450.75,
                'invoice_number' => null,
                'expense_date' => now()->subDays(1)->format('Y-m-d'),
                'note' => 'Aseo y limpieza',
            ],
        ];

        foreach ($expensesData as $data) {
            CondoExpense::create([
                'condo_period_id' => $period->id,
                'expense_type_id' => $data['expense_type_id'],
                'amount_usd_minor' => (int) ($data['amount_usd'] * 100),
                'invoice_number' => $data['invoice_number'],
                'expense_date' => $data['expense_date'],
                'note' => $data['note'],
                'is_active' => true,
            ]);
        }

        $this->command->info('✓ Created 3 sample expenses');

        // Exclusions-only model: exclude ~10% of locals (or min 2)
        $excludeCount = max(2, (int) ceil($locals->count() * 0.10));
        $excludedLocals = $locals->random(min($excludeCount, $locals->count()));

        foreach ($excludedLocals as $local) {
            CondoParticipant::create([
                'condo_period_id' => $period->id,
                'local_id' => $local->id,
                'area_m2_snapshot' => $local->area_m2,
                'included' => false, // Exclusion
                'is_active' => true,
            ]);
        }

        $this->command->info("✓ Excluded {$excludedLocals->count()} locals (out of {$locals->count()} total)");
        $this->command->info('✓ Included (by default): '.($locals->count() - $excludedLocals->count()).' locals');
        $this->command->info('✓ CondoPeriod seeding complete!');
    }
}
