<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FxRate;
use Carbon\CarbonImmutable as Carbon;
use Illuminate\Database\Seeder;

class FxRatesOctober2025Seeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            '2025-10-01' => ['USD' => 179.43, 'EUR' => 210.51],
            '2025-10-02' => ['USD' => 181.30, 'EUR' => 212.94],
            '2025-10-03' => ['USD' => 183.14, 'EUR' => 214.52],
            '2025-10-06' => ['USD' => 185.40, 'EUR' => 217.65],
            '2025-10-07' => ['USD' => 187.29, 'EUR' => 219.40],
            '2025-10-08' => ['USD' => 189.26, 'EUR' => 220.94],
            '2025-10-09' => ['USD' => 191.37, 'EUR' => 222.28],
            '2025-10-10' => ['USD' => 193.30, 'EUR' => 223.64],
            '2025-10-13' => ['USD' => 195.25, 'EUR' => 226.04],
            '2025-10-14' => ['USD' => 197.25, 'EUR' => 228.09],
            '2025-10-15' => ['USD' => 199.11, 'EUR' => 230.45],
            '2025-10-16' => ['USD' => 201.46, 'EUR' => 234.20],
            '2025-10-17' => ['USD' => 203.74, 'EUR' => 237.92],
            '2025-10-20' => ['USD' => 205.67, 'EUR' => 239.92],
            '2025-10-21' => ['USD' => 207.89, 'EUR' => 242.21],
            '2025-10-22' => ['USD' => 210.28, 'EUR' => 244.02],
            '2025-10-23' => ['USD' => 212.48, 'EUR' => 246.67],
            '2025-10-24' => ['USD' => 214.41, 'EUR' => 249.02],
            '2025-10-27' => ['USD' => 216.37, 'EUR' => 251.65],
            '2025-10-28' => ['USD' => 218.17, 'EUR' => 253.90],
            '2025-10-29' => ['USD' => 219.87, 'EUR' => 256.36],
            '2025-10-30' => ['USD' => 221.74, 'EUR' => 258.34],
            '2025-10-31' => ['USD' => 223.64, 'EUR' => 258.80],

            '2025-11-03' => ['USD' => 223.96, 'EUR' => 258.14],
            '2025-11-04' => ['USD' => 224.37, 'EUR' => 258.41],
            '2025-11-05' => ['USD' => 226.13, 'EUR' => 259.63],
            '2025-11-06' => ['USD' => 227.55, 'EUR' => 261.16],
            '2025-11-07' => ['USD' => 228.47, 'EUR' => 263.29],

            '2025-11-10' => ['USD' => 231.05, 'EUR' => 267.64],

            '2025-11-11' => ['USD' => 231.09, 'EUR' => 267.27],
            '2025-11-12' => ['USD' => 233.04, 'EUR' => 270.25],
            '2025-11-13' => ['USD' => 233.55, 'EUR' => 270.32],
            '2025-11-14' => ['USD' => 234.87, 'EUR' => 273.08],

            '2025-11-17' => ['USD' => 236.46, 'EUR' => 274.84],
            '2025-11-18' => ['USD' => 236.83, 'EUR' => 274.35],
            '2025-11-19' => ['USD' => 237.75, 'EUR' => 275.81],
            '2025-11-20' => ['USD' => 240.32, 'EUR' => 277.61],

            '2025-11-21' => ['USD' => 241.58, 'EUR' => null], // No pude extraer EUR por redirección en la fuente

            // '2025-11-24' feriado bancario (sin publicación/operación)

            '2025-11-25' => ['USD' => 243.11, 'EUR' => 280.05],
            '2025-11-26' => ['USD' => 243.57, 'EUR' => 281.76],
            '2025-11-27' => ['USD' => 244.65, 'EUR' => 283.50],
            '2025-11-28' => ['USD' => 245.67, 'EUR' => 284.88],

            // Publicada para el próximo día hábil:
            '2025-12-01' => ['USD' => 247.30, 'EUR' => 286.40],
            '2025-12-02' => ['USD' => 247.41, 'EUR' => 287.83],
            '2025-12-03' => ['USD' => 249.20, 'EUR' => 289.16],
            '2025-12-04' => ['USD' => 251.89, 'EUR' => 293.88],
            '2025-12-05' => ['USD' => 254.87, 'EUR' => 297.38],
            '2025-12-09' => ['USD' => 257.93, 'EUR' => 300.51],
            '2025-12-10' => ['USD' => 262.10, 'EUR' => 304.85],
            '2025-12-11' => ['USD' => 265.07, 'EUR' => 308.81],
        ];

        $dates = array_keys($rows);
        sort($dates);

        foreach ($dates as $idx => $dateStr) {
            $tz = (string) config('app.timezone', 'America/Caracas');
            $rateDate = Carbon::parse($dateStr, $tz)->startOfDay();
            $nextDate = $dates[$idx + 1] ?? null;
            $operFrom = $rateDate->toDateTimeString();
            $operTo = $nextDate
                ? Carbon::parse($nextDate, $tz)->startOfDay()->subSecond()->toDateTimeString()
                : null;
            $publishedAt = $rateDate->toDateTimeString();

            $source = 'BCV';
            foreach ($rows[$dateStr] as $ccy => $rateToVes) {
                FxRate::query()->updateOrCreate(
                    [
                        'currency_code' => $ccy,
                        'rate_date' => $rateDate->toDateString(),
                    ],
                    [
                        'value_date' => $rateDate->toDateString(),
                        'published_at' => $publishedAt,
                        'rate_to_ves' => round((float) $rateToVes, 2),
                        'operational_from' => $operFrom,
                        'operational_to' => $operTo,
                        'source' => $source,
                        'is_official' => true,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
