<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LocalLocation;
use Illuminate\Database\Seeder;

class LocalLocationSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'PB', 'name' => 'Planta baja', 'is_active' => true],

        ];

        foreach ($items as $data) {
            $model = LocalLocation::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'is_active' => $data['is_active'],
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
