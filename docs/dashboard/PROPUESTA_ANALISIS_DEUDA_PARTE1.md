# 📊 Propuesta: Sección "Análisis de Deudas" - Parte 1

## 🎯 Objetivo

Crear una sección intermedia entre el **Dashboard** (vista ejecutiva) y el **Perfil Económico** (vista transaccional) que permita análisis agregado, filtrado y comparación de deudas.

---

## 💡 Concepto: "Vista de Gestor de Cobranza"

### **Problema a resolver:**

```
Gerente de Cobranza: "Necesito ver TODOS los morosos con deuda > €500
                      en el mercado Norte, ordenados por días vencidos"

→ Dashboard: Solo muestra Top 10
→ Perfil Económico: Solo búsqueda individual
→ FALTA: Vista agregada filtrable y paginada
```

### **Solución propuesta:**

Nueva ruta `/dashboard/debt-analysis` con:

- 📊 Tablas paginadas interactivas (25/50/100 registros)
- 🔍 Filtros avanzados multi-criterio
- 📥 Exportación masiva CSV
- 📈 Gráficas de distribución (aging, mercados)
- 🔗 Deep links a Perfil Económico individual

---

## 🏗️ Arquitectura

### **Ubicación:**

```
Sidebar
├── Dashboard (/)
│   └── Tab: Deudas (Top 10 + KPIs)
│
├── 📊 Análisis de Deudas (/dashboard/debt-analysis) ← NUEVO
│   ├── Por Concesionario (tabla completa)
│   ├── Por Local (tabla completa)
│   ├── Solventes (lista)
│   └── Distribución (gráficas)
│
└── Perfil Económico (/admin/economic-profile)
    └── Individual + transaccional
```

### **Flujo:**

```
Dashboard → "Ver análisis completo" → Análisis de Deudas
                                      ├─ Filtrar
                                      ├─ Ordenar
                                      ├─ Paginar
                                      ├─ Click fila → Perfil Económico
                                      └─ Exportar CSV
```

---

## 📐 Diseño UI (Wireframe)

### **Vista: Por Concesionario**

```
┌────────────────────────────────────────────────────────────────┐
│ 📊 Análisis de Deudas                    [← Volver Dashboard]│
├────────────────────────────────────────────────────────────────┤
│ [Por Concesionario] [Por Local] [Solventes] [Distribución]    │
│  ══════════════                                                │
│                                                                 │
│ 🔍 FILTROS                                                     │
│ ┌──────────────────────────────────────────────────────────┐  │
│ │ Mercado: [Todos ▼]  Deuda ≥: [€ 0     ] ≤: [€ 10000   ]│  │
│ │ Días ≥:  [0 ▼]      Buscar:  [            🔍]          │  │
│ │ [Aplicar] [Limpiar]                    [📥 Exportar CSV]│  │
│ └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│ 💡 KPIs FILTRADOS                                              │
│ ┌──────────┬──────────┬──────────┬──────────┐                │
│ │ € 45,230 │ 89 total │ € 508.20 │ 62 días  │                │
│ │ Deuda    │ Morosos  │ Promedio │ Prom días│                │
│ └──────────┴──────────┴──────────┴──────────┘                │
│                                                                 │
│ 📊 TABLA (1-25 de 89)                    [🔽 25 por página]   │
│ ┌──┬────────────────┬────────┬─────────┬──────┬───────────┐  │
│ │# │ Concesionario  │Mercado │ Deuda   │ Días │ Acciones  │  │
│ ├──┼────────────────┼────────┼─────────┼──────┼───────────┤  │
│ │🔴│GRUPO CHILANGO  │ Norte  │€2,450.00│ 125  │[Ver][📧] │  │
│ │🔴│INVERSIONES XYZ │ Centro │€1,830.50│  98  │[Ver][📧] │  │
│ │🟠│COMERCIAL ABC   │ Sur    │€1,205.30│  67  │[Ver][📧] │  │
│ │🟡│TEXTILES EUROPA │ Norte  │€  980.00│  45  │[Ver][📧] │  │
│ │  ...                                                      │  │
│ └──┴────────────────┴────────┴─────────┴──────┴───────────┘  │
│                                                                 │
│ [← Anterior] [1][2][3][4][5]...[9] [Siguiente →]              │
└────────────────────────────────────────────────────────────────┘

Leyenda:
🔴 >90 días  🟠 61-90 días  🟡 31-60 días  🟢 0-30 días
```

---

## 🔧 Especificación Backend

### **Nuevos Endpoints:**

```php
// 1. Lista paginada de morosos
GET /api/debt-analysis/delinquent-concessionaires
  ?page=1
  &per_page=25
  &sort_by=debt_eur
  &sort_dir=desc
  &min_debt_eur=500
  &market_id=2
  &search=GRUPO

// 2. Lista paginada por local
GET /api/debt-analysis/delinquent-locals
  ?page=1&per_page=50

// 3. Lista de solventes
GET /api/debt-analysis/solvent-concessionaires
  ?months_solvent=6

// 4. Distribuciones para gráficas
GET /api/debt-analysis/distributions

// 5. Exportación
GET /api/debt-analysis/export
  ?format=csv
  &filters[...]
```

### **Response Example:**

```json
{
    "data": [
        {
            "id": 123,
            "full_name": "GRUPO CHILANGO",
            "document_number": "J-12345678-9",
            "market_name": "Norte",
            "debt_eur_minor": 245000,
            "debt_bs_minor": 65450500,
            "days_overdue_avg": 125,
            "days_overdue_max": 180,
            "locals_count": 2,
            "charges_count": 15,
            "severity": "critical"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 25,
        "total": 89,
        "last_page": 4
    },
    "summary": {
        "total_debt_eur_minor": 4523050,
        "total_count": 89,
        "avg_debt_eur_minor": 50820,
        "avg_days_overdue": 62
    }
}
```

---

## 📋 Plan de Implementación

### **Fase 1: Backend (2-3 días)**

1. ✅ Crear `DebtAnalysisController`
2. ✅ Crear `DebtAnalysisService`
3. ✅ Implementar queries optimizadas (usar índices)
4. ✅ Agregar rutas en `routes/dashboard.php`
5. ✅ Agregar permisos `dashboard.debt_analysis.view`
6. ✅ Testing con PHPUnit

### **Fase 2: Frontend (3-4 días)**

1. ✅ Crear página `/resources/js/pages/debt-analysis/index.tsx`
2. ✅ Componente `FilterPanel` reutilizable
3. ✅ Componente `DebtTable` con paginación
4. ✅ Componente `DistributionCharts`
5. ✅ Integración con React Query
6. ✅ Testing con Playwright

### **Fase 3: Integración (1 día)**

1. ✅ Agregar link desde Dashboard
2. ✅ Configurar deep links a Perfil Económico
3. ✅ Agregar breadcrumbs
4. ✅ Agregar al sidebar

### **Fase 4: Mejoras (opcional)**

1. ⭕ Exportación Excel (además de CSV)
2. ⭕ Notificaciones por email (morosos críticos)
3. ⭕ Guardado de filtros favoritos
4. ⭕ Comparación periodo anterior

---

**CONTINÚA EN:** `PROPUESTA_ANALISIS_DEUDA_PARTE2.md`
