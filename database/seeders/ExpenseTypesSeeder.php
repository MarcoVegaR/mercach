<?php

namespace Database\Seeders;

use App\Models\ExpenseType;
use Illuminate\Database\Seeder;

class ExpenseTypesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ['code' => 'ELECT', 'name' => 'Electricidad', 'description' => null],
            // ['code' => 'AGUA', 'name' => 'Agua', 'description' => null],
            // ['code' => 'ASEO', 'name' => 'Aseo', 'description' => null],
            // ['code' => 'INET', 'name' => 'Internet', 'description' => null],
            // Providers requested
            ['code' => 'HIDROCAPITAL', 'name' => 'HIDROCAPITAL', 'description' => null],
            ['code' => 'CANTV', 'name' => 'CANTV', 'description' => null],
            ['code' => 'MOVISTAR', 'name' => 'MOVISTAR', 'description' => null],
            ['code' => 'CORPOELEC', 'name' => 'CORPOELEC', 'description' => null],
            ['code' => 'DESOMI', 'name' => 'DESOMI', 'description' => null],
            ['code' => 'WOW', 'name' => 'WOW', 'description' => null],
            ['code' => 'PERFILAB', 'name' => 'PERFILAB', 'description' => null],
            ['code' => 'ASCENSOR', 'name' => 'ASCENSOR', 'description' => null],
            ['code' => 'SERVITEM', 'name' => 'SERVITEM', 'description' => null],
        ];

        foreach ($items as $data) {
            $model = ExpenseType::withTrashed()->updateOrCreate(
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
