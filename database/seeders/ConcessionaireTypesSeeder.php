<?php

namespace Database\Seeders;

use App\Models\ConcessionaireType;
use Illuminate\Database\Seeder;

class ConcessionaireTypesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'PNAT', 'name' => 'Persona Natural'],
            ['code' => 'PJUR', 'name' => 'Persona Juridica'],
        ];

        foreach ($items as $data) {
            $model = ConcessionaireType::withTrashed()->updateOrCreate(
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
