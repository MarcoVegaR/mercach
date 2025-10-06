<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DebtTransferReasonsSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\DebtTransferReason::query()->updateOrCreate(
            ['code' => 'ALTA_CONTRATO'],
            ['name' => 'Alta de contrato', 'is_active' => true]
        );
    }
}
