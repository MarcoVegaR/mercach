# 📊 Propuesta: Sección "Análisis de Deudas" - Parte 2

## 🔧 Implementación Técnica Detallada

### **1. Controlador Backend**

```php
<?php
// app/Http/Controllers/Api/DebtAnalysisController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DebtAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtAnalysisController extends Controller
{
    public function __construct(private DebtAnalysisService $service) {}

    /**
     * Lista paginada de concesionarios morosos
     */
    public function delinquentConcessionaires(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => 'string|in:debt_eur,debt_bs,days_overdue,name',
            'sort_dir' => 'string|in:asc,desc',
            'min_debt_eur' => 'numeric|min:0',
            'max_debt_eur' => 'numeric|min:0',
            'min_days' => 'integer|min:0',
            'market_id' => 'integer|exists:markets,id',
            'search' => 'string|max:255',
        ]);

        $data = $this->service->getDelinquentConcessionaires($filters);

        return response()->json($data);
    }

    /**
     * Lista paginada de locales morosos
     */
    public function delinquentLocals(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'sort_by' => 'string|in:debt_eur,days_overdue,code',
            'sort_dir' => 'string|in:asc,desc',
            'min_debt_eur' => 'numeric|min:0',
            'local_type_id' => 'integer|exists:local_types,id',
            'market_id' => 'integer|exists:markets,id',
            'search' => 'string|max:255',
        ]);

        $data = $this->service->getDelinquentLocals($filters);

        return response()->json($data);
    }

    /**
     * Lista de concesionarios solventes
     */
    public function solventConcessionaires(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'months_solvent' => 'integer|min:1',
            'market_id' => 'integer|exists:markets,id',
            'search' => 'string|max:255',
        ]);

        $data = $this->service->getSolventConcessionaires($filters);

        return response()->json($data);
    }

    /**
     * Distribuciones agregadas para gráficas
     */
    public function distributions(Request $request): JsonResponse
    {
        $data = $this->service->getDistributions();

        return response()->json($data);
    }

    /**
     * Exportar a CSV
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate([
            'scope' => 'required|string|in:concessionaires,locals',
            'format' => 'string|in:csv,xlsx',
            // ... otros filtros
        ]);

        return $this->service->export($filters);
    }
}
```

---

### **2. Servicio Backend (Query Optimizada)**

```php
<?php
// app/Services/DebtAnalysisService.php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class DebtAnalysisService
{
    /**
     * Obtener concesionarios morosos con paginación
     */
    public function getDelinquentConcessionaires(array $filters): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $perPage = min((int) ($filters['per_page'] ?? 25), 100);
        $sortBy = $filters['sort_by'] ?? 'debt_eur';
        $sortDir = $filters['sort_dir'] ?? 'desc';

        // Obtener tasa de cambio activa
        $fxRate = $this->getActiveFxRate();
        $today = Carbon::today()->toDateString();

        // Query base optimizada con CTEs
        $query = DB::table('concessionaires as cn')
            ->selectRaw("
                cn.id,
                cn.full_name,
                cn.document_number,
                m.name as market_name,
                COUNT(DISTINCT cl.local_id)::int as locals_count,
                COUNT(DISTINCT ch.id)::int as charges_count,
                SUM(ch.amount_minor)::bigint as debt_eur_minor,
                COALESCE(SUM(pa.amount_bs_minor), 0)::bigint as paid_bs_minor,
                ROUND(AVG(EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on))::numeric))::int as days_overdue_avg,
                MAX(EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on))::int) as days_overdue_max
            ")
            ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'cn.id')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('charges as ch', function($j) {
                $j->on('ch.debtor_id', '=', 'cl.local_id')
                  ->where('ch.debtor_type', '=', 'LOCAL');
            })
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->leftJoin('payment_allocations as pa', 'pa.charge_id', '=', 'ch.id')
            ->leftJoin('markets as m', 'm.id', '=', 'cn.market_id')
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $today)
            ->whereNull('cn.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNull('ch.deleted_at');

        // Aplicar filtros
        if (!empty($filters['market_id'])) {
            $query->where('cn.market_id', (int) $filters['market_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->whereRaw('LOWER(cn.full_name) LIKE ?', ['%' . strtolower($search) . '%'])
                  ->orWhere('cn.document_number', 'LIKE', "%{$search}%");
            });
        }

        if (!empty($filters['min_days'])) {
            $query->havingRaw('ROUND(AVG(EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on))::numeric)) >= ?',
                [(int) $filters['min_days']]);
        }

        $query->groupBy('cn.id', 'cn.full_name', 'cn.document_number', 'm.name');

        // Filtro de deuda (después del group by)
        if (!empty($filters['min_debt_eur'])) {
            $minEurMinor = (int) ($filters['min_debt_eur'] * 100);
            $query->havingRaw('SUM(ch.amount_minor) - COALESCE(SUM(pa.amount_bs_minor), 0) / ? >= ?',
                [$fxRate, $minEurMinor]);
        }

        // Contar total antes de paginar
        $countQuery = clone $query;
        $total = DB::table(DB::raw("({$countQuery->toSql()}) as subquery"))
            ->mergeBindings($countQuery)
            ->count();

        // Ordenar
        $sortColumn = match($sortBy) {
            'debt_eur' => 'debt_eur_minor',
            'debt_bs' => DB::raw('(debt_eur_minor * ' . $fxRate . ' - paid_bs_minor)'),
            'days_overdue' => 'days_overdue_avg',
            'name' => 'cn.full_name',
            default => 'debt_eur_minor'
        };
        $query->orderBy($sortColumn, $sortDir);

        // Paginar
        $results = $query->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Procesar resultados
        $data = $results->map(function($row) use ($fxRate) {
            $outstandingEur = max(0, $row->debt_eur_minor - ($row->paid_bs_minor / $fxRate));
            $outstandingBs = max(0, ($row->debt_eur_minor * $fxRate) - $row->paid_bs_minor);

            return [
                'id' => (int) $row->id,
                'full_name' => (string) $row->full_name,
                'document_number' => (string) $row->document_number,
                'market_name' => (string) ($row->market_name ?? 'Sin asignar'),
                'debt_eur_minor' => (int) $outstandingEur,
                'debt_bs_minor' => (int) $outstandingBs,
                'days_overdue_avg' => (int) $row->days_overdue_avg,
                'days_overdue_max' => (int) $row->days_overdue_max,
                'locals_count' => (int) $row->locals_count,
                'charges_count' => (int) $row->charges_count,
                'severity' => $this->calculateSeverity($row->days_overdue_avg),
            ];
        });

        // Calcular resumen
        $summary = [
            'total_debt_eur_minor' => $data->sum('debt_eur_minor'),
            'total_debt_bs_minor' => $data->sum('debt_bs_minor'),
            'total_count' => $total,
            'avg_debt_eur_minor' => $total > 0 ? (int) ($data->sum('debt_eur_minor') / $total) : 0,
            'avg_days_overdue' => $total > 0 ? (int) $data->avg('days_overdue_avg') : 0,
        ];

        return [
            'data' => $data->values()->all(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'summary' => $summary,
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Calcular severidad por días vencidos
     */
    private function calculateSeverity(int $days): string
    {
        return match(true) {
            $days > 90 => 'critical',
            $days > 60 => 'high',
            $days > 30 => 'medium',
            default => 'low'
        };
    }

    /**
     * Obtener tasa FX activa (con caché)
     */
    private function getActiveFxRate(): float
    {
        return Cache::remember('fx_rate_eur_active', 300, function() {
            $rate = DB::table('fx_rates')
                ->where('currency_code', 'EUR')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->value('rate_to_ves');

            return $rate ? (float) $rate : 1.0;
        });
    }

    /**
     * Obtener distribuciones para gráficas
     */
    public function getDistributions(): array
    {
        $fxRate = $this->getActiveFxRate();
        $today = Carbon::today()->toDateString();

        // Distribución por aging
        $byAging = DB::table('charges as ch')
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->leftJoin('payment_allocations as pa', 'pa.charge_id', '=', 'ch.id')
            ->selectRaw("
                CASE
                    WHEN EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on)) <= 30 THEN '0-30'
                    WHEN EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on)) <= 60 THEN '31-60'
                    WHEN EXTRACT(DAY FROM AGE(CURRENT_DATE, ch.due_on)) <= 90 THEN '61-90'
                    ELSE '90+'
                END as bucket,
                SUM(ch.amount_minor * ?) - COALESCE(SUM(pa.amount_bs_minor), 0) as debt_bs_minor,
                COUNT(DISTINCT ch.id) as count
            ", [$fxRate])
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $today)
            ->whereNull('ch.deleted_at')
            ->groupByRaw("bucket")
            ->orderByRaw("
                CASE bucket
                    WHEN '0-30' THEN 1
                    WHEN '31-60' THEN 2
                    WHEN '61-90' THEN 3
                    ELSE 4
                END
            ")
            ->get()
            ->map(fn($r) => [
                'bucket' => $r->bucket,
                'debt_eur_minor' => (int) ($r->debt_bs_minor / $fxRate),
                'debt_bs_minor' => (int) $r->debt_bs_minor,
                'count' => (int) $r->count,
            ]);

        // Distribución por mercado
        $byMarket = DB::table('concessionaires as cn')
            ->join('concessionaire_contract as cc', 'cc.concessionaire_id', '=', 'cn.id')
            ->join('contracts as c', 'c.id', '=', 'cc.contract_id')
            ->join('contract_local as cl', 'cl.contract_id', '=', 'c.id')
            ->join('charges as ch', function($j) {
                $j->on('ch.debtor_id', '=', 'cl.local_id')
                  ->where('ch.debtor_type', '=', 'LOCAL');
            })
            ->join('charge_statuses as chs', 'chs.id', '=', 'ch.charge_status_id')
            ->leftJoin('payment_allocations as pa', 'pa.charge_id', '=', 'ch.id')
            ->join('markets as m', 'm.id', '=', 'cn.market_id')
            ->selectRaw("
                m.id as market_id,
                m.name as market_name,
                SUM(ch.amount_minor * ?) - COALESCE(SUM(pa.amount_bs_minor), 0) as debt_bs_minor,
                COUNT(DISTINCT cn.id) as count
            ", [$fxRate])
            ->whereIn('chs.code', ['ISSUED', 'PARTIAL'])
            ->whereDate('ch.due_on', '<', $today)
            ->whereNull('cn.deleted_at')
            ->whereNull('c.deleted_at')
            ->whereNull('ch.deleted_at')
            ->groupBy('m.id', 'm.name')
            ->orderBy('debt_bs_minor', 'desc')
            ->get()
            ->map(fn($r) => [
                'market_id' => (int) $r->market_id,
                'market_name' => (string) $r->market_name,
                'debt_eur_minor' => (int) ($r->debt_bs_minor / $fxRate),
                'debt_bs_minor' => (int) $r->debt_bs_minor,
                'count' => (int) $r->count,
            ]);

        return [
            'by_aging' => $byAging->all(),
            'by_market' => $byMarket->all(),
            'generated_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Exportar a CSV
     */
    public function export(array $filters): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $scope = $filters['scope'];
        $data = $scope === 'locals'
            ? $this->getDelinquentLocals(array_merge($filters, ['per_page' => 10000]))
            : $this->getDelinquentConcessionaires(array_merge($filters, ['per_page' => 10000]));

        $filename = sprintf(
            'analisis-deuda-%s-%s.csv',
            $scope,
            Carbon::now()->format('Y-m-d-His')
        );

        return response()->streamDownload(function() use ($data, $scope) {
            $handle = fopen('php://output', 'w');

            // Headers CSV
            if ($scope === 'concessionaires') {
                fputcsv($handle, [
                    'ID', 'Concesionario', 'Documento', 'Mercado',
                    'Deuda EUR', 'Deuda Bs', 'Días Vencidos Promedio',
                    'Días Vencidos Máximo', 'Locales', 'Cargos', 'Severidad'
                ]);
            } else {
                fputcsv($handle, [
                    'ID', 'Código Local', 'Concesionario', 'Mercado',
                    'Deuda EUR', 'Deuda Bs', 'Días Vencidos', 'Severidad'
                ]);
            }

            // Rows
            foreach ($data['data'] as $row) {
                $csvRow = $scope === 'concessionaires'
                    ? [
                        $row['id'],
                        $row['full_name'],
                        $row['document_number'],
                        $row['market_name'],
                        number_format($row['debt_eur_minor'] / 100, 2, ',', '.'),
                        number_format($row['debt_bs_minor'] / 100, 2, ',', '.'),
                        $row['days_overdue_avg'],
                        $row['days_overdue_max'],
                        $row['locals_count'],
                        $row['charges_count'],
                        $row['severity'],
                    ]
                    : [
                        $row['id'],
                        $row['local_code'],
                        $row['concessionaire_name'],
                        $row['market_name'],
                        number_format($row['debt_eur_minor'] / 100, 2, ',', '.'),
                        number_format($row['debt_bs_minor'] / 100, 2, ',', '.'),
                        $row['days_overdue'],
                        $row['severity'],
                    ];

                fputcsv($handle, $csvRow);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
```

---

## 📋 Resumen de Propuesta

### **✅ Ventajas:**

1. Vista agregada completa (no solo Top 10)
2. Filtrado avanzado multi-criterio
3. Paginación para manejar grandes volúmenes
4. Exportación para análisis externo
5. Gráficas de distribución visual
6. Deep links a Perfil Económico individual
7. Performance optimizado (índices, caché)

### **🎯 Diferenciación:**

- **Dashboard:** KPIs ejecutivos (¿cómo estamos?)
- **Análisis Deuda:** Vista gerencial filtrable (¿quiénes son?)
- **Perfil Económico:** Vista transaccional (¿qué debe [X]?)

### **⏱️ Estimación:**

- Backend: 2-3 días
- Frontend: 3-4 días
- Testing: 1-2 días
- **TOTAL: 6-9 días** (1.5-2 semanas)

---

**Ver también:** `PROPUESTA_ANALISIS_DEUDA_PARTE1.md` para diseño UI y wireframes.
