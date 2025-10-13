<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BanksSeeder extends Seeder
{
    public function run(): void
    {
        // Banks as per manual (4-digit local code, 3-digit bank_code for gateway)
        $items = [
            ['code' => '0102', 'bank_code' => '102', 'name' => 'VENEZUELA', 'swift_bic' => null],
            ['code' => '0104', 'bank_code' => '104', 'name' => 'VZLANO DE CREDITO', 'swift_bic' => null],
            ['code' => '0105', 'bank_code' => '105', 'name' => 'MERCANTIL', 'swift_bic' => null],
            ['code' => '0108', 'bank_code' => '108', 'name' => 'PROVINCIAL', 'swift_bic' => null],
            ['code' => '0114', 'bank_code' => '114', 'name' => 'BANCARIBE', 'swift_bic' => null],
            ['code' => '0115', 'bank_code' => '115', 'name' => 'EXTERIOR', 'swift_bic' => null],
            ['code' => '0128', 'bank_code' => '128', 'name' => 'CARONI', 'swift_bic' => null],
            ['code' => '0134', 'bank_code' => '134', 'name' => 'BANESCO', 'swift_bic' => null],
            ['code' => '0137', 'bank_code' => '137', 'name' => 'SOFITASA', 'swift_bic' => null],
            ['code' => '0138', 'bank_code' => '138', 'name' => 'PLAZA', 'swift_bic' => null],
            ['code' => '0151', 'bank_code' => '151', 'name' => 'BFC BANCO FONDO COMUN', 'swift_bic' => null],
            ['code' => '0156', 'bank_code' => '156', 'name' => '100% BANCO, BANCO UNIVERSAL', 'swift_bic' => null],
            ['code' => '0157', 'bank_code' => '157', 'name' => 'DEL SUR', 'swift_bic' => null],
            ['code' => '0163', 'bank_code' => '163', 'name' => 'BANCO DEL TESORO', 'swift_bic' => null],
            ['code' => '0166', 'bank_code' => '166', 'name' => 'AGRICOLA DE VENEZUELA', 'swift_bic' => null],
            ['code' => '0168', 'bank_code' => '168', 'name' => 'BANCRECER', 'swift_bic' => null],
            ['code' => '0169', 'bank_code' => '169', 'name' => 'R4', 'swift_bic' => null],
            ['code' => '0171', 'bank_code' => '171', 'name' => 'ACTIVO BANCO UNIVERSAL', 'swift_bic' => null],
            ['code' => '0172', 'bank_code' => '172', 'name' => 'BANCAMIGA', 'swift_bic' => null],
            ['code' => '0174', 'bank_code' => '174', 'name' => 'BANPLUS BANCO COMERCIAL', 'swift_bic' => null],
            ['code' => '0175', 'bank_code' => '175', 'name' => 'BANCO DIGITAL DE LOS TRABAJADORES', 'swift_bic' => null],
            ['code' => '0177', 'bank_code' => '177', 'name' => 'BANCO FUERZA ARMADA NACIONAL', 'swift_bic' => null],
            ['code' => '0191', 'bank_code' => '191', 'name' => 'BANCO NACIONAL CREDITO', 'swift_bic' => null],
        ];

        foreach ($items as $data) {
            $model = Bank::withTrashed()->updateOrCreate(
                ['code' => $data['code']],
                [
                    'name' => $data['name'],
                    'swift_bic' => $data['swift_bic'],
                    'is_active' => true,
                    'bank_code' => $data['bank_code'],
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
