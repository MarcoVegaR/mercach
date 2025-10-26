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
