<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketTariffsSeeder extends Seeder
{
    public function run(): void
    {
        // Find MERCACH market
        $marketId = (int) DB::table('markets')->where('code', 'MERCACH')->value('id');
        if ($marketId <= 0) {
            return;
        }

        // Set a current tariff: 0.10 EUR per m² (minor units = 10)
        $validFrom = date('Y-m-01');
        $priceMinor = 10; // 0.10 EUR in minor units

        // Mark previous currents as not current
        DB::table('market_tariffs')->where('market_id', $marketId)->update(['is_current' => false]);

        DB::table('market_tariffs')->updateOrInsert(
            ['market_id' => $marketId, 'valid_from' => $validFrom],
            [
                'price_per_m2_eur_minor' => $priceMinor,
                'is_current' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
