<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use Illuminate\Database\Seeder;

class LocalsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure required catalogs exist
        $market = Market::withTrashed()->updateOrCreate(
            ['code' => 'MERCACH'],
            [
                'name' => 'Sede Principal',
                'address' => 'Calle Urdaneta, Chacao, Caracas, Venezuela',
                'is_active' => true,
            ]
        );
        if ($market->trashed()) {
            $market->restore();
        }

        $localType = LocalType::withTrashed()->updateOrCreate(
            ['code' => 'BATEA'],
            [
                'name' => 'Batea',
                'description' => null,
                'is_active' => true,
            ]
        );
        if ($localType->trashed()) {
            $localType->restore();
        }

        $statusDisp = LocalStatus::withTrashed()->updateOrCreate(
            ['code' => 'DISP'],
            [
                'name' => 'Disponible',
                'description' => null,
                'is_active' => true,
            ]
        );
        if ($statusDisp->trashed()) {
            $statusDisp->restore();
        }

        $locationPB = LocalLocation::withTrashed()->updateOrCreate(
            ['code' => 'PB'],
            [
                'name' => 'Planta baja',
                'is_active' => true,
            ]
        );
        if ($locationPB->trashed()) {
            $locationPB->restore();
        }

        $rows = [
            ['code' => 'A-01', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '6.97'],
            ['code' => 'A-02', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '6.97'],
            ['code' => 'A-03', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '6.97'],
            ['code' => 'A-04', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '6.97'],
            ['code' => 'A-05', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-06', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-07', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-08', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-09', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-10', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '1.97'],
            ['code' => 'A-11', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-12', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-13', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-14', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-15', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-16', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-17', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-18', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-19', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-20', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-21', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-22', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-23', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-24', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-25', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-26', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-27', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-29', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-30', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-31', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-32', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-33', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-34', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-35', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-36', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-37', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'A-38', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-01', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-02', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-03', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-04', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-05', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-06', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-07', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-08', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-09', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-10', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-11', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-12', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-13', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-14', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-15', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-16', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-17', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-18', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-19', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-20', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-21', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-22', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-23', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-24', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-25', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-26', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-27', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-28', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-29', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-30', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-31', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-32', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-33', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-34', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-35', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-36', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-37', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-38', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-39', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-40', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-41', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-42', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-43', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-44', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-45', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
            ['code' => 'B-46', 'market' => $market, 'status' => $statusDisp, 'type' => $localType, 'location' => $locationPB, 'area' => '2.27'],
        ];

        foreach ($rows as $r) {
            $model = Local::withTrashed()->updateOrCreate(
                ['code' => $r['code']],
                [
                    'name' => $r['code'],
                    'market_id' => $r['market']->getKey(),
                    'local_type_id' => $r['type']->getKey(),
                    'local_status_id' => $r['status']->getKey(),
                    'local_location_id' => $r['location']->getKey(),
                    'area_m2' => $r['area'],
                    'is_active' => true,
                ]
            );

            if ($model->trashed()) {
                $model->restore();
            }
        }
    }
}
