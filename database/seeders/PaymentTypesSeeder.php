<?php

namespace Database\Seeders;

use App\Models\PaymentType;
use Illuminate\Database\Seeder;

class PaymentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'DEB', 'name' => 'Debito'],
            ['code' => 'PMOV', 'name' => 'Pago movil'],
        ];

        foreach ($items as $data) {
            $model = PaymentType::withTrashed()->updateOrCreate(
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
