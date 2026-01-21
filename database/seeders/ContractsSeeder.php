<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Contracts\Services\ContractServiceInterface;
use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Local;
use App\Models\LocalStatus;
use App\Models\TradeCategory;
use App\Services\ContractService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContractsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure required catalogs exist (idempotent lookups)
        $statusVigId = $this->statusIdByCode('VIG');
        $statusBorrId = $this->statusIdByCode('BORR');
        $statusTermId = $this->statusIdByCode('TERM');
        $typeContrId = $this->contractTypeIdByCode('CONTR');
        $typeConvId = $this->contractTypeIdByCode('CONV');
        $modM2Id = $this->modalityIdByCode('M2');
        $modFixedId = $this->modalityIdByCode('TFIJA');

        if (! $statusVigId || ! $statusBorrId || ! $statusTermId || ! $typeContrId || ! $typeConvId || ! $modM2Id || ! $modFixedId) {
            throw new \RuntimeException('Catálogos base faltantes para contratos (VIG/BORR/CONTR/CONV/M2/TFIJA).');
        }

        // Dataset rows provided (single-local and multi-local), grouped by concessionaire+start+rubro
        // Columns: doc_type, document, name, unit, start_date (d/m/Y), end_date (d/m/Y|INDEFINIDO), rubro
        $rows = [
            // --- Iniciales del primer bloque ---
            ['doc' => 'V', 'num' => '12062754', 'name' => 'ELLERY HARRY ACOSTA', 'unit' => 'A-01', 'start' => '23/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos'],
            ['doc' => 'V', 'num' => '5658210',  'name' => 'IDELFONSO CHACON GALVIS', 'unit' => 'A-02', 'start' => '23/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos'],
            ['doc' => 'E', 'num' => '699348',   'name' => 'MARIA FERRO DE SOUSA', 'unit' => 'A-03', 'start' => '31/01/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos'],
            ['doc' => 'V', 'num' => '13637899', 'name' => 'JESUS ANDRES LOVERA SALCEDO', 'unit' => 'A-04', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños/Condimentos'],
            ['doc' => 'V', 'num' => '20095054', 'name' => 'JESUS ALFREDO DE ALMEIDA MENDEZ', 'unit' => 'A-05', 'start' => '27/08/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños/Condimentos'],
            ['doc' => 'V', 'num' => '13637899', 'name' => 'JESUS ANDRES LOVERA SALCEDO', 'unit' => 'A-06', 'start' => '23/04/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños/Condimentos'],
            ['doc' => 'V', 'num' => '4290112',  'name' => 'LUCAS MIREYA MENDEZ DE ALMEIDA', 'unit' => 'A-07', 'start' => '15/02/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños/Condimentos'],
            ['doc' => 'V', 'num' => '20095054', 'name' => 'JESUS ALFREDO DE ALMEIDA MENDEZ', 'unit' => 'A-08', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños/Condimentos'],
            ['doc' => 'V', 'num' => '20095054', 'name' => 'JESUS ALFREDO DE ALMEIDA MENDEZ', 'unit' => 'A-09', 'start' => '01/02/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños/Condimentos'],
            ['doc' => 'V', 'num' => '11204676', 'name' => 'ROGER ANIBAL GALLARDO CERDEÑO', 'unit' => 'A-10', 'start' => '25/06/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '13693467', 'name' => 'NIEVES YAJAIRA CARRION RODRIGUEZ', 'unit' => 'A-11', 'start' => '03/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '5522862',  'name' => 'JOSE FRANCISCO ARIAS REYES', 'unit' => 'A-12', 'start' => '21/11/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],

            // --- Segundo bloque (simples) ---
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'A-17', 'start' => '16/04/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'A-18', 'start' => '16/04/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '5535489',  'name' => 'LEONARDO VALDEMAR BANDRES PINTO', 'unit' => 'A-19', 'start' => '23/11/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '17963266', 'name' => 'MARIA EUGENIA COLINA BABILONIA', 'unit' => 'A-20', 'start' => '14/07/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            // RECUPERADO nov-22
            // ['doc' => 'V', 'num' => '13112506', 'name' => 'JAIRO RAMON LIRA DELGADO', 'unit' => 'A-21', 'start' => '23/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            // ['doc' => 'V', 'num' => '13112506', 'name' => 'JAIRO RAMON LIRA DELGADO', 'unit' => 'A-22', 'start' => '23/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '11601769', 'name' => 'ZULE DEL VALLE VISNAGA', 'unit' => 'A-23', 'start' => '27/02/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '11601769', 'name' => 'ZULE DEL VALLE VISNAGA', 'unit' => 'A-24', 'start' => '22/11/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '5422936',  'name' => 'MARIBEL ULLOA DIAZ', 'unit' => 'A-27', 'start' => '10/04/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            ['doc' => 'V', 'num' => '12422281', 'name' => 'HECTOR FIGUEIRA CRESPO', 'unit' => 'A-29', 'start' => '20/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas'],
            // El caso J (jurídico) aporta datos incompletos (sin fecha inicio). Se ignora si falta start.
            // ['doc' => 'J', 'num' => '1', 'name' => 'IAMMCH', 'unit' => 'A-34', 'start' => null, 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Casabe'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'A-35', 'start' => '19/10/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'A-36', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos'],

            // --- Multi-local (mismo concesionario + misma fecha => 1 contrato con varios locales) ---
            ['doc' => 'V', 'num' => '5522862', 'name' => 'JOSE FRANCISCO ARIAS REYES', 'unit' => 'A-13', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas', 'ml' => true],
            ['doc' => 'V', 'num' => '5522862', 'name' => 'JOSE FRANCISCO ARIAS REYES', 'unit' => 'A-14', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas', 'ml' => true],
            ['doc' => 'V', 'num' => '4567574', 'name' => 'MARIA EVA ESTRADA PEREZ', 'unit' => 'A-15', 'start' => '19/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas', 'ml' => true],
            ['doc' => 'V', 'num' => '4567574', 'name' => 'MARIA EVA ESTRADA PEREZ', 'unit' => 'A-16', 'start' => '19/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas', 'ml' => true],
            ['doc' => 'E', 'num' => '717165', 'name' => 'JOAO ALBERTO FIGUEIRA', 'unit' => 'A-25', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas', 'ml' => true],
            ['doc' => 'E', 'num' => '717165', 'name' => 'JOAO ALBERTO FIGUEIRA', 'unit' => 'A-26', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papas', 'ml' => true],
            ['doc' => 'E', 'num' => '839310', 'name' => 'RAFFAELE GIOVANNUCCI', 'unit' => 'A-30', 'start' => '05/03/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Casabe', 'ml' => true],
            ['doc' => 'E', 'num' => '839310', 'name' => 'RAFFAELE GIOVANNUCCI', 'unit' => 'A-31', 'start' => '05/03/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Casabe', 'ml' => true],
            ['doc' => 'E', 'num' => '839310', 'name' => 'RAFFAELE GIOVANNUCCI', 'unit' => 'A-32', 'start' => '14/12/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Casabe', 'ml' => true],
            ['doc' => 'E', 'num' => '839310', 'name' => 'RAFFAELE GIOVANNUCCI', 'unit' => 'A-33', 'start' => '14/12/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Casabe', 'ml' => true],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'A-37', 'start' => '08/08/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos', 'ml' => true],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'A-38', 'start' => '08/08/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Huevos', 'ml' => true],

            // --- Nuevos: B - simples ---
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'B-02', 'start' => '02/04/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '23713562', 'name' => 'ANGELITA NARCISA GUACHICHULCA GUAMAN', 'unit' => 'B-16', 'start' => '09/09/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '17963266', 'name' => 'MARIA EUGENIA COLINA BABILONIA', 'unit' => 'B-22', 'start' => '10/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '13800165', 'name' => 'TERESA DEL VALLE REPOLE TOVAR', 'unit' => 'B-23', 'start' => '15/12/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '17963266', 'name' => 'MARIA EUGENIA COLINA BABILONIA', 'unit' => 'B-24', 'start' => '10/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '12376692', 'name' => 'NOHEMI MAXIMINA GAFARO GARCIA', 'unit' => 'B-25', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '11656691', 'name' => 'ELEOCTINES JOSE PIERLUISSI MATA', 'unit' => 'B-26', 'start' => '14/06/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '8259801',  'name' => 'VERONI DEL CARMEN MENDEZ', 'unit' => 'B-37', 'start' => '09/04/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '8259801',  'name' => 'VERONI DEL CARMEN MENDEZ', 'unit' => 'B-38', 'start' => '16/04/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '4962816',  'name' => 'JUDITH GERARDA MORENO CRISCI', 'unit' => 'B-45', 'start' => '01/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '4962816',  'name' => 'JUDITH GERARDA MORENO CRISCI', 'unit' => 'B-46', 'start' => '21/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],

            // --- Nuevos: B - multi-local (marcar cada fila con 'ml' => true para agrupar en un solo contrato) ---
            // JAQUELIN CASTILLO REYES: B-03..B-06 (30/10/2008)
            ['doc' => 'V', 'num' => '13853375', 'name' => 'JAQUELIN CASTILLO REYES', 'unit' => 'B-03', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '13853375', 'name' => 'JAQUELIN CASTILLO REYES', 'unit' => 'B-04', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '13853375', 'name' => 'JAQUELIN CASTILLO REYES', 'unit' => 'B-05', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '13853375', 'name' => 'JAQUELIN CASTILLO REYES', 'unit' => 'B-06', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // LILIA REYES RUNZA: B-07..B-10 (15/07/2012)
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'B-07', 'start' => '15/07/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'B-08', 'start' => '15/07/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'B-09', 'start' => '15/07/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'B-10', 'start' => '15/07/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MERCEDES MORILLO: B-11..B-13 (20/09/2004)
            ['doc' => 'V', 'num' => '6423231',  'name' => 'MERCEDES MORILLO', 'unit' => 'B-11', 'start' => '20/09/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '6423231',  'name' => 'MERCEDES MORILLO', 'unit' => 'B-12', 'start' => '20/09/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '6423231',  'name' => 'MERCEDES MORILLO', 'unit' => 'B-13', 'start' => '20/09/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MICHELL GREGORIO OROPEZA ARROS : B-14..B-15 (17/07/2012)
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS', 'unit' => 'B-14', 'start' => '17/07/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS', 'unit' => 'B-15', 'start' => '17/07/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // SILVESTRE JOSE BORGES LAZO: B-17..B-18 (30/10/2008)
            ['doc' => 'V', 'num' => '6288099',  'name' => 'SILVESTRE JOSE BORGES LAZO', 'unit' => 'B-17', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '6288099',  'name' => 'SILVESTRE JOSE BORGES LAZO', 'unit' => 'B-18', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MARÍA LUCIA SEM YANEZ: B-19..B-21 (18/11/2014)
            ['doc' => 'V', 'num' => '11407838', 'name' => 'MARÍA LUCIA SEM YANEZ', 'unit' => 'B-19', 'start' => '18/11/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '11407838', 'name' => 'MARÍA LUCIA SEM YANEZ', 'unit' => 'B-20', 'start' => '18/11/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '11407838', 'name' => 'MARÍA LUCIA SEM YANEZ', 'unit' => 'B-21', 'start' => '18/11/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // CRUZ CELINA GARCIA: B-27..B-28 (25/10/2008)
            ['doc' => 'V', 'num' => '3309084',  'name' => 'CRUZ CELINA GARCIA', 'unit' => 'B-27', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '3309084',  'name' => 'CRUZ CELINA GARCIA', 'unit' => 'B-28', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // NOHEMI MAXIMINA GAFARO GARCIA: B-29..B-31 (22/10/2008)
            ['doc' => 'V', 'num' => '12376692', 'name' => 'NOHEMI MAXIMINA GAFARO GARCIA', 'unit' => 'B-29', 'start' => '22/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '12376692', 'name' => 'NOHEMI MAXIMINA GAFARO GARCIA', 'unit' => 'B-30', 'start' => '22/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '12376692', 'name' => 'NOHEMI MAXIMINA GAFARO GARCIA', 'unit' => 'B-31', 'start' => '22/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // LISMAR CAROLINA BLANCO PARRA: B-32..B-33 (20/02/2020)
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'B-32', 'start' => '20/02/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'B-33', 'start' => '20/02/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MICHEL JOSE VAZQUEZ REYES: B-34..B-36 (09/07/2021)
            ['doc' => 'V', 'num' => '18442343', 'name' => 'MICHEL JOSE VAZQUEZ REYES', 'unit' => 'B-34', 'start' => '09/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '18442343', 'name' => 'MICHEL JOSE VAZQUEZ REYES', 'unit' => 'B-35', 'start' => '09/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '18442343', 'name' => 'MICHEL JOSE VAZQUEZ REYES', 'unit' => 'B-36', 'start' => '09/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // EMILTER JOSEFINA BETANCOURT GARCIA: B-39..B-41 (05/05/2016)
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'B-39', 'start' => '05/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'B-40', 'start' => '05/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'B-41', 'start' => '05/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // JUDITH GERARDA MORENO CRISCI: B-42..B-44 (20/09/2013)
            ['doc' => 'V', 'num' => '4962816',  'name' => 'JUDITH GERARDA MORENO CRISCI', 'unit' => 'B-42', 'start' => '20/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '4962816',  'name' => 'JUDITH GERARDA MORENO CRISCI', 'unit' => 'B-43', 'start' => '20/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '4962816',  'name' => 'JUDITH GERARDA MORENO CRISCI', 'unit' => 'B-44', 'start' => '20/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // --- Nuevos: C - simples ---
            ['doc' => 'V', 'num' => '10803405', 'name' => 'CARLOS MANUEL MENDEZ LIRA', 'unit' => 'C-03', 'start' => '11/06/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            // RECUPERADO PERO CANCELA nov-24
            // ['doc' => 'V', 'num' => '22494445', 'name' => 'RAMONA DORILA LUCAS DE ACEB¡O', 'unit' => 'C-18', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            // ['doc' => 'V', 'num' => '22494445', 'name' => 'RAMONA DORILA LUCAS DE ACEB¡O', 'unit' => 'C-19', 'start' => '29/05/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '15961355',  'name' => 'AUXILIADORA RAMONA CORDERO', 'unit' => 'C-23', 'start' => '23/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '15961355',  'name' => 'AUXILIADORA RAMONA CORDERO', 'unit' => 'C-24', 'start' => '27/02/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '15961355',  'name' => 'AUXILIADORA RAMONA CORDERO', 'unit' => 'C-25', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '3968118',  'name' => 'MIRNA HERNANDEZ DE PACHECO', 'unit' => 'C-35', 'start' => '29/08/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],
            ['doc' => 'V', 'num' => '10803405', 'name' => 'CARLOS MANUEL MENDEZ LIRA', 'unit' => 'C-46', 'start' => '23/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas'],

            // --- Nuevos: C - multi-local ---
            // FABIO ALEXANDER GOMES GOMES: C-01..C-02 (30/06/2011)
            ['doc' => 'V', 'num' => '17982564', 'name' => 'FABIO ALEXANDER GOMES GOMES', 'unit' => 'C-01', 'start' => '30/06/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '17982564', 'name' => 'FABIO ALEXANDER GOMES GOMES', 'unit' => 'C-02', 'start' => '30/06/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // JAVIER ALEXANDER DORSCHLAG RAMIREZ: C-04..C-06 (11/07/2023)
            ['doc' => 'V', 'num' => '12039863', 'name' => 'JAVIER ALEXANDER DORSCHLAG RAMIREZ', 'unit' => 'C-04', 'start' => '11/07/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '12039863', 'name' => 'JAVIER ALEXANDER DORSCHLAG RAMIREZ', 'unit' => 'C-05', 'start' => '11/07/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '12039863', 'name' => 'JAVIER ALEXANDER DORSCHLAG RAMIREZ', 'unit' => 'C-06', 'start' => '11/07/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // ERMELINDA LIRA DE MENDEZ: C-07..C-09 (25/10/2008)
            ['doc' => 'E', 'num' => '1045293',  'name' => 'ERMELINDA LIRA DE MENDEZ', 'unit' => 'C-07', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '1045293',  'name' => 'ERMELINDA LIRA DE MENDEZ', 'unit' => 'C-08', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '1045293',  'name' => 'ERMELINDA LIRA DE MENDEZ', 'unit' => 'C-09', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // CARLOS JORGE NUNES: C-10..C-13 (09/04/2013)
            ['doc' => 'E', 'num' => '81535511', 'name' => 'CARLOS JORGE NUNES', 'unit' => 'C-10', 'start' => '09/04/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '81535511', 'name' => 'CARLOS JORGE NUNES', 'unit' => 'C-11', 'start' => '09/04/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '81535511', 'name' => 'CARLOS JORGE NUNES', 'unit' => 'C-12', 'start' => '09/04/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '81535511', 'name' => 'CARLOS JORGE NUNES', 'unit' => 'C-13', 'start' => '09/04/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MIGUEL ANGEL TORRES PUENTE: C-14..C-17 (18/11/2016)
            ['doc' => 'V', 'num' => '18020871', 'name' => 'MIGUEL ANGEL TORRES PUENTE', 'unit' => 'C-14', 'start' => '18/11/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '18020871', 'name' => 'MIGUEL ANGEL TORRES PUENTE', 'unit' => 'C-15', 'start' => '18/11/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '18020871', 'name' => 'MIGUEL ANGEL TORRES PUENTE', 'unit' => 'C-16', 'start' => '18/11/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '18020871', 'name' => 'MIGUEL ANGEL TORRES PUENTE', 'unit' => 'C-17', 'start' => '18/11/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // CESIDIO NUNES DE OLIVEIRA: C-20..C-22 (13/02/2017)
            ['doc' => 'E', 'num' => '81882671', 'name' => 'CESIDIO NUNES DE OLIVEIRA', 'unit' => 'C-20', 'start' => '13/02/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '81882671', 'name' => 'CESIDIO NUNES DE OLIVEIRA', 'unit' => 'C-21', 'start' => '13/02/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'E', 'num' => '81882671', 'name' => 'CESIDIO NUNES DE OLIVEIRA', 'unit' => 'C-22', 'start' => '13/02/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // JOEL ARMANDO FERER: C-27..C-32 (03/04/2003)
            ['doc' => 'V', 'num' => '5893812',  'name' => 'JOEL ARMANDO FERER', 'unit' => 'C-27', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '5893812',  'name' => 'JOEL ARMANDO FERER', 'unit' => 'C-28', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '5893812',  'name' => 'JOEL ARMANDO FERER', 'unit' => 'C-29', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '5893812',  'name' => 'JOEL ARMANDO FERER', 'unit' => 'C-30', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '5893812',  'name' => 'JOEL ARMANDO FERER', 'unit' => 'C-31', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '5893812',  'name' => 'JOEL ARMANDO FERER', 'unit' => 'C-32', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // OLGA ESTHER DONADO ROSELLON: C-33..C-34 (20/09/2013)
            ['doc' => 'V', 'num' => '14411228', 'name' => 'OLGA ESTHER DONADO ROSELLON', 'unit' => 'C-33', 'start' => '20/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '14411228', 'name' => 'OLGA ESTHER DONADO ROSELLON', 'unit' => 'C-34', 'start' => '20/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MARIO ANTONIO PACHECO: C-36..C-37 (05/03/2007)
            ['doc' => 'V', 'num' => '5019749',  'name' => 'MARIO ANTONIO PACHECO', 'unit' => 'C-36', 'start' => '05/03/2007', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '5019749',  'name' => 'MARIO ANTONIO PACHECO', 'unit' => 'C-37', 'start' => '05/03/2007', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MIRNA HERNANDEZ DE PACHECO: C-38..C-40 (29/10/2008)
            ['doc' => 'V', 'num' => '3968118',  'name' => 'MIRNA HERNANDEZ DE PACHECO', 'unit' => 'C-38', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '3968118',  'name' => 'MIRNA HERNANDEZ DE PACHECO', 'unit' => 'C-39', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '3968118',  'name' => 'MIRNA HERNANDEZ DE PACHECO', 'unit' => 'C-40', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // MANUEL ADRIAN QUIJADA GUTIERREZ: C-41..C-45 (29/10/2008)
            ['doc' => 'V', 'num' => '4444264',  'name' => 'MANUEL ADRIAN QUIJADA GUTIERREZ', 'unit' => 'C-41', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '4444264',  'name' => 'MANUEL ADRIAN QUIJADA GUTIERREZ', 'unit' => 'C-42', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '4444264',  'name' => 'MANUEL ADRIAN QUIJADA GUTIERREZ', 'unit' => 'C-43', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '4444264',  'name' => 'MANUEL ADRIAN QUIJADA GUTIERREZ', 'unit' => 'C-44', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],
            ['doc' => 'V', 'num' => '4444264',  'name' => 'MANUEL ADRIAN QUIJADA GUTIERREZ', 'unit' => 'C-45', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hortalizas', 'ml' => true],

            // --- Nuevos: D - simples (todos son contratos individuales) ---
            ['doc' => 'V', 'num' => '21168188', 'name' => 'KELLY JOHANA CASTILLO', 'unit' => 'D-02', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '21168188', 'name' => 'KELLY JOHANA CASTILLO', 'unit' => 'D-03', 'start' => '06/09/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '21168188', 'name' => 'KELLY JOHANA CASTILLO', 'unit' => 'D-04', 'start' => '10/12/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '24277007', 'name' => 'KATHERINE ESTHER CASTRO CARION', 'unit' => 'D-05', 'start' => '27/06/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '24277007', 'name' => 'KATHERINE ESTHER CASTRO CARION', 'unit' => 'D-06', 'start' => '27/06/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '24277007', 'name' => 'KATHERINE ESTHER CASTRO CARION', 'unit' => 'D-07', 'start' => '27/06/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS', 'unit' => 'D-08', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS', 'unit' => 'D-09', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS', 'unit' => 'D-10', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '5118003',  'name' => 'BEATRIZ ELENA CLEMENTE', 'unit' => 'D-11', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '5118003',  'name' => 'BEATRIZ ELENA CLEMENTE', 'unit' => 'D-12', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '17754614', 'name' => 'MILEYDA DEL VALLE VASQUEZ REYES', 'unit' => 'D-13', 'start' => '29/02/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '17754615', 'name' => 'MIRRAY VASQUEZ REYES', 'unit' => 'D-14', 'start' => '22/08/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '17754614', 'name' => 'MILEYDA DEL VALLE VASQUEZ REYES', 'unit' => 'D-15', 'start' => '28/02/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '17754615', 'name' => 'MIRRAY VASQUEZ REYES', 'unit' => 'D-16', 'start' => '05/11/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19510688', 'name' => 'KELVIN XAVIER AGUILAR RODRIGUEZ', 'unit' => 'D-17', 'start' => '22/11/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19510688', 'name' => 'KELVIN XAVIER AGUILAR RODRIGUEZ', 'unit' => 'D-18', 'start' => '21/09/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19510688', 'name' => 'KELVIN XAVIER AGUILAR RODRIGUEZ', 'unit' => 'D-19', 'start' => '22/11/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19510688', 'name' => 'KELVIN XAVIER AGUILAR RODRIGUEZ', 'unit' => 'D-20', 'start' => '22/11/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19510688', 'name' => 'KELVIN XAVIER AGUILAR RODRIGUEZ', 'unit' => 'D-21', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '15183764', 'name' => 'ALBA MARIA MESINO RAMIREZ', 'unit' => 'D-22', 'start' => '25/10/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '15183764', 'name' => 'ALBA MARIA MESINO RAMIREZ', 'unit' => 'D-23', 'start' => '25/10/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '22445072', 'name' => 'BLANCA ROSA CORDOVA CARRILLO', 'unit' => 'D-24', 'start' => '11/03/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / JOJOTO'],
            ['doc' => 'V', 'num' => '22445072', 'name' => 'BLANCA ROSA CORDOVA CARRILLO', 'unit' => 'D-25', 'start' => '11/03/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / JOJOTO'],
            ['doc' => 'V', 'num' => '21409257', 'name' => 'ANDREINA ESTHER LARA ORTIZ', 'unit' => 'D-26', 'start' => '14/12/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '30687341', 'name' => 'WILMER JOSE MARTINEZ AYALA (ANDREINA)', 'unit' => 'D-27', 'start' => '23/03/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '30687341', 'name' => 'WILMER JOSE MARTINEZ AYALA (ANDREINA)', 'unit' => 'D-28', 'start' => '28/02/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '5535489',  'name' => 'LEONARDO VALDEMAR BANDRES PINTO', 'unit' => 'D-29', 'start' => '20/08/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '19220435', 'name' => 'ORLANDO JOSÈ GARRIDO MUÑOZ', 'unit' => 'D-30', 'start' => '29/03/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '613125',   'name' => 'TOMAS GONZALEZ', 'unit' => 'D-31', 'start' => '26/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '6526489',  'name' => 'NELLY JOSEFINA TORRES', 'unit' => 'D-32', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '6526489',  'name' => 'NELLY JOSEFINA TORRES', 'unit' => 'D-33', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '9966862',  'name' => 'ADELINA EVA NUÑEZ', 'unit' => 'D-34', 'start' => '26/12/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '4086980',  'name' => 'CARMEN JOSEFINA MARIN SALAZAR', 'unit' => 'D-35', 'start' => '10/04/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '6671004',  'name' => 'JOSE RAMON PACHECO TORRES', 'unit' => 'D-36', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '12215358', 'name' => 'ORLANDO BONALDY', 'unit' => 'D-37', 'start' => '03/10/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '8726388',  'name' => 'MARIA DEL CARMEN CARDOZO PALAENCIA', 'unit' => 'D-38', 'start' => '07/10/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '8726388',  'name' => 'MARIA DEL CARMEN CARDOZO PALAENCIA', 'unit' => 'D-39', 'start' => '07/10/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'JOJOTO'],
            ['doc' => 'V', 'num' => '12215358', 'name' => 'ORLANDO BONALDY', 'unit' => 'D-40', 'start' => '08/03/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '12215358', 'name' => 'ORLANDO BONALDY', 'unit' => 'D-41', 'start' => '08/03/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '16821467', 'name' => 'YAIMARY PACHECO RANGEL', 'unit' => 'D-42', 'start' => '24/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '16821467', 'name' => 'YAIMARY PACHECO RANGEL', 'unit' => 'D-43', 'start' => '24/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '16821467', 'name' => 'YAIMARY PACHECO RANGEL', 'unit' => 'D-44', 'start' => '24/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '2764528',  'name' => 'ZAIDA JOSEFINA GONZALEZ ARIAS', 'unit' => 'D-01', 'start' => '23/04/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '2764528',  'name' => 'ZAIDA JOSEFINA GONZALEZ ARIAS', 'unit' => 'D-45', 'start' => '23/04/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],
            ['doc' => 'V', 'num' => '4822387',  'name' => 'GLADYS NINOSKA DIAZ ALVAREZ', 'unit' => 'D-46', 'start' => '09/04/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Verduras / hortalizas'],

            // --- Nuevos: E - simples ---
            ['doc' => 'V', 'num' => '19203832', 'name' => 'JOSE ANTONIO GONZALEZ CARABALLO', 'unit' => 'E-03', 'start' => '21/03/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates'],
            ['doc' => 'V', 'num' => '19203832', 'name' => 'JOSE ANTONIO GONZALEZ CARABALLO', 'unit' => 'E-04', 'start' => '29/01/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'E-05', 'start' => '28/02/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'E-06', 'start' => '28/02/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '19203832', 'name' => 'JOSE ANTONIO GONZALEZ CARABALLO', 'unit' => 'E-07', 'start' => '01/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '10699908', 'name' => 'LUIS ENRIQUE DORTA BIGOTT', 'unit' => 'E-08', 'start' => '09/04/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '19203832', 'name' => 'JOSE ANTONIO GONZALEZ CARABALLO', 'unit' => 'E-09', 'start' => '28/03/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            // RECUPERADO PERO CANCELA oct-24
            // ['doc' => 'V', 'num' => '16673765', 'name' => 'VICTOR YORLERVICT CHACON TORRES', 'unit' => 'E-10', 'start' => '14/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '13466369', 'name' => 'DEISY MARY RAMIREZ PULIDO', 'unit' => 'E-11', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '13466369', 'name' => 'DEISY MARY RAMIREZ PULIDO', 'unit' => 'E-12', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '6102758',  'name' => 'JOSE GONZALEZ', 'unit' => 'E-13', 'start' => '28/02/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '6102758',  'name' => 'JOSE GONZALEZ', 'unit' => 'E-14', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates'],
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'E-15', 'start' => '25/04/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates'],
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'E-16', 'start' => '25/04/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'E-17', 'start' => '30/09/2022', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hierbas y Compuestos'],
            ['doc' => 'V', 'num' => '16202541', 'name' => 'YESSIKA ABIGAHIL GARCIA MARTURER', 'unit' => 'E-18', 'start' => '10/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hierbas y Compuestos'],
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'E-19', 'start' => '30/09/2022', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'E-20', 'start' => '23/12/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'E', 'num' => '83350279', 'name' => 'MOISES QUINTERO', 'unit' => 'E-21', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'E', 'num' => '83350279', 'name' => 'MOISES QUINTERO', 'unit' => 'E-22', 'start' => '04/07/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hierbas y Compuestos'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'E-23', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'E-24', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '2765340',  'name' => 'SONIA OCTAVIA TORRELLAS', 'unit' => 'E-25', 'start' => '13/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '5011407',  'name' => 'FRANCISCA ENCARNACION MEDINA DE FEIJOO', 'unit' => 'E-26', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '8259801',  'name' => 'VERONI DEL CARMEN MENDEZ', 'unit' => 'E-27', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '8259801',  'name' => 'VERONI DEL CARMEN MENDEZ', 'unit' => 'E-28', 'start' => '05/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Hierbas y Compuestos'],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-29', 'start' => '05/06/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-30', 'start' => '05/06/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-31', 'start' => '05/06/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-32', 'start' => '09/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '4888795',  'name' => 'DILIA NOGALES', 'unit' => 'E-37', 'start' => '23/04/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '4888795',  'name' => 'DILIA NOGALES', 'unit' => 'E-40', 'start' => '02/07/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates'],
            ['doc' => 'V', 'num' => '17963266', 'name' => 'MARIA EUGENIA COLINA BABILONIA', 'unit' => 'E-43', 'start' => '27/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],
            ['doc' => 'V', 'num' => '17963266', 'name' => 'MARIA EUGENIA COLINA BABILONIA', 'unit' => 'E-44', 'start' => '27/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton'],

            // --- Nuevos: E - multi-local ---
            // ELIZABETH COROMOTO BASTIDAS: E-01..E-02 (09/07/2021)
            ['doc' => 'V', 'num' => '4886783',  'name' => 'ELIZABETH COROMOTO BASTIDAS', 'unit' => 'E-01', 'start' => '09/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '4886783',  'name' => 'ELIZABETH COROMOTO BASTIDAS', 'unit' => 'E-02', 'start' => '09/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],

            // LUIS MANUEL BERROTERAN: E-33..E-36 (05/06/2012 y 30/10/2008)
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-33', 'start' => '05/06/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-34', 'start' => '05/06/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-35', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '8750024',  'name' => 'LUIS MANUEL BERROTERAN', 'unit' => 'E-36', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],

            // ISRAEL ANTONIO NOGALES: E-38..E-39 (28/10/2008)
            ['doc' => 'V', 'num' => '6727635',  'name' => 'ISRAEL ANTONIO NOGALES', 'unit' => 'E-38', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '6727635',  'name' => 'ISRAEL ANTONIO NOGALES', 'unit' => 'E-39', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],

            // ANDRES ALGARIN BOGADO: E-41..E-42 (15/05/2003)
            ['doc' => 'V', 'num' => '6106558',  'name' => 'ANDRES ALGARIN BOGADO', 'unit' => 'E-41', 'start' => '15/05/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '6106558',  'name' => 'ANDRES ALGARIN BOGADO', 'unit' => 'E-42', 'start' => '15/05/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],

            // --- Nuevos: F - simples ---
            ['doc' => 'V', 'num' => '5418342',  'name' => 'MERY ROSA MARTINEZ', 'unit' => 'F-01', 'start' => '06/09/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '5418342',  'name' => 'MERY ROSA MARTINEZ', 'unit' => 'F-04', 'start' => '18/12/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '6394150',  'name' => 'HIPOLITO DIAZ JAIME', 'unit' => 'F-07', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '11679422', 'name' => 'SOHAY ROSABEL CORDERO RODRIGUEZ', 'unit' => 'F-08', 'start' => '24/09/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '2126340',  'name' => 'JUAN ANTONIO PONCE LONGA', 'unit' => 'F-11', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla'],
            ['doc' => 'V', 'num' => '9966862',  'name' => 'ADELINA EVA NUÑEZ', 'unit' => 'F-12', 'start' => '21/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '10813224', 'name' => 'MARIA MELIDA ARRIECHI BASTIDAS', 'unit' => 'F-15', 'start' => '10/02/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'additional' => [
                ['doc' => 'V', 'num' => '8481598', 'name' => 'BRIYITT VALDERRAMA'],
            ]],
            ['doc' => 'V', 'num' => '10813224', 'name' => 'MARIA MELIDA ARRIECHI BASTIDAS', 'unit' => 'F-16', 'start' => '10/02/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'additional' => [
                ['doc' => 'V', 'num' => '8481598', 'name' => 'BRIYITT VALDERRAMA'],
            ]],
            ['doc' => 'V', 'num' => '10813224', 'name' => 'MARIA MELIDA ARRIECHI BASTIDAS', 'unit' => 'F-17', 'start' => '23/10/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '10872140', 'name' => 'GEORATTSY JOSEFINA URBANO ESCOBAR', 'unit' => 'F-21', 'start' => '09/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla'],
            ['doc' => 'V', 'num' => '3764916',  'name' => 'LUIS HERIBERTO VELAZCO SANCHEZ', 'unit' => 'F-22', 'start' => '28/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '16562653', 'name' => 'LISMAR CAROLINA BLANCO PARRA', 'unit' => 'F-23', 'start' => '29/05/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies'],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'F-24', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos'],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'F-25', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos'],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'F-26', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos'],
            ['doc' => 'V', 'num' => '17082408', 'name' => 'ALEJANDRA DEL VALLE CASTILLO PEREZ', 'unit' => 'F-29', 'start' => '12/12/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos'],
            ['doc' => 'V', 'num' => '17082408', 'name' => 'ALEJANDRA DEL VALLE CASTILLO PEREZ', 'unit' => 'F-30', 'start' => '12/12/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos'],
            ['doc' => 'V', 'num' => '17082408', 'name' => 'ALEJANDRA DEL VALLE CASTILLO PEREZ', 'unit' => 'F-31', 'start' => '12/12/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos'],
            ['doc' => 'V', 'num' => '18819003', 'name' => 'YUDIS MARIA AYALA', 'unit' => 'F-32', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures'],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ PEÑA', 'unit' => 'F-39', 'start' => '21/05/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures'],
            ['doc' => 'E', 'num' => '81320060', 'name' => 'MAGALY MERCEDES AVILA FANDIÑO', 'unit' => 'F-40', 'start' => '10/04/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures'],

            // --- Nuevos: F - multi-local ---
            // LUIS EDUARDO ORIQUIN CONTRERAS: F-02..F-03 (27/11/2006)
            ['doc' => 'V', 'num' => '4882181',  'name' => 'LUIS EDUARDO ORIQUIN CONTRERAS', 'unit' => 'F-02', 'start' => '27/11/2006', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],
            ['doc' => 'V', 'num' => '4882181',  'name' => 'LUIS EDUARDO ORIQUIN CONTRERAS', 'unit' => 'F-03', 'start' => '27/11/2006', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],

            // LUIS EDUARDO ORIQUIN CONTRERAS: F-05..F-06 (13/11/2014)
            ['doc' => 'V', 'num' => '4882181',  'name' => 'LUIS EDUARDO ORIQUIN CONTRERAS', 'unit' => 'F-05', 'start' => '13/11/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],
            ['doc' => 'V', 'num' => '4882181',  'name' => 'LUIS EDUARDO ORIQUIN CONTRERAS', 'unit' => 'F-06', 'start' => '13/11/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],

            // FERNANDO GUEVARA: F-09..F-10 (10/11/2003)
            ['doc' => 'V', 'num' => '18595725', 'name' => 'FERNANDO GUEVARA', 'unit' => 'F-09', 'start' => '10/11/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],
            ['doc' => 'V', 'num' => '18595725', 'name' => 'FERNANDO GUEVARA', 'unit' => 'F-10', 'start' => '10/11/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],

            // JUAN ANTONIO PONCE LONGA: F-13..F-14 (28/02/2003)
            ['doc' => 'V', 'num' => '2126340',  'name' => 'JUAN ANTONIO PONCE LONGA', 'unit' => 'F-13', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],
            ['doc' => 'V', 'num' => '2126340',  'name' => 'JUAN ANTONIO PONCE LONGA', 'unit' => 'F-14', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],

            // MICHELL GREGORIO OROPEZA ARROS : F-18..F-20 (07/10/2014)
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS ', 'unit' => 'F-18', 'start' => '07/10/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS ', 'unit' => 'F-19', 'start' => '07/10/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS ', 'unit' => 'F-20', 'start' => '07/10/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cebolla, Ajos y Ajies', 'ml' => true],

            // JOSE FRANCISCO DIAZ MORAN: F-27..F-28 (08/10/2020)
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'F-27', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos', 'ml' => true],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'F-28', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos', 'ml' => true],

            // WILMER JOSE MARTINEZ AYALA (ANDREINA): F-33..F-34 (21/12/2016)
            ['doc' => 'V', 'num' => '30687341', 'name' => 'WILMER JOSE MARTINEZ AYALA (ANDREINA)', 'unit' => 'F-33', 'start' => '21/12/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],
            ['doc' => 'V', 'num' => '30687341', 'name' => 'WILMER JOSE MARTINEZ AYALA (ANDREINA)', 'unit' => 'F-34', 'start' => '21/12/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],

            // UDALRICO MENDEZ: F-35..F-36 (28/10/2008)
            ['doc' => 'V', 'num' => '5430357',  'name' => 'UDALRICO MENDEZ', 'unit' => 'F-35', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],
            ['doc' => 'V', 'num' => '5430357',  'name' => 'UDALRICO MENDEZ', 'unit' => 'F-36', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],

            // JUAN FRANCISCO DIAZ PEÑA: F-37..F-38 (27/10/2008)
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ PEÑA', 'unit' => 'F-37', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos / Cambures', 'ml' => true],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ PEÑA', 'unit' => 'F-38', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Platanos / Cambures', 'ml' => true],

            // MARTHA ELENA PEÑA DE DIAZ: F-41..F-44 (29/08/2004)
            ['doc' => 'V', 'num' => '13088201', 'name' => 'MARTHA ELENA PEÑA DE DIAZ', 'unit' => 'F-41', 'start' => '29/08/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],
            ['doc' => 'V', 'num' => '13088201', 'name' => 'MARTHA ELENA PEÑA DE DIAZ', 'unit' => 'F-42', 'start' => '29/08/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],
            ['doc' => 'V', 'num' => '13088201', 'name' => 'MARTHA ELENA PEÑA DE DIAZ', 'unit' => 'F-43', 'start' => '29/08/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],
            ['doc' => 'V', 'num' => '13088201', 'name' => 'MARTHA ELENA PEÑA DE DIAZ', 'unit' => 'F-44', 'start' => '29/08/2004', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cambures', 'ml' => true],

            // --- Nuevos: G - simples ---
            ['doc' => 'V', 'num' => '13159971', 'name' => 'GIUSSEPPA ASUNTA MANGIALOMINI CALABRESE', 'unit' => 'G-00', 'start' => '01/06/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Café'],
            ['doc' => 'V', 'num' => '14046671', 'name' => 'FREDDY JOSE MORALES', 'unit' => 'G-01', 'start' => '10/06/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos de Belleza'],
            ['doc' => 'V', 'num' => '6440922',  'name' => 'BELARDINO TRITTO HERNANDEZ', 'unit' => 'G-01A', 'start' => '14/06/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida', 'additional' => [
                ['doc' => 'V', 'num' => '6432192', 'name' => 'PEDRO TAPIA RAMIREZ'],
            ]],
            ['doc' => 'E', 'num' => '81983762', 'name' => 'FERNANDA OLIM FERRAZ', 'unit' => 'G-02', 'start' => '28/01/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Confitería'],
            ['doc' => 'V', 'num' => '20799341', 'name' => 'VILMA ALICIA VACA ALVAREZ DE ABRIL', 'unit' => 'G-03', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Charcutería'],
            // RECUPERADO jun-23
            // ['doc' => 'E', 'num' => '81510242', 'name' => 'MARIA CECILIA OLIM DOS RAMOS', 'unit' => 'G-04', 'start' => '23/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Charcutería'],
            ['doc' => 'V', 'num' => '11920303', 'name' => 'JOAO LEONARDO DE ABREU ASCENCAO', 'unit' => 'G-05', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Charcutería'],
            ['doc' => 'V', 'num' => '14689448', 'name' => 'DEIVIS DE ABREU PEREIRA', 'unit' => 'G-06', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Charcutería'],
            ['doc' => 'V', 'num' => '13537834', 'name' => 'JOHNNY DE ABREU PEREIRA', 'unit' => 'G-07', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Charcutería'],
            ['doc' => 'V', 'num' => '13537834', 'name' => 'JOHNNY DE ABREU PEREIRA', 'unit' => 'G-08', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Charcutería'],
            ['doc' => 'V', 'num' => '5420214',  'name' => 'MARIA DEL CARMEN PEREIRA DE ABREU', 'unit' => 'G-09', 'start' => '03/02/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            ['doc' => 'V', 'num' => '6106488',  'name' => 'YESMIN DEL VALLE QUINTERO SOSA', 'unit' => 'G-10', 'start' => '04/10/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            ['doc' => 'V', 'num' => '10504961', 'name' => 'JUAN ELADIO DE ABREU ASCENCAO', 'unit' => 'G-11', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            ['doc' => 'V', 'num' => '15049249', 'name' => 'MANUEL ABREU CONGALVE', 'unit' => 'G-12', 'start' => '06/02/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos', 'additional' => [
                ['doc' => 'V', 'num' => '6285377', 'name' => 'JOSE CELESTINO JARDIN'],
            ]],
            ['doc' => 'V', 'num' => '7012039',  'name' => 'JOSE GOMEZ ABREU', 'unit' => 'G-13', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            ['doc' => 'V', 'num' => '25385835', 'name' => 'ELIAS JESUS MARTINEZ SALAZAR', 'unit' => 'G-14', 'start' => '10/07/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Telecomunicaciones'],
            ['doc' => 'V', 'num' => '18313165', 'name' => 'JOHAN ENRIQUE RODRIGUEZ GALINDO', 'unit' => 'G-15A', 'start' => '19/11/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos Refrigerados y Congelados'],
            ['doc' => 'V', 'num' => '19966344', 'name' => 'MIGUEL ANGEL PEREIRA HERRERA', 'unit' => 'G-15B', 'start' => '23/04/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            // RECUPERADO abr-23
            // ['doc' => 'V', 'num' => '6152259',  'name' => 'MANUEL FELIPE PINO BLANCO', 'unit' => 'G-16', 'start' => '21/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            ['doc' => 'V', 'num' => '6022417',  'name' => 'JOSEFA ELBA ZAMBRANO MENDEZ', 'unit' => 'G-17', 'start' => '11/12/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quesos'],
            ['doc' => 'V', 'num' => '15805646', 'name' => 'MARIBEL DE ABREU PEREIRA', 'unit' => 'G-18', 'start' => '06/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pan'],
            ['doc' => 'V', 'num' => '12362276', 'name' => 'GLORIA MERCEDES MARTEL', 'unit' => 'G-19', 'start' => '31/03/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ferretería'],
            ['doc' => 'V', 'num' => '24277007', 'name' => 'KATHERINE ESTHER CASTRO CARION', 'unit' => 'G-20', 'start' => '03/04/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pastelitos Andinos / Dulces Criollos / Tortas en Porciones / Bebidas no Alcoholicas'],
            // ELIMINADOS: Duplicado V-634309 FILOMENA PUIG MARES DE VALLS (locales G-21A, G-21B)
            // ['doc' => 'E', 'num' => '634309',   'name' => 'FILOMENA PUIG MARES DE VALLS', 'unit' => 'G-21A', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'rubro' => 'Manualidades'],
            // ['doc' => 'E', 'num' => '634309',   'name' => 'FILOMENA PUIG MARES DE VALLS', 'unit' => 'G-21B', 'start' => '21/02/2011', 'end' => 'INDEFINIDO', 'rubro' => 'Cholas / Pantuflas / Paraguas'],

            // --- Nuevos: H - simples ---
            // RECUPERADO sep-22
            // ['doc' => 'V', 'num' => '6863091',  'name' => 'JUAN CARLOS CHIPAMO MARRERO', 'unit' => 'H-01', 'start' => '10/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Utensilios'],
            ['doc' => 'E', 'num' => '81684570', 'name' => 'MARIA DE FATIMA GOMES DE NOBREGA', 'unit' => 'H-01A', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Utensilios'],
            ['doc' => 'V', 'num' => '5217015',  'name' => 'LUIS DE SOUSA MENDOZA', 'unit' => 'H-02', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Utensilios'],
            ['doc' => 'E', 'num' => '81684570', 'name' => 'MARIA DE FATIMA GOMES DE NOBREGA', 'unit' => 'H-02A', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla'],
            ['doc' => 'V', 'num' => '6840226',  'name' => 'JESUS RUBEN PACHECO', 'unit' => 'H-03', 'start' => '14/04/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cochino'],
            ['doc' => 'V', 'num' => '12428760', 'name' => 'MICHELE FERNANDEZ', 'unit' => 'H-12', 'start' => '30/05/2022', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes'],
            ['doc' => 'V', 'num' => '12119259', 'name' => 'JULIO CESAR RUIZ PERDIGON', 'unit' => 'H-13', 'start' => '31/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes'],
            ['doc' => 'V', 'num' => '6012218',  'name' => 'MANUEL ALFREDO PESTANO PUERTA', 'unit' => 'H-14', 'start' => '10/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes'],
            ['doc' => 'V', 'num' => '12399747', 'name' => 'ALAN STANLEY PAZMIÑO NIETO', 'unit' => 'H-15', 'start' => '10/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes'],
            ['doc' => 'V', 'num' => '14690782', 'name' => 'MARIA DA CONCEICAO DE ABREU JARDIN', 'unit' => 'H-16', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pollos'],
            ['doc' => 'V', 'num' => '14690782', 'name' => 'MARIA DA CONCEICAO DE ABREU JARDIN', 'unit' => 'H-16A', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pollos'],
            ['doc' => 'V', 'num' => '5520548',  'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'H-17', 'start' => '30/01/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pollos'],
            ['doc' => 'V', 'num' => '12640517', 'name' => 'JOSE BERNARDO DULCEY MEDERO', 'unit' => 'H-18', 'start' => '24/09/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Derivados de Cerdo y Refrescos'],
            ['doc' => 'V', 'num' => '5001396',  'name' => 'LESBIA TIBISAY ARIAS PEREZ', 'unit' => 'H-19', 'start' => '21/12/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Bolsas'],
            ['doc' => 'V', 'num' => '5001396',  'name' => 'LESBIA TIBISAY ARIAS PEREZ', 'unit' => 'H-20', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Bolsas'],
            ['doc' => 'V', 'num' => '9028802',  'name' => 'EDUARDO ROJAS VELA', 'unit' => 'H-21', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Bolsas'],
            ['doc' => 'V', 'num' => '9375327', 'name' => 'SULAY GARCIA', 'unit' => 'H-22', 'start' => '28/11/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Confitería', 'additional' => [
                ['doc' => 'V', 'num' => '11704798', 'name' => 'MARIA BETANIA GARCIA'],
            ]],
            ['doc' => 'V', 'num' => '6930089',  'name' => 'JULIA YANETTE LOZADA', 'unit' => 'H-23', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aliños'],

            // --- Nuevos: H - multi-local ---
            // JOAO MARTINHO NUNES: H-04..H-05 (24/10/2008)
            ['doc' => 'E', 'num' => '81875217', 'name' => 'JOAO MARTINHO NUNES', 'unit' => 'H-04', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pescados / mariscos', 'ml' => true],
            ['doc' => 'E', 'num' => '81875217', 'name' => 'JOAO MARTINHO NUNES', 'unit' => 'H-05', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pescados / mariscos', 'ml' => true],

            // ANGELA ANAIDE ABREU ALVES: H-06..H-07 (24/10/2008)
            ['doc' => 'V', 'num' => '13712622', 'name' => 'ANGELA ANAIDE ABREU ALVES', 'unit' => 'H-06', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pescados / mariscos', 'ml' => true],
            ['doc' => 'V', 'num' => '13712622', 'name' => 'ANGELA ANAIDE ABREU ALVES', 'unit' => 'H-07', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Pescados / mariscos', 'ml' => true],

            // CARLOS ALBERTO DE SOUSA: H-08..H-09 (29/10/2008)
            ['doc' => 'E', 'num' => '81525225', 'name' => 'CARLOS ALBERTO DE SOUSA', 'unit' => 'H-08', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes', 'ml' => true],
            ['doc' => 'E', 'num' => '81525225', 'name' => 'CARLOS ALBERTO DE SOUSA', 'unit' => 'H-09', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes', 'ml' => true],

            // CESIDIO NUNES DE OLIVEIRA: H-10..H-11 (10/09/2020)
            ['doc' => 'E', 'num' => '81882671', 'name' => 'CESIDIO NUNES DE OLIVEIRA', 'unit' => 'H-10', 'start' => '10/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes', 'ml' => true],
            ['doc' => 'E', 'num' => '81882671', 'name' => 'CESIDIO NUNES DE OLIVEIRA', 'unit' => 'H-11', 'start' => '10/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Carnes', 'ml' => true],

            // --- Nuevos: I - simples ---
            ['doc' => 'V', 'num' => '12062754', 'name' => 'ELLERY HARRY ACOSTA NUÑEZ', 'unit' => 'I-01', 'start' => '01/11/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla / misceláneos'],
            ['doc' => 'V', 'num' => '12062754', 'name' => 'ELLERY HARRY ACOSTA NUÑEZ', 'unit' => 'I-02', 'start' => '01/11/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla / misceláneos'],
            ['doc' => 'V', 'num' => '12485480', 'name' => 'YULL CHARLES ACOSTA NUÑEZ', 'unit' => 'I-03', 'start' => '29/12/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla / misceláneos'],
            ['doc' => 'V', 'num' => '12485480', 'name' => 'YULL CHARLES ACOSTA NUÑEZ', 'unit' => 'I-04', 'start' => '29/12/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla / misceláneos'],
            ['doc' => 'V', 'num' => '6324559',  'name' => 'MARIA PILAR LEBOREIRO SANPEDRO', 'unit' => 'I-05', 'start' => '18/01/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa / textiles'],
            ['doc' => 'V', 'num' => '6324559',  'name' => 'MARIA PILAR LEBOREIRO SANPEDRO', 'unit' => 'I-06', 'start' => '18/01/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa / textiles'],
            ['doc' => 'V', 'num' => '18361214', 'name' => 'HENRY OSWALDO FERNANDEZ CERVEN', 'unit' => 'I-07', 'start' => '12/08/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas Secas'],
            ['doc' => 'V', 'num' => '16330837', 'name' => 'GELSILICA JANET MONTEMARANI', 'unit' => 'I-08', 'start' => '16/03/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla / misceláneos'],
            ['doc' => 'V', 'num' => '3312658',  'name' => 'MARIA CRISTINA RAMIRES DE SANCHEZ', 'unit' => 'I-09', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Velas'],
            ['doc' => 'V', 'num' => '17300795', 'name' => 'JOHN CARLO JIMENEZ NUÑES', 'unit' => 'I-10', 'start' => '16/11/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla / misceláneos'],
            ['doc' => 'V', 'num' => '5115168',  'name' => 'PRISCILA ISABEL PIRONA RAMIREZ', 'unit' => 'I-11', 'start' => '29/12/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa / Quincalla'],
            ['doc' => 'J', 'num' => '402330014', 'name' => 'REPRESENTACIONES GAEMA CA', 'unit' => 'I-12', 'start' => '12/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa / Quincalla'],

            // --- Nuevos: J - simples ---
            ['doc' => 'V', 'num' => '1287450',  'name' => 'PABLO FERNANDEZ', 'unit' => 'J-03', 'start' => '27/06/2022', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa Deportiva'],
            ['doc' => 'V', 'num' => '17698413', 'name' => 'ELIAS ARMANDO GUADARRAMA DELGADO', 'unit' => 'J-04', 'start' => '14/12/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa / Zapatos'],
            ['doc' => 'V', 'num' => '20589357', 'name' => 'GABRIEL YERMAIN GALBAN PEREZ', 'unit' => 'J-05', 'start' => '13/12/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '14534677', 'name' => 'CLEISYMAR MONTILLA LEON', 'unit' => 'J-06', 'start' => '15/02/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '13532308', 'name' => 'RAUL ENRIQUE GONZALEZ GARCIAL', 'unit' => 'J-07', 'start' => '24/05/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'CASABE'],
            ['doc' => 'V', 'num' => '6188413',  'name' => 'VIRGINIA NORA RODRIGUEZ MOROS', 'unit' => 'J-08', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '9966862',  'name' => 'ADELINA EVA NUÑEZ', 'unit' => 'J-09', 'start' => '07/09/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Zapatos'],
            ['doc' => 'V', 'num' => '9969617',  'name' => 'KATTY CAROLINA ROJAS PEREZ', 'unit' => 'J-10', 'start' => '03/04/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '16027110', 'name' => 'MELVIN DAVID ROJAS RENDON', 'unit' => 'J-11', 'start' => '02/09/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa Intima'],
            ['doc' => 'V', 'num' => '5889312',  'name' => 'MARITZA RENDON ROMERO', 'unit' => 'J-12', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa Intima'],

            // --- Nuevos: Multi-local especial ---
            // MARIA ANDREINA MACHADO RODRIGUEZ: J-02 y BM-23 (09/01/2015)
            ['doc' => 'V', 'num' => '13832262', 'name' => 'MARIA ANDREINA MACHADO RODRIGUEZ', 'unit' => 'J-02', 'start' => '09/01/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa', 'ml' => true],
            ['doc' => 'V', 'num' => '13832262', 'name' => 'MARIA ANDREINA MACHADO RODRIGUEZ', 'unit' => 'BM-23', 'start' => '09/01/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa', 'ml' => true],

            // --- Nuevos: AM - simples ---
            ['doc' => 'V', 'num' => '13535411', 'name' => 'LILIANA ESPERANZA GIL GUATACHEZ', 'unit' => 'AM-01', 'start' => '04/11/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '13535411', 'name' => 'LILIANA ESPERANZA GIL GUATACHEZ', 'unit' => 'AM-02', 'start' => '25/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '13535411', 'name' => 'LILIANA ESPERANZA GIL GUATACHEZ', 'unit' => 'AM-03', 'start' => '25/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '13535411', 'name' => 'LILIANA ESPERANZA GIL GUATACHEZ', 'unit' => 'AM-04', 'start' => '28/02/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '13535411', 'name' => 'LILIANA ESPERANZA GIL GUATACHEZ', 'unit' => 'AM-05', 'start' => '09/08/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],

            // --- Nuevos: BM - simples ---
            ['doc' => 'V', 'num' => '24331771', 'name' => 'ANJI ANGELICA GRATEROL SERVERA', 'unit' => 'BM-10', 'start' => '24/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '24331771', 'name' => 'ANJI ANGELICA GRATEROL SERVERA', 'unit' => 'BM-11', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            // RECUPERADO jul-22
            ['doc' => 'V', 'num' => '12748824', 'name' => 'VICTOR MANUEL SEVILLA MARIN', 'unit' => 'BM-17', 'start' => '17/04/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true, 'additional' => [
                ['doc' => 'V', 'num' => '20114853', 'name' => 'GLEBYS MARKENIS GARCIA DE SEVILLA'],
            ]],
            ['doc' => 'V', 'num' => '20675598', 'name' => 'RENNIER DAVID SUNIAGA', 'unit' => 'BM-18', 'start' => '28/01/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            // RECUPERADO may-23 - BM-19 y BM-21 (JOSE FRANCISCO DIAZ MORAN)
            ['doc' => 'V', 'num' => '12748824', 'name' => 'VICTOR MANUEL SEVILLA MARIN', 'unit' => 'BM-19', 'start' => '17/04/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true, 'additional' => [
                ['doc' => 'V', 'num' => '20114853', 'name' => 'GLEBYS MARKENIS GARCIA DE SEVILLA'],
            ]],
            ['doc' => 'V', 'num' => '26711528', 'name' => 'MARCO JOSE AQUINO', 'unit' => 'BM-20', 'start' => '09/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '6516284',  'name' => 'JIMMY WILL LUISE DI GERONIMO', 'unit' => 'BM-22', 'start' => '04/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],

            // --- Nuevos: CM - simples ---
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'CM-11', 'start' => '21/09/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '15049249', 'name' => 'MANUEL ABREU CONGALVE', 'unit' => 'CM-17', 'start' => '25/09/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '13478396', 'name' => 'RAQUEL SARAI RIVERO', 'unit' => 'CM-18', 'start' => '27/11/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '25740407', 'name' => 'ORIANA NOSLE TORRES', 'unit' => 'CM-19', 'start' => '27/11/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '15870303', 'name' => 'GUSTAVO JOSE ARRAIZ VALBUENA', 'unit' => 'CM-29', 'start' => '09/02/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],

            // --- Nuevos: DM - simples ---
            ['doc' => 'V', 'num' => '4461422',  'name' => 'CECILIA CRISTINA ARCAY PARRA', 'unit' => 'DM-15', 'start' => '22/02/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '4461422',  'name' => 'CECILIA CRISTINA ARCAY PARRA', 'unit' => 'DM-16', 'start' => '14/06/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'DM-17', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'DM-18', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'DM-19', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'DM-20', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '14484007', 'name' => 'EMILTER JOSEFINA BETANCOURT GARCIA', 'unit' => 'DM-21', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'DM-22', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas'],

            // --- Nuevos: FL - simples ---
            ['doc' => 'V', 'num' => '14855931', 'name' => 'LUZ MARIA VELIZ', 'unit' => 'FL-01', 'start' => '01/11/2010', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '6427680',  'name' => 'ROSA ISABEL ATOPO', 'unit' => 'FL-02', 'start' => '02/12/2008', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'E', 'num' => '81248897', 'name' => 'JOSE EDUARDO DA ENCARNECAO NUÑEZ', 'unit' => 'FL-03', 'start' => '04/08/2009', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '10167008', 'name' => 'LUIS RAMON DUQUE PARRA', 'unit' => 'FL-04', 'start' => '09/10/2018', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '19348844', 'name' => 'DOUGLAS CLEMENTE TORREALBA CORDERO', 'unit' => 'FL-07', 'start' => '09/02/2022', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '6730785',  'name' => 'MAIRA BEATRIZ FARIAS SUAREZ', 'unit' => 'FL-08', 'start' => '10/06/2021', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '15198619', 'name' => 'GLAYDE MILAGROS CONDE GONZALEZ', 'unit' => 'FL-09', 'start' => '24/05/2018', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '3667892',  'name' => 'EVA ELIZABETH RAMOS RAMIREZ', 'unit' => 'FL-10', 'start' => '28/08/2006', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '15198619', 'name' => 'GLAYDE MILAGROS CONDE GONZALEZ', 'unit' => 'FL-11', 'start' => '24/05/2018', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],
            ['doc' => 'V', 'num' => '3829820',  'name' => 'JUANA COROMOTO DELGADO COLINA', 'unit' => 'FL-12', 'start' => '16/02/2009', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Flores'],

            // --- Nuevos: FL - multi-local ---
            // MARIA SALOME GONCALVES DE ROCHA: FL-05..FL-06 (25/10/2017)
            ['doc' => 'E', 'num' => '81772581', 'name' => 'MARIA SALOME GONCALVES DE ROCHA', 'unit' => 'FL-05', 'start' => '25/10/2017', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Plantas', 'ml' => true],
            ['doc' => 'E', 'num' => '81772581', 'name' => 'MARIA SALOME GONCALVES DE ROCHA', 'unit' => 'FL-06', 'start' => '25/10/2017', 'end' => 'INDEFINIDO', 'price' => 40.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Plantas', 'ml' => true],

            // --- Nuevos: PLZ (Plaza) ---
            ['doc' => 'V', 'num' => '23610287', 'name' => 'FELIX CRUZ SHUIN', 'unit' => 'PLZ-01', 'start' => '01/01/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Bebidas / Batidos / Chichas'],
            ['doc' => 'J', 'num' => '50727178', 'name' => 'OBLEA FOODS CA', 'unit' => 'Kiosco 11', 'start' => '01/10/2025', 'end' => '01/10/2026', 'price' => 47.0, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oleas'],

            // --- Nuevos: K - simples ---
            // RECUPERADO PERO CANCELA ene-25
            ['doc' => 'V', 'num' => '4975115',  'name' => 'RAIZA MARVELIS RIVAS GIL', 'unit' => 'K-01', 'start' => '04/02/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Corte, Costura y Confeccion de Ropa', 'additional' => [
                ['doc' => 'V', 'num' => '6866117', 'name' => 'ELAYNE LISBETH RIVAS GIL'],
            ]],
            ['doc' => 'E', 'num' => '81115646', 'name' => 'MARIA LUISA LEON DE CACERES', 'unit' => 'K-02', 'start' => '21/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '25787871', 'name' => 'KENNERLING RUIZ', 'unit' => 'K-03', 'start' => '18/10/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '27916588', 'name' => 'ANA GABRIELA ARRAIZ GIL', 'unit' => 'K-04', 'start' => '20/12/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],

            // --- Nuevos: L - simples ---
            ['doc' => 'V', 'num' => '3189362',  'name' => 'FRANCISCO JAVIER GANAU PALOMERO', 'unit' => 'L-01', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aceitunas'],
            ['doc' => 'E', 'num' => '81302102', 'name' => 'VERA LUCIA LUCAS DE OLIVEIRA', 'unit' => 'L-02', 'start' => '19/12/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aceitunas'],
            ['doc' => 'V', 'num' => '13478472', 'name' => 'JOSE GREGORIO JARDIN PESTANA', 'unit' => 'L-03', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Aceitunas'],
            ['doc' => 'E', 'num' => '81202126', 'name' => 'JOSE LUIS DA SILVA BARRETO', 'unit' => 'L-04', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],

            // --- Nuevos: GM - simples ---
            ['doc' => 'E', 'num' => '81394905', 'name' => 'GLADYS PAULINO', 'unit' => 'GM-03', 'start' => '18/08/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida'],
            ['doc' => 'V', 'num' => '16411464', 'name' => 'TULIA INES BELTRAN MEDINA', 'unit' => 'GM-04', 'start' => '05/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Bebidas / refrescos'],
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'GM-04A', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Lacteos'],
            ['doc' => 'V', 'num' => '14032607', 'name' => 'HERBIN ALBEIRO VALENCIA MORENO', 'unit' => 'GM-05', 'start' => '30/07/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '25957554', 'name' => 'YOSANDRY KATERINE RUIZ LINARES', 'unit' => 'GM-12', 'start' => '13/08/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '11566433', 'name' => 'FELICIA DEL CARMEN ROSELLON AMARIS', 'unit' => 'GM-13', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '4490473',  'name' => 'JOSE RAFAEL AVILA', 'unit' => 'GM-14', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '5594370',  'name' => 'LUISA JOSEFINA GARCIA', 'unit' => 'GM-15', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '8509183',  'name' => 'LUIS DARIO RODRIGUEZ', 'unit' => 'GM-16', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '23685955', 'name' => 'ANA MARIA CAMPO DE VILLAFAÑE', 'unit' => 'GM-17', 'start' => '24/08/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '6692558',  'name' => 'KARELY MERCEDES ARTEAGA GONZALEZ', 'unit' => 'GM-18', 'start' => '23/11/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '6692558',  'name' => 'KARELY MERCEDES ARTEAGA GONZALEZ', 'unit' => 'GM-19', 'start' => '23/11/2010', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            // RECUPERADO jul-24
            // ['doc' => 'V', 'num' => '12910088', 'name' => 'KARINA REGINATO MUÑOZ', 'unit' => 'GM-20', 'start' => '26/09/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '14484948', 'name' => 'ANDREINA MARIA BRITO NOVAIS', 'unit' => 'GM-21', 'start' => '15/05/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '6818127',  'name' => 'MIRIAM LILIANA DI FABIO LOPEZ', 'unit' => 'GM-22', 'start' => '13/08/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Lacteos'],
            ['doc' => 'V', 'num' => '5534257',  'name' => 'ROSSANA MARIA DI FABIO LOPEZ', 'unit' => 'GM-23', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'E', 'num' => '1069776',  'name' => 'ADELACIA COTE CARVAJAL', 'unit' => 'GM-24', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '25957554', 'name' => 'YOSANDRY KATERINE RUIZ', 'unit' => 'GM-25', 'start' => '29/04/2024', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'E', 'num' => '81246600', 'name' => 'VASCO PINTO GOMES', 'unit' => 'GM-26', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '9149093',  'name' => 'MARLENE CARVAJAL BOADA', 'unit' => 'GM-27', 'start' => '03/12/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Artesanía', 'additional' => [
                ['doc' => 'V', 'num' => '9463526', 'name' => 'BELKIS COROMOTO CARVAJAL BOADA'],
            ]],
            ['doc' => 'V', 'num' => '13337124', 'name' => 'MARIELA RODRIGUEZ DE TORRES', 'unit' => 'GM-28', 'start' => '22/06/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '15048567', 'name' => 'DAYANA COROMOTO RUJANO GUATACHEZ', 'unit' => 'GM-29', 'start' => '08/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'additional' => [
                ['doc' => 'V', 'num' => '26159039', 'name' => 'DAIBELYS DEL CARMEN OCHOA RUJANO'],
            ]],

            // --- Nuevos: GM - multi-local ---
            // ELVIRA JOSEFINA TORRES DE TRITTO: GM-01..GM-02 (28/10/2008)
            ['doc' => 'V', 'num' => '6124677',  'name' => 'ELVIRA JOSEFINA TORRES DE TRITTO', 'unit' => 'GM-01', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida', 'ml' => true],
            ['doc' => 'V', 'num' => '6124677',  'name' => 'ELVIRA JOSEFINA TORRES DE TRITTO', 'unit' => 'GM-02', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida', 'ml' => true],

            // MARIA VICTORIA LOZADA RUIZ: GM-06..GM-07 (27/10/2008)
            ['doc' => 'V', 'num' => '9064272',  'name' => 'MARIA VICTORIA LOZADA RUIZ', 'unit' => 'GM-06', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],
            ['doc' => 'V', 'num' => '9064272',  'name' => 'MARIA VICTORIA LOZADA RUIZ', 'unit' => 'GM-07', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],

            // YONATHAN EDUARDO MENDOZA LOZADA: GM-08..GM-09 (14/07/2016)
            ['doc' => 'V', 'num' => '20130612', 'name' => 'YONATHAN EDUARDO MENDOZA LOZADA', 'unit' => 'GM-08', 'start' => '14/07/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],
            ['doc' => 'V', 'num' => '20130612', 'name' => 'YONATHAN EDUARDO MENDOZA LOZADA', 'unit' => 'GM-09', 'start' => '14/07/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],

            // YOHANA COROMOTO MARTIN RODRIGUEZ: GM-10..GM-11 (03/12/2021)
            ['doc' => 'V', 'num' => '11231626', 'name' => 'YOHANA COROMOTO MARTIN RODRIGUEZ', 'unit' => 'GM-10', 'start' => '03/12/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],
            ['doc' => 'V', 'num' => '11231626', 'name' => 'YOHANA COROMOTO MARTIN RODRIGUEZ', 'unit' => 'GM-11', 'start' => '03/12/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],

            // --- Nuevos: HM - simples ---
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'HM-01', 'start' => '16/11/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida'],
            ['doc' => 'V', 'num' => '18313422', 'name' => 'LUIS ISAAC GALLARDO RUIZ', 'unit' => 'HM-02', 'start' => '21/09/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida', 'additional' => [
                ['doc' => 'V', 'num' => '13067898', 'name' => 'MARJORIE GALLARDO RUIZ'],
                ['doc' => 'V', 'num' => '15314887', 'name' => 'MAIKEL GALLARDO RUIZ'],
            ]],
            ['doc' => 'V', 'num' => '6220408',  'name' => 'CELIA FERNANDEZ GOMIS', 'unit' => 'HM-03', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '6027167',  'name' => 'MIRNA COROMOTO DELGADO', 'unit' => 'HM-03A', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '6283401',  'name' => 'CARMEN MERCEDES PEREZ GONZALEZ', 'unit' => 'HM-04', 'start' => '11/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '22030488', 'name' => 'EDER FLORIAN RUIZ', 'unit' => 'HM-07', 'start' => '23/10/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '21025819', 'name' => 'WEN LIN', 'unit' => 'HM-08', 'start' => '23/09/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'additional' => [
                ['doc' => 'V', 'num' => '8226824', 'name' => 'ESTHER AMPARO CHACON DE FIGUEIRA'],
            ]],
            ['doc' => 'V', 'num' => '12058191', 'name' => 'CONSUELO TRAZONA CALDERON', 'unit' => 'HM-09', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres'],
            ['doc' => 'V', 'num' => '11821895', 'name' => 'MARIA DE LOS ANGELES RIVAS GONZALEZ', 'unit' => 'HM-10', 'start' => '24/11/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'additional' => [
                ['doc' => 'V', 'num' => '14059519', 'name' => 'MANUEL RIVAS GARCIA'],
            ]],
            ['doc' => 'V', 'num' => '5001396',  'name' => 'LESBIA TIBISAY ARIAS PEREZ', 'unit' => 'HM-11', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla'],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS ', 'unit' => 'HM-13', 'start' => '16/05/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Papeleria y Accesorios'],
            ['doc' => 'V', 'num' => '7348285',  'name' => 'LENNY PRICETT MARTINEZ EGAS', 'unit' => 'HM-14', 'start' => '13/05/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'E', 'num' => '829349',   'name' => 'MARIA AUGUSTA DOMINGUES DE ANDRADE', 'unit' => 'HM-15', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],
            ['doc' => 'V', 'num' => '18745449', 'name' => 'CARMEN VILORIA CARRASQUINA', 'unit' => 'HM-16', 'start' => '08/09/2022', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Artesanía'],
            ['doc' => 'V', 'num' => '10865205', 'name' => 'JANETH JOSEFINA MONTOYA', 'unit' => 'HM-17', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla'],
            ['doc' => 'V', 'num' => '6279725',  'name' => 'GREGORIO ALBERTO CAMACHO GARCIA', 'unit' => 'HM-18', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Perfumería / belleza'],
            // RECUPERADO sep-24
            // ['doc' => 'V', 'num' => '11471357', 'name' => 'ANA GABRIELA MARIN HERRERA', 'unit' => 'HM-19', 'start' => '03/07/2009', 'end' => 'INDEFINIDO', 'rubro' => 'Productos naturales'],
            ['doc' => 'V', 'num' => '5144163',  'name' => 'LUIS RAMON ALONZO RAMIREZ', 'unit' => 'HM-21', 'start' => '28/02/2003', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla'],
            ['doc' => 'V', 'num' => '4612073',  'name' => 'ITALO RAFAEL ACUÑA BALBAS', 'unit' => 'HM-22', 'start' => '10/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa / Quincalla'],
            ['doc' => 'V', 'num' => '4612073',  'name' => 'ITALO RAFAEL ACUÑA BALBAS', 'unit' => 'HM-23', 'start' => '18/02/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Comida', 'additional' => [
                ['doc' => 'V', 'num' => '4238882', 'name' => 'SONIA BENAVIDES MORALES'],
                ['doc' => 'V', 'num' => '5193034', 'name' => 'LUIS MORALES MORALES'],
            ]],
            ['doc' => 'V', 'num' => '10788772', 'name' => 'NANCY BEATRIZ VAZQUEZ GARCIA', 'unit' => 'HM-24', 'start' => '09/07/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Reposteria'],
            ['doc' => 'V', 'num' => '6868245',  'name' => 'HORACIO ASUNCION FREITAS BARROS', 'unit' => 'HM-25', 'start' => '19/08/2021', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos Nacionales e Importados'],
            // Nuevo contrato HM-26 - Veronica Sofia Moreno (ene-2026)
            ['doc' => 'V', 'num' => '32299771', 'name' => 'VERONICA SOFIA MORENO', 'unit' => 'HM-26', 'start' => '01/01/2026', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Lencería'],
            ['doc' => 'V', 'num' => '16749059', 'name' => 'JOSE ANTONIO GOMEZ PEREIRA', 'unit' => 'HM-27', 'start' => '13/07/2011', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa'],

            // --- Nuevos: HM - multi-local ---
            // CESAR HUGO ARTEAGA GONZALEZ: HM-05..HM-06 (18/10/2020)
            ['doc' => 'V', 'num' => '7661787',  'name' => 'CESAR HUGO ARTEAGA GONZALEZ', 'unit' => 'HM-05', 'start' => '18/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Lacteos', 'ml' => true],
            ['doc' => 'V', 'num' => '7661787',  'name' => 'CESAR HUGO ARTEAGA GONZALEZ', 'unit' => 'HM-06', 'start' => '18/10/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Lacteos', 'ml' => true],

            // ANJI ANGELICA GRATEROL SERVERA: HM-12A..HM-12B (24/09/2020)
            ['doc' => 'V', 'num' => '24331771', 'name' => 'ANJI ANGELICA GRATEROL SERVERA', 'unit' => 'HM-12A', 'start' => '24/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa', 'ml' => true],
            ['doc' => 'V', 'num' => '24331771', 'name' => 'ANJI ANGELICA GRATEROL SERVERA', 'unit' => 'HM-12B', 'start' => '24/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Ropa', 'ml' => true],

            // ALCIRA OSPINO BLANCO: HM-20..HM-20A (29/11/2012)
            ['doc' => 'V', 'num' => '19829622', 'name' => 'ALCIRA OSPINO BLANCO', 'unit' => 'HM-20', 'start' => '29/11/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],
            ['doc' => 'V', 'num' => '19829622', 'name' => 'ALCIRA OSPINO BLANCO', 'unit' => 'HM-20A', 'start' => '29/11/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Tomates / Pimenton', 'ml' => true],

            // --- Nuevos: AM - multi-local ---
            // ROSINA ISABEL PIMENTEL DIAZ: AM-06..AM-08 (13/07/2017)
            ['doc' => 'V', 'num' => '23642287', 'name' => 'ROSINA ISABEL PIMENTEL DIAZ', 'unit' => 'AM-06', 'start' => '13/07/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '23642287', 'name' => 'ROSINA ISABEL PIMENTEL DIAZ', 'unit' => 'AM-07', 'start' => '13/07/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '23642287', 'name' => 'ROSINA ISABEL PIMENTEL DIAZ', 'unit' => 'AM-08', 'start' => '13/07/2017', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // ILVA MORA DE DIAZ: AM-09..AM-12 (24/10/2008, 10/08/2016)
            ['doc' => 'V', 'num' => '12072586', 'name' => 'ILVA MORA DE DIAZ', 'unit' => 'AM-09', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12072586', 'name' => 'ILVA MORA DE DIAZ', 'unit' => 'AM-10', 'start' => '24/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12072586', 'name' => 'ILVA MORA DE DIAZ', 'unit' => 'AM-11', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12072586', 'name' => 'ILVA MORA DE DIAZ', 'unit' => 'AM-12', 'start' => '10/08/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // RECUPERADO dic-21 - AM-13..AM-17 (28/04/2025)
            ['doc' => 'V', 'num' => '10471368', 'name' => 'JOSE MANUEL', 'unit' => 'AM-13', 'start' => '28/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cachapas', 'ml' => true],
            ['doc' => 'V', 'num' => '10471368', 'name' => 'JOSE MANUEL', 'unit' => 'AM-14', 'start' => '28/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cachapas', 'ml' => true],
            ['doc' => 'V', 'num' => '10471368', 'name' => 'JOSE MANUEL', 'unit' => 'AM-15', 'start' => '28/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cachapas', 'ml' => true],
            ['doc' => 'V', 'num' => '10471368', 'name' => 'JOSE MANUEL', 'unit' => 'AM-16', 'start' => '28/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cachapas', 'ml' => true],
            ['doc' => 'V', 'num' => '10471368', 'name' => 'JOSE MANUEL', 'unit' => 'AM-17', 'start' => '28/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Cachapas', 'ml' => true],

            // JOSE FRANCISCO DIAZ MORAN: AM-18..AM-21' (16/04/2018)
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'AM-18', 'start' => '16/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'AM-19', 'start' => '16/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'AM-20', 'start' => '16/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'AM-21', 'start' => '16/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '4975803',  'name' => 'JOSE FRANCISCO DIAZ MORAN', 'unit' => 'AM-21"', 'start' => '16/04/2018', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // --- Nuevos: BM - multi-local ---
            // BRIGIDA DE FREITAS KAATZ: BM-01..BM-02 (30/10/2008)
            ['doc' => 'V', 'num' => '14674071', 'name' => 'BRIGIDA DE FREITAS KAATZ', 'unit' => 'BM-01', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '14674071', 'name' => 'BRIGIDA DE FREITAS KAATZ', 'unit' => 'BM-02', 'start' => '30/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // JOSE IGNACIO DE FREITAS RODRIGUES: BM-03..BM-07 (20/11/2008)
            ['doc' => 'V', 'num' => '12415176', 'name' => 'JOSE IGNACIO DE FREITAS RODRIGUES', 'unit' => 'BM-03', 'start' => '20/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12415176', 'name' => 'JOSE IGNACIO DE FREITAS RODRIGUES', 'unit' => 'BM-04', 'start' => '20/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12415176', 'name' => 'JOSE IGNACIO DE FREITAS RODRIGUES', 'unit' => 'BM-05', 'start' => '20/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12415176', 'name' => 'JOSE IGNACIO DE FREITAS RODRIGUES', 'unit' => 'BM-06', 'start' => '20/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '12415176', 'name' => 'JOSE IGNACIO DE FREITAS RODRIGUES', 'unit' => 'BM-07', 'start' => '20/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // RECUPERADO - IAMMCH: BM-08..BM-09 (27/10/2008)
            // ['doc' => 'V', 'num' => '2127024',  'name' => 'JUAN DE JESUS MENDOZA', 'unit' => 'BM-08', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            // ['doc' => 'V', 'num' => '2127024',  'name' => 'JUAN DE JESUS MENDOZA', 'unit' => 'BM-09', 'start' => '27/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // JUAN FRANCISCO DIAZ PEÑA: BM-12..BM-14 (23/05/2016)
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ PEÑA', 'unit' => 'BM-12', 'start' => '23/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ PEÑA', 'unit' => 'BM-13', 'start' => '23/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ PEÑA', 'unit' => 'BM-14', 'start' => '23/05/2016', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // MARCO JOSE AQUINO: BM-15..BM-16 (07/08/2015)
            ['doc' => 'V', 'num' => '26711528', 'name' => 'MARCO JOSE AQUINO', 'unit' => 'BM-15', 'start' => '07/08/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '26711528', 'name' => 'MARCO JOSE AQUINO', 'unit' => 'BM-16', 'start' => '07/08/2015', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // ANTONIO DANIEL AVE GIL: BM-21..BM-21' (17/04/2023)
            ['doc' => 'V', 'num' => '12748824', 'name' => 'VICTOR MANUEL SEVILLA MARIN', 'unit' => 'BM-21', 'start' => '17/04/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true, 'additional' => [
                ['doc' => 'V', 'num' => '20114853', 'name' => 'GLEBYS MARKENIS GARCIA DE SEVILLA'],
            ]],
            ['doc' => 'V', 'num' => '12748824', 'name' => 'VICTOR MANUEL SEVILLA MARIN', 'unit' => 'BM-21"', 'start' => '17/04/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true, 'additional' => [
                ['doc' => 'V', 'num' => '20114853', 'name' => 'GLEBYS MARKENIS GARCIA DE SEVILLA'],
            ]],

            // --- Nuevos: CM - multi-local ---
            // DAYANA COROMOTO RUJANO GUATACHEZ: CM-01..CM-34 (28/10/2008)
            ['doc' => 'V', 'num' => '15048567', 'name' => 'DAYANA COROMOTO RUJANO GUATACHEZ', 'unit' => 'CM-01', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '15048567', 'name' => 'DAYANA COROMOTO RUJANO GUATACHEZ', 'unit' => 'CM-34', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // RECUPERADO abr-24 - CM-02..CM-03 (21/10/2019)
            ['doc' => 'V', 'num' => '5685523', 'name' => 'MARIA MAYELA', 'unit' => 'CM-02', 'start' => '21/10/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Delicatesses y Productos Importados', 'ml' => true],
            ['doc' => 'V', 'num' => '5685523', 'name' => 'MARIA MAYELA', 'unit' => 'CM-03', 'start' => '21/10/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Delicatesses y Productos Importados', 'ml' => true],

            // RAYGEL FRISMAN MORENO RNDON: CM-04..CM-05 (09/05/2025)
            ['doc' => 'V', 'num' => '23711898', 'name' => 'RAYGEL FRISMAN MORENO RNDON', 'unit' => 'CM-04', 'start' => '09/05/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos Lacteos y Postres', 'ml' => true],
            ['doc' => 'V', 'num' => '23711898', 'name' => 'RAYGEL FRISMAN MORENO RNDON', 'unit' => 'CM-05', 'start' => '09/05/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos Lacteos y Postres', 'ml' => true],

            // JOSEFINA RIGGI GIROTO: CM-06..CM-06' (21/10/2013)
            ['doc' => 'V', 'num' => '5534639',  'name' => 'JOSEFINA RIGGI GIROTO', 'unit' => 'CM-06', 'start' => '21/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'ml' => true],
            ['doc' => 'V', 'num' => '5534639',  'name' => 'JOSEFINA RIGGI GIROTO', 'unit' => 'CM-06"', 'start' => '21/10/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'ml' => true],

            // ANTONIO LUCRECIO BREINDEMBACH GERIK: CM-07..CM-08 (28/10/2008)
            ['doc' => 'V', 'num' => '3123326',  'name' => 'ANTONIO LUCRECIO BREINDEMBACH GERIK', 'unit' => 'CM-07', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '3123326',  'name' => 'ANTONIO LUCRECIO BREINDEMBACH GERIK', 'unit' => 'CM-08', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // LETICIA MORENO DE VALENCIA: CM-09..CM-10 (30/08/2013)
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'CM-09', 'start' => '30/08/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'CM-09"', 'start' => '30/08/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'CM-10', 'start' => '30/08/2013', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // RECUPERADO - IAMMCH: CM-12..CM-14 (29/08/2014)
            // ['doc' => 'V', 'num' => '6941647',  'name' => 'MARIA ISABEL FERNANDEZ CASANOVA', 'unit' => 'CM-12', 'start' => '29/08/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            // ['doc' => 'V', 'num' => '6941647',  'name' => 'MARIA ISABEL FERNANDEZ CASANOVA', 'unit' => 'CM-13', 'start' => '29/08/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            // ['doc' => 'V', 'num' => '6941647',  'name' => 'MARIA ISABEL FERNANDEZ CASANOVA', 'unit' => 'CM-14', 'start' => '29/08/2014', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // MARIA GILDA DOS SANTOS DE SOUSA: CM-15..CM-16 (01/02/2019)
            ['doc' => 'E', 'num' => '971578',   'name' => 'MARIA GILDA DOS SANTOS DE SOUSA', 'unit' => 'CM-15', 'start' => '01/02/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '971578',   'name' => 'MARIA GILDA DOS SANTOS DE SOUSA', 'unit' => 'CM-16', 'start' => '01/02/2019', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // ANGELO LOMASCOLO: CM-20..CM-22 (12/11/2008)
            ['doc' => 'V', 'num' => '694488',   'name' => 'ANGELO LOMASCOLO', 'unit' => 'CM-20', 'start' => '12/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '694488',   'name' => 'ANGELO LOMASCOLO', 'unit' => 'CM-21', 'start' => '12/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '694488',   'name' => 'ANGELO LOMASCOLO', 'unit' => 'CM-22', 'start' => '12/11/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // SILVIO LUIS LOMASCOLO: CM-23..CM-24 (28/10/2008)
            ['doc' => 'V', 'num' => '9098109',  'name' => 'SILVIO LUIS LOMASCOLO', 'unit' => 'CM-23', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '9098109',  'name' => 'SILVIO LUIS LOMASCOLO', 'unit' => 'CM-24', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // PATROCINIA DEL PILAR ECHEVERRIA DE MARTINEZ: CM-25..CM-26 (28/10/2008)
            ['doc' => 'V', 'num' => '17297775', 'name' => 'PATROCINIA DEL PILAR ECHEVERRIA DE MARTINEZ', 'unit' => 'CM-25', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '17297775', 'name' => 'PATROCINIA DEL PILAR ECHEVERRIA DE MARTINEZ', 'unit' => 'CM-26', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // LUIS GUSTAVO ARRAIZ GIL: CM-27..CM-28 (06/11/2020)
            ['doc' => 'V', 'num' => '22393156', 'name' => 'LUIS GUSTAVO ARRAIZ GIL', 'unit' => 'CM-27', 'start' => '06/11/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '22393156', 'name' => 'LUIS GUSTAVO ARRAIZ GIL', 'unit' => 'CM-28', 'start' => '06/11/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // GUSTAVO JOSE ARRAIZ VALBUENA: CM-29..CM-31 (09/02/2012, 28/10/2008)
            ['doc' => 'V', 'num' => '15870303', 'name' => 'GUSTAVO JOSE ARRAIZ VALBUENA', 'unit' => 'CM-29', 'start' => '09/02/2012', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '15870303', 'name' => 'GUSTAVO JOSE ARRAIZ VALBUENA', 'unit' => 'CM-30', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '15870303', 'name' => 'GUSTAVO JOSE ARRAIZ VALBUENA', 'unit' => 'CM-31', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // ANA GABRIELA ARRAIZ GIL: CM-32..CM-33 (06/11/2020)
            ['doc' => 'V', 'num' => '27916588', 'name' => 'ANA GABRIELA ARRAIZ GIL', 'unit' => 'CM-32', 'start' => '06/11/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '27916588', 'name' => 'ANA GABRIELA ARRAIZ GIL', 'unit' => 'CM-33', 'start' => '06/11/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // --- Nuevos: DM - multi-local ---
            // RECUPERADO feb-24 - DM-03..DM-04
            ['doc' => 'V', 'num' => '3229883',  'name' => 'ANGELINA RISQUEZ', 'unit' => 'DM-03', 'start' => '21/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos y Alimentos Procesados', 'ml' => true],
            ['doc' => 'V', 'num' => '3229883',  'name' => 'ANGELINA RISQUEZ', 'unit' => 'DM-04', 'start' => '21/04/2025', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Productos y Alimentos Procesados', 'ml' => true],

            // MARIA ELENA SANABRIA DE MOYA: DM-05..DM-06 (28/10/2008)
            ['doc' => 'V', 'num' => '4813967',  'name' => 'MARIA ELENA SANABRIA DE MOYA', 'unit' => 'DM-05', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'ml' => true],
            ['doc' => 'V', 'num' => '4813967',  'name' => 'MARIA ELENA SANABRIA DE MOYA', 'unit' => 'DM-06', 'start' => '28/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'ml' => true],

            // JOSE MIGUEL ALVAREZ MUJICA: DM-07..DM-08 (25/09/2020)
            ['doc' => 'V', 'num' => '18154140', 'name' => 'JOSE MIGUEL ALVAREZ MUJICA', 'unit' => 'DM-07', 'start' => '25/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],
            ['doc' => 'V', 'num' => '18154140', 'name' => 'JOSE MIGUEL ALVAREZ MUJICA', 'unit' => 'DM-08', 'start' => '25/09/2020', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Viveres', 'ml' => true],

            // BERKIS ISABEL SANCHEZ DE PEREZ: DM-09..DM-10 (29/10/2008)
            ['doc' => 'V', 'num' => '10071769', 'name' => 'BERKIS ISABEL SANCHEZ DE PEREZ', 'unit' => 'DM-09', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'ml' => true],
            ['doc' => 'V', 'num' => '10071769', 'name' => 'BERKIS ISABEL SANCHEZ DE PEREZ', 'unit' => 'DM-10', 'start' => '29/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Quincalla', 'ml' => true],

            // RODOLFO SANCHEZ: DM-11..DM-14 (14/04/2009)
            ['doc' => 'V', 'num' => '6512735',  'name' => 'RODOLFO SANCHEZ', 'unit' => 'DM-11', 'start' => '14/04/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '6512735',  'name' => 'RODOLFO SANCHEZ', 'unit' => 'DM-12', 'start' => '14/04/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '6512735',  'name' => 'RODOLFO SANCHEZ', 'unit' => 'DM-13', 'start' => '14/04/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '6512735',  'name' => 'RODOLFO SANCHEZ', 'unit' => 'DM-14', 'start' => '14/04/2009', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // NORBELYS ZERPA: DM-23..DM-27 (25/10/2008)
            ['doc' => 'V', 'num' => '13009519', 'name' => 'NORBELYS ZERPA', 'unit' => 'DM-23', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '13009519', 'name' => 'NORBELYS ZERPA', 'unit' => 'DM-24', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '13009519', 'name' => 'NORBELYS ZERPA', 'unit' => 'DM-25', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '13009519', 'name' => 'NORBELYS ZERPA', 'unit' => 'DM-26', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'V', 'num' => '13009519', 'name' => 'NORBELYS ZERPA', 'unit' => 'DM-27', 'start' => '25/10/2008', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // RICARDO GONZALEZ RAMIREZ: DM-28..DM-34 (28/09/2023)
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-28', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-29', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-30', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-31', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-32', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-33', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'DM-34', 'start' => '28/09/2023', 'end' => 'INDEFINIDO', 'type' => 'CONV', 'modality' => 'M2', 'rubro' => 'Frutas', 'ml' => true],

            // --- Nuevos: Locales Comerciales ---
            ['doc' => 'J', 'num' => '50244208',  'name' => 'VESCO SUMINISTROS C.A', 'unit' => 'LOCAL 6', 'start' => '01/11/2024', 'end' => '30/11/2025', 'price' => 110.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Vinatería'],
            ['doc' => 'J', 'num' => '50244208',  'name' => 'VESCO SUMINISTROS C.A', 'unit' => 'LOCAL 7', 'start' => '15/08/2024', 'end' => '30/11/2025', 'price' => 370.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '50244208',  'name' => 'VESCO SUMINISTROS C.A', 'unit' => 'LOCAL 8', 'start' => '15/08/2024', 'end' => '30/11/2025', 'price' => 370.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '501668698', 'name' => 'GRUPO CHILANGO', 'unit' => 'LOCAL 9', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 370.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '501668698', 'name' => 'GRUPO CHILANGO', 'unit' => 'LOCAL 10', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 960.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '501530270', 'name' => 'INVERSIONES AZOTEA GOURMET C.A.', 'unit' => 'LOCAL TERRAZA', 'start' => '15/08/2024', 'end' => '30/11/2025', 'price' => 1700.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '50244208',  'name' => 'VESCO SUMINISTROS C.A', 'unit' => 'OFICINA TERRAZA', 'start' => '03/07/2024', 'end' => '30/11/2025', 'price' => 238.43, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'J', 'num' => '413153815', 'name' => 'GLOBAL FOOD 20-05 C.A.', 'unit' => 'LOCAL 6', 'start' => '01/12/2025', 'end' => '01/12/2027', 'price' => 600.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Vinatería'],
            ['doc' => 'J', 'num' => '413153815', 'name' => 'GLOBAL FOOD 20-05 C.A.', 'unit' => 'LOCAL 7', 'start' => '01/12/2025', 'end' => '01/12/2027', 'price' => 250.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '413153815', 'name' => 'GLOBAL FOOD 20-05 C.A.', 'unit' => 'LOCAL 8', 'start' => '01/12/2025', 'end' => '01/12/2027', 'price' => 250.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '413153815', 'name' => 'GLOBAL FOOD 20-05 C.A.', 'unit' => 'LOCAL TERRAZA', 'start' => '01/12/2025', 'end' => '01/12/2027', 'price' => 800.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Restaurante'],
            ['doc' => 'J', 'num' => '413153815', 'name' => 'GLOBAL FOOD 20-05 C.A.', 'unit' => 'OFICINA TERRAZA', 'start' => '01/12/2025', 'end' => '01/12/2027', 'price' => 100.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],

            // --- Nuevos: Depósitos y Oficinas ---
            ['doc' => 'V', 'num' => '13637899', 'name' => 'JESUS ANDRES LOVERA SALCEDO', 'unit' => 'S-5', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 8.38, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '22492274', 'name' => 'IDIOSELINA MEDINA', 'unit' => 'S-6', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 8.73, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '13637899', 'name' => 'JESUS ANDRES LOVERA SALCEDO', 'unit' => 'S-11', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 17.53, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '11566433', 'name' => 'FELICIA DEL CARMEN ROSELLON AMARIS', 'unit' => 'S-13', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 7.62, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '20130612', 'name' => 'YONATHAN EDUARDO MENDOZA LOZADA', 'unit' => 'S-14', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 22.59, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '20130612', 'name' => 'YONATHAN EDUARDO MENDOZA LOZADA', 'unit' => 'S-14-1', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 14.97, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'J', 'num' => '316287645', 'name' => 'PESCADERIA NUNES ABREU C.A', 'unit' => 'S-15', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 14.97, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '12415176', 'name' => 'JOSE IGNACIO DE FREITAS', 'unit' => 'S-16', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 7.62, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'S-19', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 13.30, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA /', 'unit' => 'S-23', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 15.94, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '10803405', 'name' => 'CARLOS MANUEL MENDEZ LIRA', 'unit' => 'S-24', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 7.62, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '10803405', 'name' => 'CARLOS MANUEL MENDEZ LIRA', 'unit' => 'S-25', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 7.62, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'J', 'num' => '406029300', 'name' => 'INVERSIONES THILE C.A', 'unit' => 'S-28', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 37.43, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '25957554', 'name' => 'YOSANDRY KATERINE RUIZ LINARES', 'unit' => 'S-30', 'start' => '01/08/2024', 'end' => null, 'price' => 31.12, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '25957554', 'name' => 'YOSANDRY KATERINE RUIZ LINARES', 'unit' => 'S-30A', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 31.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'S-31', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 8.73, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '9064272',  'name' => 'MARIA VICTORIA LOZADA RUIZ', 'unit' => 'S-32', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 17.11, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '18020871', 'name' => 'MIGUEL ANGEL TORRES PUENTE', 'unit' => 'S-34', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 55.10, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'S-35', 'start' => '01/08/2024', 'end' => null, 'price' => 60.36, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'S-36', 'start' => '01/08/2024', 'end' => null, 'price' => 108.76, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS ', 'unit' => 'S-37', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 75.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'E', 'num' => '84554980', 'name' => 'RICARDO GONZALEZ RAMIREZ', 'unit' => 'S-38', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 33.19, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '6730785', 'name' => 'MAIRA BEATRIZ FARIAS SUAREZ', 'unit' => 'S-39', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 105.60, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '8509183',  'name' => 'LUIS DARIO RODRIGUEZ', 'unit' => 'S-40', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 105.60, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],

            // Lockers (S-49 a S-61)
            ['doc' => 'V', 'num' => '6279725', 'name' => 'GREGORIO ALBERTO CAMACHO GARCIA', 'unit' => 'S-49', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '10865205', 'name' => 'JANETH JOSEFINA MONTOYA', 'unit' => 'S-50', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '9966862',  'name' => 'ADELINA EVA NUÑEZ', 'unit' => 'S-51', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 14.89, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '20095054', 'name' => 'JESUS ALFREDO DE ALMEIDA MENDEZ', 'unit' => 'S-52', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '18361214', 'name' => 'HENRY OSWALDO FERNANDEZ CERVEN', 'unit' => 'S-53', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '3312658', 'name' => 'MARIA CRISTINA RAMIRES DE SANCHEZ', 'unit' => 'S-54', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '9969617', 'name' => 'KATTY CAROLINA ROJAS PEREZ', 'unit' => 'S-55', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '11821895', 'name' => 'MARIA DE LOS ANGELES RIVAS GONZALEZ /', 'unit' => 'S-56', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '5960934', 'name' => 'MARIA LUANDA', 'unit' => 'S-59', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '5960934', 'name' => 'MARIA LUANDA', 'unit' => 'S-60', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'E', 'num' => '81115646', 'name' => 'MARIA LUISA LEON DE CACERES', 'unit' => 'S-61', 'start' => '01/01/2025', 'end' => '01/01/2026', 'price' => 9.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ', 'unit' => 'S-N', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 9.98, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '5144194',  'name' => 'EMILIA BRAVO FERNANDEZ', 'unit' => 'S-O', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 9.98, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '10167008', 'name' => 'LUIS RAMON DUQUE', 'unit' => 'SO-43', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 79.14, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'E', 'num' => '81875217', 'name' => 'JOAO MARTINHO NUNES', 'unit' => 'SO-44', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 82.58, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'V', 'num' => '14775627', 'name' => 'LETICIA MORENO DE VALENCIA', 'unit' => 'SO-46', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 64.61, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ', 'unit' => 'SO-48', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 79.14, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'SO-49', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 113.17, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],

            // Oficinas SO-* faltantes
            ['doc' => 'V', 'num' => '8509183', 'name' => 'LUIS DARIO RODRIGUEZ', 'unit' => 'SO-40', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 61.93, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'E', 'num' => '81535511', 'name' => 'CARLOS JORGE NUNES', 'unit' => 'SO-41', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 46.67, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'V', 'num' => '13478472', 'name' => 'JOSE GREGORIO JARDIN PESTANA', 'unit' => 'SO-42', 'start' => '01/10/2025', 'end' => '01/10/2026', 'price' => 48.17, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'V', 'num' => '19371956', 'name' => 'MICHELL GREGORIO OROPEZA ARROS ', 'unit' => 'SO-45', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 75.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'SO-47', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 236.26, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],

            // Deshuesaderos SS-* faltantes
            ['doc' => 'V', 'num' => '12399747', 'name' => 'ALAN STANLEY PAZMIÑO NIETO', 'unit' => 'SS-A', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 60.06, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'SS-B', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 46.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '13478472', 'name' => 'JOSE GREGORIO JARDIN PESTANA', 'unit' => 'SS-C', 'start' => '01/10/2025', 'end' => '01/10/2026', 'price' => 40.20, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'SS-D', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 59.09, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '13478472', 'name' => 'JOSE GREGORIO JARDIN PESTANA', 'unit' => 'SS-E', 'start' => '01/10/2025', 'end' => '01/10/2026', 'price' => 41.07, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'J', 'num' => '316287645', 'name' => 'PESCADERIA NUNES ABREU C.A', 'unit' => 'SS-F', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 41.07, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'E', 'num' => '81535511', 'name' => 'CARLOS JORGE NUNES', 'unit' => 'SS-G', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 47.63, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'SS-H', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 65.70, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'SS-I', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 76.05, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '6012218', 'name' => 'MANUEL ALFREDO PESTANO PUERTA', 'unit' => 'SS-J', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 71.48, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'V', 'num' => '13637899', 'name' => 'JESUS ANDRES LOVERA SALCEDO', 'unit' => 'SS-K', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 57.06, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '5520548', 'name' => 'MARINA LEONOR DULCEY MEDERO', 'unit' => 'SS-L', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 32.92, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Deshuesaderos'],
            ['doc' => 'J', 'num' => '294388841', 'name' => 'CHARCUTERIA TRIGO DORADO C.A', 'unit' => 'SSO-01', 'start' => '31/01/2025', 'end' => '31/01/2026', 'price' => 226.20, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Oficina Administrativa'],

            // Nuevos contratos S-E, S-F, S-G, S-H, S-I
            ['doc' => 'V', 'num' => '13137429', 'name' => 'LILIA REYES RUNZA', 'unit' => 'S-E', 'start' => '01/08/2024', 'end' => null, 'price' => 9.98, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'S-F', 'start' => '01/08/2024', 'end' => null, 'price' => 10.60, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'S-G', 'start' => '01/08/2024', 'end' => null, 'price' => 9.43, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'S-H', 'start' => '01/08/2024', 'end' => null, 'price' => 9.98, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '16905366', 'name' => 'FRANCISCO JAVIER ARIAS BARROZO', 'unit' => 'S-I', 'start' => '01/08/2024', 'end' => null, 'price' => 9.43, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],

            // S-X y S-Y (Actualizados)
            ['doc' => 'V', 'num' => '15198619', 'name' => 'GLAYDE MILAGROS CONDE GONZALEZ', 'unit' => 'S-X', 'start' => '01/09/2025', 'end' => '01/09/2026', 'price' => 12.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '15198619', 'name' => 'GLAYDE MILAGROS CONDE GONZALEZ', 'unit' => 'S-Y', 'start' => '01/09/2025', 'end' => '01/09/2026', 'price' => 12.00, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '16661247', 'name' => 'JUAN FRANCISCO DIAZ', 'unit' => 'S-Z1', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 9.98, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '6730785',  'name' => 'MAIRA BEATRIZ FARIAS SUAREZ', 'unit' => 'S-Z2', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 9.98, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
            ['doc' => 'V', 'num' => '25867051', 'name' => 'ANTHONY YOHAN HERNANDEZ HERNANDEZ', 'unit' => 'S-Z5', 'start' => '01/08/2024', 'end' => '01/08/2025', 'price' => 10.60, 'type' => 'CONTR', 'modality' => 'TFIJA', 'rubro' => 'Depósito'],
        ];

        $unsignedUnits = [
            'S-19', 'S-23', 'S-31', 'S-37', 'S-49', 'S-50', 'S-52', 'S-53', 'S-54', 'S-55', 'S-56', 'S-59', 'S-60', 'S-61',
            'S-F', 'S-G', 'S-H', 'S-I',
            'SO-40', 'SO-41', 'SO-45', 'SO-47',
            'SS-A', 'SS-B', 'SS-D', 'SS-G', 'SS-H', 'SS-I', 'SS-J', 'SS-L',
            'FL-01', 'FL-02', 'FL-03', 'FL-04', 'FL-05', 'FL-06', 'FL-07', 'FL-08', 'FL-09', 'FL-10', 'FL-11', 'FL-12',
        ];

        // Group by (doc,num,name,start,end,rubro)
        $groups = [];
        foreach ($rows as $r) {
            // All rows in our dataset have valid start dates, no need to check
            $start = $this->toDate($r['start']);
            $end = $this->toNullableDate($r['end']);
            $common = implode('|', [strtoupper((string) $r['doc']), (string) $r['num'], strtoupper((string) $r['name']), $start, $end ?? 'NULL', strtoupper((string) $r['rubro'])]);
            $isMl = (bool) ($r['ml'] ?? false);
            $key = $isMl ? 'ML|'.$common : 'SL|'.$common.'|'.(string) $r['unit'];
            $groups[$key] = $groups[$key] ?? [
                'doc' => strtoupper((string) $r['doc']),
                'num' => (string) $r['num'],
                'name' => strtoupper((string) $r['name']),
                'start' => $start,
                'end' => $end,
                'rubro' => (string) $r['rubro'],
                'units' => [],
                'additional' => [],
                'price' => null,
                'type' => null,
                'modality' => null,
            ];
            $groups[$key]['units'][] = (string) $r['unit'];
            $groups[$key]['price'] = $r['price'] ?? null;
            $groups[$key]['type'] = $r['type'];
            $groups[$key]['modality'] = $r['modality'];
            // Merge any additional signers
            if (isset($r['additional']) && is_array($r['additional'])) {
                foreach ($r['additional'] as $a) {
                    $doc = strtoupper(trim((string) $a['doc']));
                    $num = trim((string) $a['num']);
                    $name = strtoupper(trim((string) $a['name']));
                    // Skip incomplete entries
                    if ($doc === '' || $num === '' || $name === '') {
                        continue;
                    }
                    $ad = [
                        'doc' => $doc,
                        'num' => $num,
                        'name' => $name,
                    ];
                    // Avoid duplicates
                    $exists = false;
                    foreach ($groups[$key]['additional'] as $ex) {
                        if ($ex['doc'] === $ad['doc'] && $ex['num'] === $ad['num'] && $ex['name'] === $ad['name']) {
                            $exists = true;
                            break;
                        }
                    }
                    if (! $exists) {
                        $groups[$key]['additional'][] = $ad;
                    }
                }
            }
        }

        // Resolve service
        /** @var ContractServiceInterface|ContractService $service */
        $service = app(ContractServiceInterface::class);

        // Determine next sequence for 'S-C###' numbering based on existing contracts
        $maxSeq = 0;
        foreach (DB::table('contracts')->select('number')->get() as $row) {
            $num = (string) ($row->number ?? '');
            if (preg_match('/^S-C(\d{1,})$/i', $num, $m)) {
                $n = (int) $m[1];
                if ($n > $maxSeq) {
                    $maxSeq = $n;
                }
            }
        }
        $seq = $maxSeq + 1;
        foreach ($groups as $g) {
            $concessionaire = $this->ensureConcessionaire($g['doc'], $g['num'], $g['name']);
            $tradeCatId = $this->ensureTradeCategory($g['rubro']);

            // Resolve local ids, skip group if any local missing
            $localIds = [];
            foreach (array_unique($g['units']) as $code) {
                $id = Local::query()->where('code', $code)->value('id');
                if (! $id) {
                    // silently skip this group if local not present
                    $localIds = [];
                    break;
                }
                $localIds[] = (int) $id;
            }
            if (empty($localIds)) {
                continue;
            }

            $groupHasUnsigned = ! empty(array_intersect($unsignedUnits, array_unique($g['units'])));

            // Skip if any of these locals already has an active (VIG/EXT) contract to keep seeder idempotent
            $activeStatusIds = array_values(array_filter([
                $this->statusIdByCode('VIG'),
                $this->statusIdByCode('EXT'),
            ]));
            if (! empty($activeStatusIds)) {
                $existingActive = Contract::query()
                    ->whereIn('contract_status_id', $activeStatusIds)
                    ->whereHas('locals', fn ($q) => $q->whereIn('locals.id', $localIds))
                    ->get(['id', 'signed_at']);
                if ($existingActive->isNotEmpty()) {
                    if (! $groupHasUnsigned) {
                        try {
                            $signedAt = Carbon::parse($g['start'])->startOfDay();
                        } catch (\Throwable) {
                            $signedAt = now();
                        }
                        \DB::table('contracts')
                            ->whereIn('id', $existingActive->pluck('id')->all())
                            ->whereNull('signed_at')
                            ->update(['signed_at' => $signedAt, 'updated_at' => now()]);
                    }

                    // Skip creating a new contract for this group to keep idempotency
                    continue;
                }
            }

            // Simple correlativo para el número de contrato con prefijo estándar 'S-C###'
            $contractNumber = sprintf('S-C%03d', $seq);

            // Create and confirm
            // Resolve additional signers (if any)
            $additionalIds = [];
            foreach ($g['additional'] as $a) {
                $am = $this->ensureConcessionaire($a['doc'], $a['num'], $a['name']);
                if ($am->getKey() && (int) $am->getKey() !== (int) $concessionaire->getKey()) {
                    $additionalIds[] = (int) $am->getKey();
                }
            }

            // Validate and resolve type and modality
            $endDate = $g['end'];
            $explicitType = strtoupper(trim((string) $g['type']));
            $explicitModality = strtoupper(trim((string) $g['modality']));

            // Monthly price only for CONTR + TFIJA
            $monthlyPrice = null;
            if ($explicitType === 'CONTR' && $explicitModality === 'TFIJA') {
                $p = $g['price'];
                if ($p !== null) {
                    $monthlyPrice = (float) $p;
                }
            }

            $useTypeId = $this->contractTypeIdByCode($explicitType) ?? $typeConvId;
            $useModalityId = $this->modalityIdByCode($explicitModality) ?? $modM2Id;

            // Billing day: for TFIJA contracts, use day of start_date; otherwise null
            $billingDay = null;
            if ($useModalityId === $modFixedId) {
                try {
                    $billingDay = (int) Carbon::parse($g['start'])->day;
                } catch (\Throwable) {
                    $billingDay = null;
                }
            }

            /** @var Contract $contract */
            $contract = $service->create([
                'number' => $contractNumber,
                'contract_type_id' => $useTypeId,
                'contract_modality_id' => $useModalityId,
                'trade_category_id' => $tradeCatId,
                'start_date' => $g['start'],
                'end_date' => $endDate,
                'billing_day' => $billingDay,
                'monthly_price_eur' => $monthlyPrice,
                'local_ids' => $localIds,
                'primary_concessionaire_id' => (int) $concessionaire->getKey(),
                'additional_concessionaire_ids' => array_values(array_unique($additionalIds)),
                // optional: explicit BORR to be clear (service defaults to BORR if missing)
                'contract_status_id' => $statusBorrId,
            ]);

            // Confirm => transitions locals to OCUP and records history
            $service->confirm($contract);
            if (! $groupHasUnsigned) {
                try {
                    $signedAt = Carbon::parse($g['start'])->startOfDay();
                } catch (\Throwable) {
                    $signedAt = now();
                }
                \DB::table('contracts')
                    ->where('id', $contract->getKey())
                    ->update(['signed_at' => $signedAt, 'updated_at' => now()]);
            }

            if (in_array((string) $g['num'], ['50244208', '501530270'], true)) {
                $units = array_unique($g['units']);
                if (! empty(array_intersect($units, ['LOCAL 6', 'LOCAL 7', 'LOCAL 8', 'LOCAL TERRAZA', 'OFICINA TERRAZA']))) {
                    DB::table('contracts')
                        ->where('id', $contract->getKey())
                        ->update([
                            'end_date' => '2025-11-30',
                            'contract_status_id' => $statusTermId,
                            'updated_at' => now(),
                        ]);

                    $dispId2 = (int) (LocalStatus::query()->where('code', 'DISP')->value('id') ?? 0);
                    if ($dispId2 > 0) {
                        DB::table('locals')
                            ->whereIn('id', $localIds)
                            ->update(['local_status_id' => $dispId2]);
                    }
                }
            }

            $seq++;
        }

        // Post-process: mark overdue contracts (end_date < today) as VENC (no liberación de locales)
        // Mantener coherencia de LocalStatus: considerar OCUPADOS los locales con contratos VIG/EXT vigentes hoy
        // y también los asociados a contratos VENCIDOS (siguen ocupando hasta terminar el contrato).
        $service->expireOverdue();

        // Reconcile all locals' status to match canonical availability rule (OCUP when VIG/EXT today, or any VENC)
        $today = Carbon::now()->startOfDay()->toDateString();
        $ocupId = (int) (LocalStatus::query()->where('code', 'OCUP')->value('id') ?? 0);
        $dispId = (int) (LocalStatus::query()->where('code', 'DISP')->value('id') ?? 0);
        $vigId = $this->statusIdByCode('VIG');
        $extId = $this->statusIdByCode('EXT');
        $vencId = $this->statusIdByCode('VENC');

        if ($dispId > 0 && $ocupId > 0) {
            $activeLocalIds = DB::table('contract_local as cl')
                ->join('contracts as c', 'c.id', '=', 'cl.contract_id')
                ->whereNull('c.deleted_at')
                ->where(function ($q) use ($vigId, $extId, $vencId, $today): void {
                    // VIG/EXT activos hoy por rango de fechas
                    $q->where(function ($w) use ($vigId, $extId, $today): void {
                        if ($vigId || $extId) {
                            $ids = array_values(array_filter([(int) $vigId, (int) $extId]));
                            if (! empty($ids)) {
                                $w->whereIn('c.contract_status_id', $ids)
                                    ->whereDate('c.start_date', '<=', $today);
                            }
                        }
                    })
                    // O bien, cualquier contrato marcado como VENC (mantiene ocupación)
                        ->orWhere(function ($w) use ($vencId): void {
                            if ($vencId) {
                                $w->where('c.contract_status_id', (int) $vencId);
                            }
                        });
                })
                ->distinct()
                ->pluck('cl.local_id');

            // Marcar ocupados y el resto como disponibles
            DB::table('locals')->whereIn('id', $activeLocalIds)->update(['local_status_id' => $ocupId]);
            DB::table('locals')->whereNotIn('id', $activeLocalIds)->update(['local_status_id' => $dispId]);
        }
    }

    private function statusIdByCode(string $code): ?int
    {
        return ContractStatus::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id');
    }

    private function contractTypeIdByCode(string $code): ?int
    {
        return ContractType::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id');
    }

    private function modalityIdByCode(string $code): ?int
    {
        return ContractModality::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id');
    }

    private function docTypeIdByCode(string $code): ?int
    {
        return DocumentType::query()->whereRaw('UPPER(code) = ?', [strtoupper($code)])->value('id');
    }

    private function concessionaireTypeIdByDoc(string $docCode): ?int
    {
        $cc = strtoupper($docCode) === 'J' ? 'PJUR' : 'PNAT';

        return ConcessionaireType::query()->whereRaw('UPPER(code) = ?', [$cc])->value('id');
    }

    private function ensureConcessionaire(string $docCode, string $docNumber, string $name): Concessionaire
    {
        $docId = $this->docTypeIdByCode($docCode);
        $ctype = $this->concessionaireTypeIdByDoc($docCode);
        if (! $docId || ! $ctype) {
            throw new \RuntimeException('Tipos de documento o concesionario faltantes');
        }

        $email = strtolower(Str::slug($name, '.')).'.'.$docCode.strtolower(preg_replace('/\D+/', '', $docNumber)).'@mailinator.com';
        /** @var Concessionaire $m */
        $m = Concessionaire::withTrashed()->updateOrCreate(
            [
                'document_type_id' => $docId,
                'document_number' => (string) $docNumber,
            ],
            [
                'concessionaire_type_id' => $ctype,
                'full_name' => strtoupper($name),
                'fiscal_address' => 'Sin direccion',
                'email' => $email,
                'is_active' => true,
            ]
        );
        if ($m->trashed()) {
            $m->restore();
        }

        return $m;
    }

    private function ensureTradeCategory(string $name): int
    {
        $map = [
            'Papas' => 'PAPAS',
            'Huevos' => 'HUEVOS',
            'Aliños/Condimentos' => 'ALICON',
            'Casabe' => 'CASABE',
            'Hortalizas' => 'HORTALIZAS',
            'Verduras / hortalizas' => 'VERDURAS',
            'Cebolla, Ajos y Ajies' => 'CEBOLLA',
            'Tomates / Pimenton' => 'TOMATES',
            'Platanos' => 'PLATANOS',
            'Platanos / Cambures' => 'PLATCAMB',
            'Cambures' => 'CAMBURES',
            'Café' => 'CAFE',
            'Productos de Belleza' => 'BELLEZA',
            'Comida' => 'COMIDA',
            'Confitería' => 'CONFITERIA',
            'Charcutería' => 'CHARCUTERIA',
            'Quesos' => 'QUESOS',
            'Telecomunicaciones' => 'TELECOM',
            'Productos Refrigerados y Congelados' => 'REFRIG',
            'Pan' => 'PAN',
            'Ferretería' => 'FERRETERIA',
            'Pastelitos Andinos / Dulces Criollos / Tortas en Porciones / Bebidas no Alcoholicas' => 'DULCES',
            'Manualidades' => 'MANUALIDADES',
            'Cholas / Pantuflas / Paraguas' => 'CHOLAS',
            'Utensilios' => 'UTENSILIOS',
            'Quincalla' => 'QUINCALLA',
            'Cochino' => 'COCHINO',
            'Pescados / mariscos' => 'PESCADOS',
            'Pollos' => 'POLLOS',
            'Derivados de Cerdo y Refrescos' => 'DERIVADOS',
            'Bolsas' => 'BOLSAS',
            'Aliños' => 'ALINOS',
            'Quincalla / misceláneos' => 'QUINCALLA_MISC',
            'Ropa / textiles' => 'ROPA_TEXTILES',
            'Frutas Secas' => 'FRUTAS_SECAS',
            'Velas' => 'VELAS',
            'Ropa / Quincalla' => 'ROPA_QUINCALLA',
            'Ropa Deportiva' => 'ROPA_DEPORTIVA',
            'Ropa / Zapatos' => 'ROPA_ZAPATOS',
            'Ropa' => 'ROPA',
            'CASABE' => 'CASABE',
            'Zapatos' => 'ZAPATOS',
            'Ropa Intima' => 'ROPA_INTIMA',
            'Frutas' => 'FRUTAS',
            'Flores' => 'FLORES',
            'Plantas' => 'PLANTAS',
            'Corte, Costura y Confeccion de Ropa' => 'CORTE_COSTURA',
            'Viveres' => 'VIVERES',
            'Aceitunas' => 'ACEITUNAS',
            'Bebidas / refrescos' => 'BEBIDAS',
            'Lacteos' => 'LACTEOS',
            'Artesanía' => 'ARTESANIA',
            'Papeleria y Accesorios' => 'PAPELERIA',
            'Perfumería / belleza' => 'PERFUMERIA',
            'Productos naturales' => 'PRODUCTOS_NATURALES',
            'Reposteria' => 'REPOSTERIA',
            'Productos Nacionales e Importados' => 'PRODUCTOS_NAC_IMP',
            'Lenceria' => 'LENCERIA',
            'Cachapas' => 'CACHAPAS',
            'Delicatesses y Productos Importados' => 'DELICATESSES',
            'Productos Lacteos y Postres' => 'LACTEOS_POSTRES',
            'Productos y Alimentos Procesados' => 'ALIMENTOS_PROC',
            'Vinatería' => 'VINATERIA',
            'Restaurante' => 'RESTAURANTE',
            'Oficina Administrativa' => 'OFICINA_ADMIN',
            'Depósito' => 'DEPOSITO',
            'Almacén' => 'ALMACEN',
        ];

        // Si existe en el mapa, usar ese código
        if (isset($map[$name])) {
            $code = $map[$name];
        } else {
            // Si no, generar un código y limitarlo a 30 caracteres
            $code = strtoupper(Str::slug($name, ''));
            if (strlen($code) > 30) {
                $code = substr($code, 0, 30);
            }
        }

        /** @var TradeCategory $m */
        $m = TradeCategory::withTrashed()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => null,
                'is_active' => true,
            ]
        );
        if ($m->trashed()) {
            $m->restore();
        }

        return (int) $m->getKey();
    }

    private function toDate(string $dmy): string
    {
        return Carbon::createFromFormat('d/m/Y', $dmy)->toDateString();
    }

    private function toNullableDate(?string $dmy): ?string
    {
        if (! $dmy || strtoupper($dmy) === 'INDEFINIDO') {
            return null;
        }

        return $this->toDate($dmy);
    }
}
