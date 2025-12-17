<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\Services\ChargeServiceInterface;
use App\Models\Charge;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GastosComunesNoviembre2024Seeder extends Seeder
{
    public function run(): void
    {
        $marketId = (int) (DB::table('markets')->where('code', 'MERCACH')->value('id') ?? 0);
        if ($marketId <= 0) {
            throw new \RuntimeException('Market MERCACH not found. Run LocalsSeeder first.');
        }

        $issuedStatusId = (int) (DB::table('charge_statuses')->where('code', 'ISSUED')->value('id') ?? 0);
        if ($issuedStatusId <= 0) {
            throw new \RuntimeException('ChargeStatus ISSUED not found.');
        }

        $period = Carbon::create(2024, 11, 1)->startOfMonth();
        $periodStr = $period->toDateString();
        $issuedOn = $periodStr;
        $dueOn = $period->copy()->day(5)->toDateString();

        $usdPerM2Minor = $this->decimalToInt('0,87', 2);

        $csvPath = database_path('seeders/data/locales_solo_noviembre.csv');
        if (! is_file($csvPath)) {
            throw new \RuntimeException('CSV not found at: '.$csvPath);
        }

        $codes = $this->readCodesFromCsv($csvPath);

        $paidCsvPath = database_path('seeders/data/Deuda Condominio Sistema Mercado/_scan/locales_ultimo_mes_pagado_completo_definitivo.csv');
        $paidAtLeastThisMonth = $this->readPaidCodesAtLeastMonth($paidCsvPath, $periodStr);
        if ($paidAtLeastThisMonth !== []) {
            $codes = array_values(array_filter($codes, static function (string $code) use ($paidAtLeastThisMonth): bool {
                return ! isset($paidAtLeastThisMonth[strtoupper($code)]);
            }));
        }

        $locals = DB::table('locals')
            ->where('market_id', $marketId)
            ->whereNull('deleted_at')
            ->get(['id', 'code', 'area_m2']);

        $localByUpperCode = [];
        foreach ($locals as $row) {
            $localByUpperCode[strtoupper((string) $row->code)] = $row;
        }

        $missingCodes = [];
        $invalidCodes = [];
        $rowsToCreate = [];
        $desiredLocalIds = [];

        foreach ($codes as $code) {
            $upper = strtoupper($code);
            $local = $localByUpperCode[$upper] ?? null;
            if (! $local) {
                $missingCodes[] = $code;

                continue;
            }

            $localId = (int) $local->id;
            $desiredLocalIds[$localId] = true;

            $areaCenti = $this->decimalToInt((string) $local->area_m2, 2);
            if ($areaCenti <= 0) {
                $invalidCodes[] = $code;

                continue;
            }

            $amountMinor = intdiv($usdPerM2Minor * $areaCenti, 100);
            if ($amountMinor <= 0) {
                $invalidCodes[] = $code;

                continue;
            }

            $idempotencyKey = hash('sha256', implode('|', [
                'CONDO_IMPORT',
                (string) $marketId,
                'CONDO_USD',
                $periodStr,
                (string) $localId,
            ]));

            $rowsToCreate[] = [
                'market_id' => $marketId,
                'local_id' => $localId,
                'contract_id' => null,
                'condo_period_id' => null,

                'debtor_type' => 'LOCAL',
                'debtor_id' => $localId,
                'origin_debtor_type' => 'LOCAL',
                'origin_debtor_id' => $localId,

                'kind' => 'CONDO_USD',
                'currency' => 'USD',
                'amount_minor' => $amountMinor,

                'period' => $periodStr,
                'issued_on' => $issuedOn,
                'due_on' => $dueOn,
                'settled_on' => null,

                'charge_status_id' => $issuedStatusId,
                'source' => 'CONDO_IMPORT',
                'idempotency_key' => $idempotencyKey,
                'note' => null,
            ];
        }

        if ($missingCodes !== []) {
            throw new \RuntimeException('Missing locals in CSV: '.implode(', ', $missingCodes));
        }

        if ($invalidCodes !== []) {
            throw new \RuntimeException('Invalid locals (area/amount <=0): '.implode(', ', $invalidCodes));
        }

        DB::transaction(function () use ($rowsToCreate): void {
            foreach ($rowsToCreate as $row) {
                $existing = Charge::query()
                    ->where('idempotency_key', $row['idempotency_key'])
                    ->whereNull('deleted_at')
                    ->first(['id']);

                if ($existing) {
                    continue;
                }

                Charge::query()->create($row);
            }
        });

        $this->syncRemovedLocals($marketId, $periodStr, array_map('intval', array_keys($desiredLocalIds)));
    }

    private function decimalToInt(string $value, int $scale): int
    {
        $value = trim($value);
        $value = str_replace(',', '.', $value);
        if ($value === '') {
            return 0;
        }

        $negative = false;
        if (str_starts_with($value, '-')) {
            $negative = true;
            $value = substr($value, 1);
        }

        $parts = explode('.', $value, 2);
        $whole = preg_replace('/\D+/', '', $parts[0]) ?? '';
        $frac = preg_replace('/\D+/', '', $parts[1] ?? '') ?? '';

        if ($whole === '') {
            $whole = '0';
        }

        $frac = substr(str_pad($frac, $scale, '0'), 0, $scale);

        $mul = 1;
        for ($i = 0; $i < $scale; $i++) {
            $mul *= 10;
        }

        $int = ((int) $whole) * $mul + (int) $frac;

        return $negative ? -$int : $int;
    }

    /**
     * @param  array<int, int>  $desiredLocalIds
     */
    private function syncRemovedLocals(int $marketId, string $periodStr, array $desiredLocalIds): void
    {
        $desired = [];
        foreach ($desiredLocalIds as $id) {
            $desired[(int) $id] = true;
        }

        $rows = DB::table('charges')
            ->where('market_id', $marketId)
            ->where('kind', 'CONDO_USD')
            ->where('currency', 'USD')
            ->where('period', $periodStr)
            ->where('source', 'CONDO_IMPORT')
            ->whereNull('deleted_at')
            ->get(['id', 'local_id']);

        if ($rows->isEmpty()) {
            return;
        }

        /** @var ChargeServiceInterface $charges */
        $charges = app(ChargeServiceInterface::class);

        foreach ($rows as $row) {
            $localId = (int) $row->local_id;
            if (isset($desired[$localId])) {
                continue;
            }

            $charges->cancel((int) $row->id, 'Removed from locales_solo_noviembre.csv');
        }
    }

    /**
     * @return array<int, string>
     */
    private function readCodesFromCsv(string $csvPath): array
    {
        $lines = file($csvPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Unable to read CSV at: '.$csvPath);
        }

        $codes = [];
        foreach ($lines as $i => $line) {
            $line = $this->stripBom((string) $line);
            $cols = str_getcsv($line);
            $code = trim((string) ($cols[0] ?? ''));
            $code = trim($code, " \t\n\r\0\x0B\"");
            if ($code === '') {
                continue;
            }

            if ($i === 0 && strtolower($code) === 'local_id') {
                continue;
            }

            if ($code === '.') {
                continue;
            }

            $codes[] = $code;
        }

        $unique = [];
        foreach ($codes as $code) {
            $key = strtoupper($code);
            $unique[$key] = $code;
        }

        return array_values($unique);
    }

    /**
     * @return array<string, true>
     */
    private function readPaidCodesAtLeastMonth(string $csvPath, string $monthStartDate): array
    {
        if (! is_file($csvPath)) {
            throw new \RuntimeException('CSV not found at: '.$csvPath);
        }

        $thresholdYm = substr($monthStartDate, 0, 7);
        $thresholdParts = explode('-', $thresholdYm);
        $threshold = ((int) $thresholdParts[0]) * 12 + (int) ($thresholdParts[1] ?? '0');

        $fh = fopen($csvPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('Unable to read CSV at: '.$csvPath);
        }

        $header = fgetcsv($fh);
        if ($header === false) {
            fclose($fh);

            return [];
        }

        $map = [];
        foreach ($header as $i => $name) {
            $name = strtolower(trim((string) $name));
            if ($name !== '') {
                $map[$name] = (int) $i;
            }
        }

        $localIdx = $map['local'] ?? null;
        $monthIdx = $map['ultimo_mes_pagado'] ?? null;
        if ($localIdx === null || $monthIdx === null) {
            fclose($fh);
            throw new \RuntimeException('Invalid CSV header at: '.$csvPath);
        }

        $paid = [];
        while (($row = fgetcsv($fh)) !== false) {
            $local = trim((string) ($row[$localIdx] ?? ''));
            if ($local === '') {
                continue;
            }

            $ultimoMes = trim((string) ($row[$monthIdx] ?? ''));
            if ($ultimoMes !== '') {
                $parts = explode('-', $ultimoMes);
                $value = ((int) $parts[0]) * 12 + (int) ($parts[1] ?? '0');
                if ($value >= $threshold) {
                    $paid[strtoupper($local)] = true;
                }
            }
        }

        fclose($fh);

        return $paid;
    }

    private function stripBom(string $value): string
    {
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return $value;
    }
}
