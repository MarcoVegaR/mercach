<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use Illuminate\Database\Seeder;

class PaymentStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'REG', 'name' => 'Registrado'],
            ['code' => 'CONF', 'name' => 'Confirmado'],
            ['code' => 'CONC', 'name' => 'Conciliado'],
        ];

        foreach ($items as $data) {
            $model = PaymentStatus::withTrashed()->updateOrCreate(
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
