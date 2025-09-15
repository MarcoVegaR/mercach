<?php

namespace Database\Seeders;

use App\Models\ContractModality;
use Illuminate\Database\Seeder;

class ContractModalitiesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'TFIJA', 'name' => 'Tasa Fija'],
            ['code' => 'M2', 'name' => 'Metro Cuadrado'],
        ];

        foreach ($items as $data) {
            $model = ContractModality::withTrashed()->updateOrCreate(
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
