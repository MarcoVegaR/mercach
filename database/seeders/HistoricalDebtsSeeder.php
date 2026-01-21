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
            'locales_sin_contrato_activo' => [],
            'intentos_duplicados' => [],
            'seen_keys' => [],
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
        // Buscar concesionario por número de documento (permitir null)
        $concessionaire = Concessionaire::where('document_number', $debt['cedula'])->first();
        if (! $concessionaire) {
            $stats['concesionarios_no_encontrados'][] = "{$debt['nombre']} ({$debt['cedula']})";
        }

        // Obtener locales
        $localCodes = array_map('trim', explode(',', $debt['locales']));
        $locals = Local::whereIn('code', $localCodes)->get();

        if ($locals->isEmpty()) {
            $stats['locales_no_encontrados'][] = "{$debt['nombre']} - Locales: {$debt['locales']}";

            return;
        }

        // Status IDs para búsqueda de contratos
        $statusIds = ContractStatus::query()
            ->whereIn('code', ['VIG', 'EXT', 'VENC', 'TERM'])
            ->pluck('id')
            ->all();

        // Calcular períodos pendientes
        $lastPaidDate = Carbon::parse($debt['ultimo_pago'].'-01');
        $monthsPending = (int) $debt['meses_pendientes'];

        // Crear cargos por cada mes pendiente
        // Para cada local, buscar SU contrato específico (no prorratear entre múltiples locales)
        for ($i = 1; $i <= $monthsPending; $i++) {
            $period = $lastPaidDate->copy()->addMonths($i);

            foreach ($locals as $local) {
                // Buscar contrato específico para este local (filtrar por concesionario solo si existe)
                $contract = null;
                if ($concessionaire) {
                    $contract = Contract::whereHas('locals', function ($q) use ($local) {
                        $q->where('locals.id', $local->id);
                    })
                        ->whereHas('concessionaires', function ($q) use ($concessionaire) {
                            $q->where('concessionaire_id', $concessionaire->id);
                        })
                        ->whereIn('contract_status_id', $statusIds)
                        ->orderByRaw("CASE WHEN contract_status_id IN (SELECT id FROM contract_statuses WHERE code IN ('VIG','EXT','VENC')) THEN 0 ELSE 1 END")
                        ->first();
                }

                // Fallback: buscar por local solamente si no hay por concesionario
                if (! $contract) {
                    $contract = Contract::whereHas('locals', function ($q) use ($local) {
                        $q->where('locals.id', $local->id);
                    })
                        ->whereIn('contract_status_id', $statusIds)
                        ->orderByRaw("CASE WHEN contract_status_id IN (SELECT id FROM contract_statuses WHERE code IN ('VIG','EXT','VENC')) THEN 0 ELSE 1 END")
                        ->first();
                }

                if (! $contract) {
                    // No se encontró contrato para este local, crear cargo sin contract_id y registrar alerta
                    $stats['locales_sin_contrato_activo'][] = $local->code.' - '.$period->format('Y-m');

                    // Detección de intento duplicado (dataset) para clave Local+Periodo+Kind
                    $dupKind = 'RENT_EUR_M2';
                    $dupKey = 'historical_local_'.$local->id.'_'.$period->format('Y-m').'_'.$dupKind;
                    if (isset($stats['seen_keys'][$dupKey])) {
                        $stats['intentos_duplicados'][] = [
                            'key' => $dupKey,
                            'local' => $local->code,
                            'period' => $period->format('Y-m'),
                            'kind' => $dupKind,
                            'primera_origen' => $stats['seen_keys'][$dupKey]['origen'] ?? null,
                            'repetido_por' => $debt['nombre'].' ('.$debt['cedula'].')',
                        ];
                    } else {
                        $stats['seen_keys'][$dupKey] = [
                            'origen' => $debt['nombre'].' ('.$debt['cedula'].')',
                        ];
                    }

                    $this->createHistoricalCharge(
                        null,
                        $local,
                        $concessionaire,
                        $period,
                        $issuedStatus,
                        'RENT_EUR_M2',
                        'RENT_RUN',
                        null
                    );
                    $stats['cargos_creados']++;

                    continue;
                }

                // Resolver metadatos del contrato
                $contractStatusCode = (string) (DB::table('contract_statuses')->where('id', $contract->contract_status_id)->value('code') ?? '');
                $contractTypeCode = (string) (DB::table('contract_types')->where('id', $contract->contract_type_id)->value('code') ?? '');

                // Determinar si el contrato aplica para este periodo
                $periodStart = $period->copy()->startOfMonth();
                $periodEnd = $period->copy()->endOfMonth();
                $contractStart = $contract->start_date ? Carbon::parse($contract->start_date)->startOfDay() : null;
                $contractEnd = $contract->end_date ? Carbon::parse($contract->end_date)->endOfDay() : null;

                // Forzar vínculo FIXED para todos los meses posteriores al inicio si el contrato es tipo CONTR con precio mensual
                $alwaysFixedLink = $contractTypeCode === 'CONTR' && $contract->monthly_price_eur !== null && (float) $contract->monthly_price_eur > 0;
                $contractForPeriod = null;

                if ($alwaysFixedLink) {
                    // Ignora end_date: se considera vigente hasta TERM (a efectos de migración histórica)
                    $within = $contractStart ? $periodEnd->gte($contractStart) : true;
                } else {
                    // Regla: VENC sigue vigente hasta TERM (ignora end_date para vinculación)
                    // Update: VIG/EXT también deben ignorar end_date si el archivo de deuda histórica solicita un cargo.
                    if (in_array($contractStatusCode, ['VENC', 'VIG', 'EXT'])) {
                        $within = $contractStart ? $periodEnd->gte($contractStart) : true;
                    } else {
                        // Fallback para otros estados (si los hubiera)
                        $withinStart = $contractStart ? $periodEnd->gte($contractStart) : true;
                        $withinEnd = $contractEnd ? $periodStart->lte($contractEnd) : true;
                        $within = $withinStart && $withinEnd;
                    }
                }

                if ($within) {
                    $contractForPeriod = $contract;
                }
                if (! $contractForPeriod) {
                    // Existe contrato pero no está activo para el periodo evaluado
                    $stats['locales_sin_contrato_activo'][] = $local->code.' - '.$period->format('Y-m');
                }

                // Determinar tipo de cargo: usar el precio del contrato específico de este local (SIN prorrateo)
                $useFixed = $contractForPeriod && $contractTypeCode === 'CONTR' && $contract->monthly_price_eur !== null && (float) $contract->monthly_price_eur > 0;
                $kind = $useFixed ? 'RENT_EUR_FIXED' : 'RENT_EUR_M2';
                $source = $useFixed ? 'FIXED_RUN' : 'RENT_RUN';
                $amountMinorOverride = $useFixed ? (int) round(((float) $contract->monthly_price_eur) * 100) : null;

                // Detección de intento duplicado (dataset) para clave Local+Periodo+Kind
                $dupKind2 = $kind;
                $dupKey2 = 'historical_local_'.$local->id.'_'.$period->format('Y-m').'_'.$dupKind2;
                if (isset($stats['seen_keys'][$dupKey2])) {
                    $stats['intentos_duplicados'][] = [
                        'key' => $dupKey2,
                        'local' => $local->code,
                        'period' => $period->format('Y-m'),
                        'kind' => $dupKind2,
                        'primera_origen' => $stats['seen_keys'][$dupKey2]['origen'] ?? null,
                        'repetido_por' => $debt['nombre'].' ('.$debt['cedula'].')',
                    ];
                } else {
                    $stats['seen_keys'][$dupKey2] = [
                        'origen' => $debt['nombre'].' ('.$debt['cedula'].')',
                    ];
                }

                $this->createHistoricalCharge(
                    $contractForPeriod,
                    $local,
                    $concessionaire,
                    $period,
                    $issuedStatus,
                    $kind,
                    $source,
                    $amountMinorOverride
                );
                $stats['cargos_creados']++;
            }
        }
    }

    private function createHistoricalCharge(
        ?Contract $contract,
        Local $local,
        ?Concessionaire $concessionaire,
        Carbon $period,
        ChargeStatus $issuedStatus,
        ?string $kind = null,
        ?string $source = null,
        ?int $amountMinorOverride = null
    ): void {
        $finalKind = $kind ?? 'RENT_EUR_M2';
        $finalSource = $source ?? 'RENT_RUN';

        // Verificar si ya existe el cargo del mismo tipo para el periodo
        $exists = Charge::where('debtor_type', 'LOCAL')
            ->where('debtor_id', $local->id)
            ->where('kind', $finalKind)
            ->where('period', $period->format('Y-m-01'))
            ->exists();

        if ($exists) {
            return;
        }

        // Calcular monto
        $amountMinor = $amountMinorOverride;
        if ($amountMinor === null) {
            $m2 = (float) ($local->area_m2 ?? 0);
            $tariff = DB::table('market_tariffs')
                ->where('market_id', $local->market_id)
                ->where('is_current', true)
                ->orderByDesc('valid_from')
                ->first(['price_per_m2_eur_minor']);
            $priceMinorPerM2PerDay = $tariff ? (int) $tariff->price_per_m2_eur_minor : 0;
            $monthlyFactor = 365 / 12;
            $amountMinor = (int) round($priceMinorPerM2PerDay * $m2 * $monthlyFactor, 0);
        }

        Charge::create([
            'market_id' => $local->market_id,
            'local_id' => $local->id,
            'contract_id' => $contract?->id,
            'condo_period_id' => null,

            'debtor_type' => 'LOCAL',
            'debtor_id' => $local->id,
            'origin_debtor_type' => 'LOCAL',
            'origin_debtor_id' => $local->id,

            'kind' => $finalKind,
            'currency' => $finalKind === 'RENT_EUR_FIXED' ? 'USD' : 'EUR',
            'amount_minor' => $amountMinor,

            'period' => $period->format('Y-m-01'),
            'issued_on' => $period->copy()->startOfMonth(),
            'due_on' => $period->copy()->day(6),
            'settled_on' => null,

            'charge_status_id' => $issuedStatus->id,
            'source' => $finalSource,
            'idempotency_key' => "historical_local_{$local->id}_{$period->format('Y-m')}_{$finalKind}",
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

        if (! empty($stats['locales_sin_contrato_activo'])) {
            $this->command->newLine();
            $this->command->warn('⚠️  LOCALES CON DEUDA SIN CONTRATO ACTIVO (período):');
            foreach (array_slice($stats['locales_sin_contrato_activo'], 0, 10) as $item) {
                $this->command->warn("   - {$item}");
            }
            if (count($stats['locales_sin_contrato_activo']) > 10) {
                $remaining = count($stats['locales_sin_contrato_activo']) - 10;
                $this->command->warn("   ... y {$remaining} más");
            }
        }

        if (! empty($stats['intentos_duplicados'])) {
            $this->command->newLine();
            $this->command->warn('⚠️  INTENTOS DUPLICADOS DETECTADOS (dataset):');
            foreach (array_slice($stats['intentos_duplicados'], 0, 20) as $dup) {
                $line = ($dup['local'] ?? '?').' - '.($dup['period'] ?? '?').' - '.($dup['kind'] ?? '?');
                $first = $dup['primera_origen'] ?? 'N/A';
                $second = $dup['repetido_por'] ?? 'N/A';
                $this->command->warn('   - '.$line.' | primero: '.$first.' | repetido por: '.$second);
            }
            if (count($stats['intentos_duplicados']) > 20) {
                $remaining = count($stats['intentos_duplicados']) - 20;
                $this->command->warn("   ... y {$remaining} más");
            }
        }

        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
