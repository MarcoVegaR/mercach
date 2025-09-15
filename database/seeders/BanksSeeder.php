<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BanksSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => '0110', 'name' => 'Banco Mercantil', 'swift_bic' => null],
            ['code' => '0102', 'name' => 'Banco de Venezuela', 'swift_bic' => null],
            ['code' => '0108', 'name' => 'Banco Provincial', 'swift_bic' => null],
        ];

        foreach ($items as $data) {
            $model = Bank::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'swift_bic' => $data['swift_bic'],
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
