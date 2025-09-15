<?php

namespace Database\Seeders;

use App\Models\DocumentType;
use Illuminate\Database\Seeder;

class DocumentTypesSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'V', 'name' => 'Venezolano', 'mask' => null],
            ['code' => 'E', 'name' => 'Extranjero', 'mask' => null],
            ['code' => 'J', 'name' => 'Juridico', 'mask' => null],
            ['code' => 'P', 'name' => 'Pasaporte', 'mask' => null],
        ];

        foreach ($items as $data) {
            $model = DocumentType::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'mask' => $data['mask'],
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
