<?php

namespace Database\Seeders;

use App\Models\Concessionaire;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Local;
use App\Models\TradeCategory;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RecoveredContractsSeeder extends Seeder
{
    /**
     * Crear contratos TERMINADOS para locales RECUPERADOS
     * Esto permite mantener trazabilidad de deuda histórica
     *
     * Fecha de terminación: 30/09/2025
     * Status: TERMINADO (TER)
     */
    public function run(): void
    {
        $this->command->info('🔄 Creando contratos RECUPERADOS (TERMINADOS)...');

        // Obtener catálogos necesarios
        $statusTerminado = ContractStatus::where('code', 'TERM')->first();
        if (! $statusTerminado) {
            $this->command->error('❌ Status TERMINADO (TER) no existe');

            return;
        }

        $typeContr = ContractType::where('code', 'CONTR')->first();
        $modM2 = ContractModality::where('code', 'M2')->first();

        // Contratos recuperados con información completa
        $recoveredContracts = [
            // RECUPERADO nov-22
            ['doc' => 'V', 'num' => '13112506', 'name' => 'JAIRO RAMON LIRA DELGADO', 'locals' => ['A-21', 'A-22'], 'start' => '23/09/2020', 'rubro' => 'Papas'],

            // RECUPERADO PERO CANCELA nov-24
            ['doc' => 'V', 'num' => '22494445', 'name' => 'RAMONA DORILA LUCAS DE ACEB¡O', 'locals' => ['C-18', 'C-19'], 'start' => '28/10/2008', 'rubro' => 'Hortalizas'],

            // RECUPERADO PERO CANCELA oct-24
            ['doc' => 'V', 'num' => '16673765', 'name' => 'VICTOR YORLERVICT CHACON TORRES', 'locals' => ['E-10'], 'start' => '14/10/2013', 'rubro' => 'Tomates / Pimenton'],

            // RECUPERADO jun-23
            ['doc' => 'E', 'num' => '81510242', 'name' => 'MARIA CECILIA OLIM DOS RAMOS', 'locals' => ['G-04'], 'start' => '23/10/2008', 'rubro' => 'Charcutería'],

            // RECUPERADO abr-23
            ['doc' => 'V', 'num' => '6152259', 'name' => 'MANUEL FELIPE PINO BLANCO', 'locals' => ['G-16'], 'start' => '21/11/2008', 'rubro' => 'Quesos'],

            // RECUPERADO sep-22
            ['doc' => 'V', 'num' => '6863091', 'name' => 'JUAN CARLOS CHIPAMO MARRERO', 'locals' => ['H-01'], 'start' => '10/11/2008', 'rubro' => 'Utensilios'],

            // RECUPERADO may-23 - BM-08, BM-09
            ['doc' => 'V', 'num' => '6941647', 'name' => 'MARIA ISABEL FERNANDEZ CASANOVA', 'locals' => ['BM-08', 'BM-09'], 'start' => '04/06/2019', 'rubro' => 'Frutas'],

            // RECUPERADO jul-22
            ['doc' => 'V', 'num' => '10790449', 'name' => 'RICARDO ALBERTO ORTEGA GARCIA', 'locals' => ['BM-17'], 'start' => '09/08/2017', 'rubro' => 'Frutas'],

            // RECUPERADO may-23 - BM-19, BM-21, BM-21'
            ['doc' => 'V', 'num' => '4975803', 'name' => 'JOSE FRANCISCO DIAZ MORAN', 'locals' => ['BM-19', 'BM-21', 'BM-21"'], 'start' => '17/04/2023', 'rubro' => 'Frutas'],

            // RECUPERADO PERO CANCELA ene-25
            ['doc' => 'V', 'num' => '3229883', 'name' => 'ANGELINA MERCEDES RIZQUEZ CUPELLO', 'locals' => ['K-01'], 'start' => '27/05/2015', 'rubro' => 'Utensilios'],

            // RECUPERADO jul-24
            ['doc' => 'V', 'num' => '12910088', 'name' => 'KARINA REGINATO MUÑOZ', 'locals' => ['GM-20'], 'start' => '27/10/2008', 'rubro' => 'Productos Lacteos'],

            // RECUPERADO abr-24 - CM-02, CM-03
            ['doc' => 'V', 'num' => '13609489', 'name' => 'MARILYN ELENA MORALES AQUINO', 'locals' => ['CM-02', 'CM-03'], 'start' => '21/10/2019', 'rubro' => 'Delicatessen y Productos Importados'],

            // RECUPERADO - CM-12, CM-13, CM-14
            ['doc' => 'V', 'num' => '6941647', 'name' => 'MARIA ISABEL FERNANDEZ CASANOVA', 'locals' => ['CM-12', 'CM-13', 'CM-14'], 'start' => '29/08/2014', 'rubro' => 'Frutas'],

            // RECUPERADO feb-24 - DM-03, DM-04
            ['doc' => 'V', 'num' => '13067898', 'name' => 'MARJORIE CAROLINA GALLARDO RUIZ', 'locals' => ['DM-03', 'DM-04'], 'start' => '21/04/2025', 'rubro' => 'Productos y Alimentos Procesados'],

            // RECUPERADO abr-22
            ['doc' => 'E', 'num' => '81494940', 'name' => 'MARCOS OSVALDO RODRIGUES DE CASTRO', 'locals' => ['HM-26'], 'start' => '20/12/2021', 'rubro' => 'Lenceria'],

            // RECUPERADO sep-24
            ['doc' => 'V', 'num' => '11471357', 'name' => 'ANA GABRIELA MARIN HERRERA', 'locals' => ['HM-19'], 'start' => '27/10/2008', 'rubro' => 'Cocina'],

            // RECUPERADO dic-21 - AM-13..AM-17 (solo 15, 16, 17 en histórico)
            ['doc' => 'V', 'num' => '8753037', 'name' => 'JOSE RAFAEL HERNANDEZ PALMA', 'locals' => ['AM-15', 'AM-16', 'AM-17'], 'start' => '01/01/2010', 'rubro' => 'Cachapas'],
        ];

        $stats = [
            'procesados' => 0,
            'creados' => 0,
            'errores' => 0,
        ];

        foreach ($recoveredContracts as $data) {
            $stats['procesados']++;

            DB::beginTransaction();
            try {
                // Buscar concesionario
                $docType = DocumentType::where('code', $data['doc'])->first();
                $concessionaire = Concessionaire::where('document_type_id', $docType->id)
                    ->where('document_number', $data['num'])
                    ->first();

                if (! $concessionaire) {
                    $this->command->warn("⚠️  Concesionario no encontrado: {$data['name']} ({$data['num']})");
                    $stats['errores']++;
                    DB::rollBack();

                    continue;
                }

                // Buscar locales
                $locals = Local::whereIn('code', $data['locals'])->get();
                if ($locals->count() !== count($data['locals'])) {
                    $this->command->warn("⚠️  No se encontraron todos los locales para: {$data['name']}");
                    $stats['errores']++;
                    DB::rollBack();

                    continue;
                }

                // Buscar rubro
                $tradeCategory = TradeCategory::where('name', 'LIKE', '%'.$data['rubro'].'%')->first()
                    ?? TradeCategory::first();

                // Asegurar fechas válidas
                $parsedStart = Carbon::createFromFormat('d/m/Y', $data['start']);
                $parsedEnd = Carbon::createFromFormat('d/m/Y', '30/09/2025');
                if ($parsedStart->gt($parsedEnd)) {
                    $parsedStart = $parsedEnd->copy()->subYears(1);
                }

                // Crear contrato TERMINADO (número único requerido)
                $number = sprintf('REC-%s-%s-%s', $data['num'], $data['locals'][0], $parsedEnd->format('Ymd'));

                $contract = Contract::create([
                    'number' => $number,
                    'contract_type_id' => $typeContr->id,
                    'contract_modality_id' => $modM2->id,
                    'contract_status_id' => $statusTerminado->id, // TERMINADO
                    'trade_category_id' => $tradeCategory->id,
                    'start_date' => $parsedStart,
                    'end_date' => $parsedEnd,
                    'primary_concessionaire_id' => $concessionaire->id,
                ]);

                // Asociar concesionarios
                $contract->concessionaires()->attach($concessionaire->id, ['is_primary' => true]);

                // Asociar locales
                foreach ($locals as $local) {
                    $contract->locals()->attach($local->id);
                }

                $this->command->info("✅ Contrato TERMINADO creado: {$data['name']} - ".implode(',', $data['locals']));
                $stats['creados']++;
                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $this->command->error("❌ Error: {$data['name']} - ".$e->getMessage());
                $stats['errores']++;
            }
        }

        // Reporte
        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('📊 REPORTE - CONTRATOS RECUPERADOS');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info("✅ Procesados: {$stats['procesados']}");
        $this->command->info("✅ Creados: {$stats['creados']}");
        $this->command->info("❌ Errores: {$stats['errores']}");
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
