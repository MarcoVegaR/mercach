<?php

namespace Database\Seeders;

use App\Models\PhoneAreaCode;
use Illuminate\Database\Seeder;

class PhoneAreaCodesSeeder extends Seeder
{
    public function run(): void
    {
        // Nota: El catálogo solo tiene columna `code` e `is_active`.
        // Los nombres de operadora se documentan aquí como referencia.
        $items = [
            ['code' => '0412'], // Digitel
            ['code' => '0422'], // Digitel
            ['code' => '0416'], // Movilnet
            ['code' => '0426'], // Movilnet
            ['code' => '0414'], // Movistar
            ['code' => '0424'], // Movistar
        ];

        foreach ($items as $data) {
            $model = PhoneAreaCode::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
