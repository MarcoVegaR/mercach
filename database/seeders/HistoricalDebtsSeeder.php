<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\ChargeStatus;
use App\Models\Concessionaire;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Local;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HistoricalDebtsSeeder extends Seeder
{
    /**
     * Migrar deuda histórica
     * Crea cargos de renta atrasados según el tipo de contrato (RENT_EUR_M2 o RENT_EUR_FIXED)
     * Procesamiento en tramos de 50 registros para control granular
     */
    public function run(): void
    {
        $this->command->info('🔄 Iniciando migración de deuda histórica...');

        // Cargar archivo consolidado de datos
        $allDebts = require database_path('seeders/data/historical_debts.php');

        $totalRecords = count($allDebts);
        $this->command->info("📊 Total de registros a procesar: {$totalRecords}");

        // Obtener status ISSUED
        $issuedStatus = ChargeStatus::where('code', 'ISSUED')->firstOrFail();

        // Estadísticas
        $stats = [
            'total' => 0,
            'procesados' => 0,
            'errores' => 0,
            'cargos_creados' => 0,
            'concesionarios_no_encontrados' => [],
            'locales_no_encontrados' => [],
        ];

        // Procesar en chunks de 50
        $chunks = array_chunk($allDebts, 50);
        $chunkNumber = 1;

        foreach ($chunks as $chunk) {
            $this->command->info("\n📦 Procesando tramo {$chunkNumber}/".count($chunks).' ('.count($chunk).' registros)');

            foreach ($chunk as $debt) {
                $stats['total']++;

                // Transacción POR REGISTRO para evitar abortos en cascada
                DB::beginTransaction();
                try {
                    $this->processDebt($debt, $issuedStatus, $stats);
                    DB::commit();
                    $stats['procesados']++;
                } catch (\Exception $e) {
                    DB::rollBack();
                    $stats['errores']++;
                    $this->command->error("❌ Error procesando {$debt['nombre']}: ".$e->getMessage());
                }
            }

            $this->command->info("✅ Tramo {$chunkNumber} completado");
            $chunkNumber++;
        }

        // Reporte final
        $this->printReport($stats);
    }

    private function processDebt(mixed $debt, ChargeStatus $issuedStatus, mixed &$stats): void
    {
        if (! is_array($debt)) {
            throw new \InvalidArgumentException('Debt payload must be an array.');
        }
        if (! is_array($stats)) {
            throw new \InvalidArgumentException('Stats must be an array reference.');
        }
        // Buscar concesionario por número de documento
        $concessionaire = Concessionaire::where('document_number', $debt['cedula'])->first();

        if (! $concessionaire) {
            $stats['concesionarios_no_encontrados'][] = "{$debt['nombre']} ({$debt['cedula']})";

            return;
        }

        // Obtener locales
        $localCodes = array_map('trim', explode(',', $debt['locales']));
        $locals = Local::whereIn('code', $localCodes)->get();

        if ($locals->isEmpty()) {
            $stats['locales_no_encontrados'][] = "{$debt['nombre']} - Locales: {$debt['locales']}";

            return;
        }

        // Obtener contrato del concesionario (activo o terminado)
        // Buscar primero activos (VIG/EXT/VENC), luego terminados (TERM) para locales recuperados
        $statusIds = ContractStatus::query()
            ->whereIn('code', ['VIG', 'EXT', 'VENC', 'TERM'])
            ->pluck('id')
            ->all();

        $contract = Contract::whereHas('concessionaires', function ($q) use ($concessionaire) {
            $q->where('concessionaire_id', $concessionaire->id);
        })
            ->whereIn('contract_status_id', $statusIds)
            ->orderByRaw("CASE WHEN contract_status_id IN (SELECT id FROM contract_statuses WHERE code IN ('VIG','EXT','VENC')) THEN 0 ELSE 1 END")
            ->first();

        if (! $contract) {
            $this->command->warn("⚠️  No se encontró contrato para {$debt['nombre']} (se crearán cargos vinculados al LOCAL)");
        }

        // Calcular períodos pendientes
        $lastPaidDate = Carbon::parse($debt['ultimo_pago'].'-01');
        $monthsPending = (int) $debt['meses_pendientes'];

        // Crear cargos por cada mes pendiente (clamp al fin de contrato si aplica)
        for ($i = 1; $i <= $monthsPending; $i++) {
            $period = $lastPaidDate->copy()->addMonths($i);

            // Si el contrato tiene fecha de fin y el periodo excede ese fin, detenemos
            if ($contract && $contract->end_date) {
                $contractEnd = Carbon::parse($contract->end_date)->endOfDay();
                if ($period->copy()->endOfMonth()->gt($contractEnd)) {
                    break; // no generar más meses posteriores al fin del contrato
                }
            }

            foreach ($locals as $local) {
                $this->createHistoricalCharge(
                    $contract,
                    $local,
                    $concessionaire,
                    $period,
                    $issuedStatus
                );
                $stats['cargos_creados']++;
            }
        }
    }

    private function createHistoricalCharge(
        ?Contract $contract,
        Local $local,
        Concessionaire $concessionaire,
        Carbon $period,
        ChargeStatus $issuedStatus
    ): void {
        // Determinar tipo de cargo según contrato
        // Asumimos RENT_EUR_M2 por defecto (puedes ajustar según lógica de negocio)
        $kind = 'RENT_EUR_M2';
        $source = 'RENT_RUN';

        // Verificar si ya existe el cargo
        $exists = Charge::where('debtor_type', 'LOCAL')
            ->where('debtor_id', $local->id)
            ->where('kind', $kind)
            ->where('period', $period->format('Y-m-01'))
            ->exists();

        if ($exists) {
            return; // Ya existe, skip
        }

        // Calcular monto según tipo de cargo
        // Renta por m2
        $m2 = (float) ($local->area_m2 ?? 0);
        $ratePerM2 = 2.50; // TODO: Obtener de MarketTariff o Contract
        $amountEur = $m2 * $ratePerM2;

        $amountMinor = (int) round($amountEur * 100); // Convertir a centavos

        Charge::create([
            'market_id' => $local->market_id,
            'local_id' => $local->id,
            'contract_id' => $contract?->id,
            'condo_period_id' => null,

            // Usar LOCAL como deudor para permitir un cargo por local/mes (consistente con el orquestador)
            'debtor_type' => 'LOCAL',
            'debtor_id' => $local->id,
            'origin_debtor_type' => 'LOCAL',
            'origin_debtor_id' => $local->id,

            'kind' => $kind,
            'currency' => 'EUR',
            'amount_minor' => $amountMinor,

            'period' => $period->format('Y-m-01'),
            'issued_on' => $period->copy()->startOfMonth(), // Usar copy() para no mutar
            'due_on' => $period->copy()->endOfMonth(),
            'settled_on' => null,

            'charge_status_id' => $issuedStatus->id,
            'source' => $source, // RENT_RUN o FIXED_RUN según tipo
            'idempotency_key' => "historical_local_{$local->id}_{$period->format('Y-m')}",
        ]);
    }

    private function printReport(mixed $stats): void
    {
        if (! is_array($stats)) {
            $stats = [];
        }
        $this->command->newLine(2);
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('📊 REPORTE FINAL - MIGRACIÓN DE DEUDA HISTÓRICA');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info("✅ Total registros procesados: {$stats['procesados']}/{$stats['total']}");
        $this->command->info("💰 Cargos creados: {$stats['cargos_creados']}");
        $this->command->info("❌ Errores: {$stats['errores']}");

        if (! empty($stats['concesionarios_no_encontrados'])) {
            $this->command->newLine();
            $this->command->warn('⚠️  CONCESIONARIOS NO ENCONTRADOS:');
            foreach (array_slice($stats['concesionarios_no_encontrados'], 0, 10) as $item) {
                $this->command->warn("   - {$item}");
            }
            if (count($stats['concesionarios_no_encontrados']) > 10) {
                $remaining = count($stats['concesionarios_no_encontrados']) - 10;
                $this->command->warn("   ... y {$remaining} más");
            }
        }

        if (! empty($stats['locales_no_encontrados'])) {
            $this->command->newLine();
            $this->command->warn('⚠️  LOCALES NO ENCONTRADOS:');
            foreach (array_slice($stats['locales_no_encontrados'], 0, 10) as $item) {
                $this->command->warn("   - {$item}");
            }
            if (count($stats['locales_no_encontrados']) > 10) {
                $remaining = count($stats['locales_no_encontrados']) - 10;
                $this->command->warn("   ... y {$remaining} más");
            }
        }

        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
