<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ChargeStatusesSeeder extends Seeder
{
    public function run(): void
    {
        // Seed ISSUED and CANCELED
        \App\Models\ChargeStatus::query()->updateOrCreate(
            ['code' => 'ISSUED'],
            ['name' => 'Emitido', 'is_active' => true]
        );
        \App\Models\ChargeStatus::query()->updateOrCreate(
            ['code' => 'PARTIAL'],
            ['name' => 'Parcialmente pagado', 'is_active' => true]
        );
        \App\Models\ChargeStatus::query()->updateOrCreate(
            ['code' => 'SETTLED'],
            ['name' => 'Cancelado', 'is_active' => true]
        );
        \App\Models\ChargeStatus::query()->updateOrCreate(
            ['code' => 'CANCELED'],
            ['name' => 'Anulado', 'is_active' => true]
        );
    }
}
