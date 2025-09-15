<?php

namespace Database\Seeders;

use App\Models\TradeCategory;
use Illuminate\Database\Seeder;

class TradeCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'PAPAS', 'name' => 'Papas', 'description' => null],
            ['code' => 'HUEVOS', 'name' => 'Huevos', 'description' => null],
        ];

        foreach ($items as $data) {
            $model = TradeCategory::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
