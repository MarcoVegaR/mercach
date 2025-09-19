<?php

namespace Database\Seeders;

use App\Models\Market;
use Illuminate\Database\Seeder;

class MarketsSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'code' => 'MERCACH',
                'name' => 'Sede Principal',
                'address' => 'Calle Urdaneta, Chacao, Caracas, Venezuela',
            ],
        ];

        foreach ($items as $data) {
            $model = Market::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'address' => $data['address'],
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
