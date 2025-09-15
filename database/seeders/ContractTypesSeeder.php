<?php

namespace Database\Seeders;

use App\Models\ContractType;
use Illuminate\Database\Seeder;

class ContractTypesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'CONTR', 'name' => 'Contrato'],
            ['code' => 'CESION', 'name' => 'Cesion'],
            ['code' => 'CONV', 'name' => 'Convenio'],
        ];

        foreach ($items as $data) {
            $model = ContractType::withTrashed()->updateOrCreate(
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
