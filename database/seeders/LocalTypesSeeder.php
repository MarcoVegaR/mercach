<?php

namespace Database\Seeders;

use App\Models\LocalType;
use Illuminate\Database\Seeder;

class LocalTypesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'BATEA',   'name' => 'Batea',   'description' => null],
            ['code' => 'KIOSKO',  'name' => 'Kiosko',  'description' => null],
            ['code' => 'LOCAL',   'name' => 'Local',   'description' => null],
            ['code' => 'OFICINA', 'name' => 'Oficina', 'description' => null],
        ];

        foreach ($items as $data) {
            // Handle soft-deleted rows gracefully
            $model = LocalType::withTrashed()->updateOrCreate(
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
