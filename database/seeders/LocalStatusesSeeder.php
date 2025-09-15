<?php

namespace Database\Seeders;

use App\Models\LocalStatus;
use Illuminate\Database\Seeder;

class LocalStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'OCUP', 'name' => 'Ocupado', 'description' => null],
            ['code' => 'DISP', 'name' => 'Disponible', 'description' => null],
        ];

        foreach ($items as $data) {
            $model = LocalStatus::withTrashed()->updateOrCreate(
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
