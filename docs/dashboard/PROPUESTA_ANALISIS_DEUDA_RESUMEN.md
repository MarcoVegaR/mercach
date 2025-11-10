# 📊 Propuesta "Análisis de Deudas" - Resumen Ejecutivo

## 🎯 Objetivo

Crear una **vista intermedia** entre Dashboard (ejecutivo) y Perfil Económico (transaccional) que permita análisis agregado, filtrado y comparación de todas las deudas del negocio.

---

## 💡 Problema a Resolver

```
SITUACIÓN ACTUAL:
├── Dashboard → Solo muestra Top 10 morosos (vista limitada)
└── Perfil Económico → Solo búsqueda individual (uno por uno)

PROBLEMA:
"Necesito ver TODOS los morosos con deuda > €500 en el mercado Norte"
→ No hay forma de hacerlo sin buscar uno por uno

SOLUCIÓN PROPUESTA:
Nueva sección "Análisis de Deudas" con tablas paginadas y filtros
```

---

## 🏗️ Arquitectura Propuesta

### **Ubicación:**

```
/dashboard/debt-analysis (nueva ruta)
```

### **Componentes:**

#### **1. Por Concesionario** (tabla principal)

- Lista completa paginada (25/50/100 por página)
- Filtros: mercado, deuda mínima/máxima, días vencidos, búsqueda
- Ordenamiento: por deuda EUR, días vencidos, nombre
- Severidad visual: 🔴 >90 días | 🟠 61-90 | 🟡 31-60 | 🟢 0-30
- Click en fila → Perfil Económico individual
- Exportar CSV

#### **2. Por Local** (tabla secundaria)

- Similar pero desglosado por espacio físico
- Muestra concesionario responsable de cada local
- Filtros adicionales: tipo de local

#### **3. Solventes** (lista reconocimiento)

- Concesionarios sin deuda vencida
- Ordenados por meses consecutivos solventes
- Útil para incentivos o referencias

#### **4. Distribución** (gráficas analíticas)

- Aging distribution (0-30, 31-60, 61-90, 90+)
- Por mercado (Norte, Centro, Sur)
- Tendencia mensual (últimos 6 meses)
- Top 5 mercados críticos

---

## 🎨 UI Propuesta (Wireframe Simplificado)

```
┌──────────────────────────────────────────────────────────────┐
│ 📊 Análisis de Deudas                  [← Volver Dashboard] │
├──────────────────────────────────────────────────────────────┤
│ [Por Concesionario] [Por Local] [Solventes] [Distribución]  │
│  ══════════════                                              │
│                                                               │
│ 🔍 FILTROS                                                   │
│ Mercado: [Todos ▼]  Deuda: [€ 0  a  € 10000]               │
│ Días ≥: [0 ▼]       Buscar: [_________ 🔍]                  │
│ [Aplicar] [Limpiar]           [📥 Exportar CSV]             │
│                                                               │
│ 💡 KPIs: € 45,230 | 89 morosos | € 508 promedio | 62 días  │
│                                                               │
│ 📊 TABLA (1-25 de 89)              [🔽 25 por página]       │
│ ┌──┬────────────────┬────────┬─────────┬──────┬─────────┐  │
│ │🔴│GRUPO CHILANGO  │ Norte  │€2,450.00│ 125  │[Ver][📧]│  │
│ │🔴│INVERSIONES XYZ │ Centro │€1,830.50│  98  │[Ver][📧]│  │
│ │🟠│COMERCIAL ABC   │ Sur    │€1,205.30│  67  │[Ver][📧]│  │
│ └──┴────────────────┴────────┴─────────┴──────┴─────────┘  │
│                                                               │
│ [← Anterior] [1][2][3][4] ... [Siguiente →]                 │
└──────────────────────────────────────────────────────────────┘
```

---

## 🔧 Especificación Técnica

### **Endpoints API (nuevos):**

```
GET /api/debt-analysis/delinquent-concessionaires
    ?page=1&per_page=25&sort_by=debt_eur&sort_dir=desc
    &min_debt_eur=500&market_id=2&search=GRUPO

GET /api/debt-analysis/delinquent-locals
    ?page=1&per_page=50&local_type_id=1

GET /api/debt-analysis/solvent-concessionaires
    ?months_solvent=6

GET /api/debt-analysis/distributions
    (retorna by_aging, by_market, trend_monthly)

GET /api/debt-analysis/export
    ?scope=concessionaires&format=csv&filters[...]
```

### **Backend (nuevo):**

- `DebtAnalysisController` - Manejo de requests
- `DebtAnalysisService` - Lógica de negocio y queries
- Queries optimizadas con JOIN y GROUP BY
- Caché para FX rates (5 min)
- Paginación eficiente

### **Frontend (nuevo):**

- `/resources/js/pages/debt-analysis/index.tsx`
- Tabs para 4 vistas (Concesionarios/Locales/Solventes/Gráficas)
- Componente `FilterPanel` reutilizable
- Componente `DebtTable` con paginación
- React Query para estado
- Shadcn/ui para UI

---

## 📊 Diferenciación con Módulos Existentes

| Característica   | Dashboard        | Análisis Deuda    | Perfil Económico     |
| ---------------- | ---------------- | ----------------- | -------------------- |
| **Vista**        | Top 10           | Todos (paginado)  | Individual           |
| **Filtrado**     | ❌ No            | ✅ Avanzado       | ✅ Avanzado          |
| **Alcance**      | Resumen          | Agregado          | Transaccional        |
| **Exportación**  | ❌ No            | ✅ CSV masivo     | ✅ CSV individual    |
| **Gráficas**     | ✅ Ranking       | ✅ Distribución   | ❌ No                |
| **Casos de uso** | "¿Cómo estamos?" | "¿Quiénes son?"   | "¿Qué debe [X]?"     |
| **Audiencia**    | Directores       | Gerentes cobranza | Analistas/contadores |

### **NO hay redundancia:**

- Dashboard: Vista ejecutiva rápida
- Análisis Deuda: Vista gerencial completa
- Perfil Económico: Vista contable detallada

---

## ✅ Ventajas

1. **Visibilidad completa** - Ver todos los morosos, no solo Top 10
2. **Filtrado flexible** - Por mercado, deuda, días, búsqueda
3. **Escalable** - Paginación maneja 1000+ registros
4. **Exportable** - CSV para análisis externo
5. **Navegación fluida** - Deep links a Perfil Económico
6. **Performance** - Queries optimizadas + caché
7. **UX moderna** - Filtros reactivos, badges visuales

---

## 📋 Plan de Implementación

### **Fase 1: Backend (2-3 días)**

- [ ] Crear `DebtAnalysisController`
- [ ] Crear `DebtAnalysisService`
- [ ] Implementar queries optimizadas
- [ ] Agregar índices DB si necesario
- [ ] Agregar rutas y permisos
- [ ] Testing con PHPUnit

### **Fase 2: Frontend (3-4 días)**

- [ ] Crear página `debt-analysis/index.tsx`
- [ ] Componente `FilterPanel`
- [ ] Componente `DebtTable` con paginación
- [ ] Componente `DistributionCharts`
- [ ] Integrar React Query
- [ ] Testing con Playwright

### **Fase 3: Integración (1 día)**

- [ ] Link desde Dashboard
- [ ] Deep links a Perfil Económico
- [ ] Breadcrumbs
- [ ] Agregar a sidebar

### **Fase 4: Mejoras Opcionales (futuro)**

- [ ] Exportación Excel (además CSV)
- [ ] Guardado de filtros favoritos
- [ ] Notificaciones email morosos críticos
- [ ] Comparación con período anterior

---

## ⏱️ Estimación

**Total: 6-9 días laborables (1.5-2 semanas)**

```
Backend:    2-3 días
Frontend:   3-4 días
Integración: 1 día
Testing:    1-2 días (paralelo)
```

---

## 🎯 Resultado Esperado

```
ANTES:
Gerente: "¿Quiénes deben más de €500 en el Norte?"
→ Buscar uno por uno en Perfil Económico (30+ búsquedas)
→ Tiempo: 30-45 minutos

DESPUÉS:
Gerente: "¿Quiénes deben más de €500 en el Norte?"
→ Análisis Deuda → Filtrar mercado=Norte, min=€500 → Ver tabla
→ Tiempo: 30 segundos
→ Exportar CSV → Enviar a equipo cobranza
```

**Productividad: +90% en análisis de deuda agregada**

---

## 📄 Documentación Completa

- **Parte 1:** `/docs/dashboard/PROPUESTA_ANALISIS_DEUDA_PARTE1.md` (diseño UI, wireframes)
- **Parte 2:** `/docs/dashboard/PROPUESTA_ANALISIS_DEUDA_PARTE2.md` (código backend/frontend)
- **Comparativa:** `/docs/dashboard/DEBT_ANALYSIS_COMPARISON.md` (vs Perfil Económico)

---

## 🚀 Próximo Paso

**¿Apruebas la propuesta?**

→ **SÍ:** Comenzar implementación (Backend primero)  
→ **NO:** Ajustar propuesta según feedback
