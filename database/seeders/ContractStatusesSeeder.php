<?php

namespace Database\Seeders;

use App\Models\ContractStatus;
use Illuminate\Database\Seeder;

class ContractStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'BORR', 'name' => 'Borrador'],
            ['code' => 'VIG', 'name' => 'Vigente'],
            ['code' => 'EXT', 'name' => 'Extendido'],
            ['code' => 'TERM', 'name' => 'Terminado'],
            ['code' => 'VENC', 'name' => 'Vencido'],
        ];

        foreach ($items as $data) {
            $model = ContractStatus::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
