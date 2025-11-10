# Análisis Comparativo: Dashboard Deudas vs Perfil Económico

## 📊 Resumen Ejecutivo

**CONCLUSIÓN: NO SON REDUNDANTES - SON COMPLEMENTARIOS Y DEBEN INTEGRARSE**

El **Dashboard de Deudas** y el **Perfil Económico** tienen propósitos y audiencias diferentes:

```
┌─────────────────────────────────────────────────────────────────┐
│ DASHBOARD - Análisis de Deudas (Tab "Deudas y Riesgo")        │
├─────────────────────────────────────────────────────────────────┤
│ PROPÓSITO: Monitoreo ejecutivo agregado                        │
│ AUDIENCIA: Directores, gerentes, tomadores de decisión         │
│ FRECUENCIA: Consulta diaria (snapshot rápido)                  │
│ NIVEL: Vista de pájaro (bird's eye view)                       │
│                                                                 │
│ FUNCIONALIDAD ACTUAL:                                          │
│ ✓ KPIs agregados (deuda total, morosos, tasa morosidad)       │
│ ✓ Top 10 morosos con severidad visual                         │
│ ✓ Métricas de riesgo en tiempo real                           │
│ ✓ Sin filtros (datos del día actual)                          │
│                                                                 │
│ LO QUE FALTA (endpoints creados, no UI):                       │
│ - Ranking completo de morosos (paginado)                      │
│ - Deuda por local (agregado)                                  │
│ - Lista de solventes                                           │
└─────────────────────────────────────────────────────────────────┘
                            ↓ Link directo
┌─────────────────────────────────────────────────────────────────┐
│ PERFIL ECONÓMICO (Módulo completo existente)                   │
├─────────────────────────────────────────────────────────────────┤
│ PROPÓSITO: Análisis profundo individual con contexto completo  │
│ AUDIENCIA: Analistas financieros, contadores, cobradores       │
│ FRECUENCIA: Consulta según necesidad (drill-down)             │
│ NIVEL: Vista granular (transaction-level detail)              │
│                                                                 │
│ FUNCIONALIDAD:                                                  │
│ ✓ Búsqueda individual (concessionaire o local)                │
│ ✓ Desglose cargo por cargo (ISSUED/PARTIAL)                   │
│ ✓ Allocations aplicadas y pendientes                          │
│ ✓ Credits abiertos con aplicaciones                           │
│ ✓ Payments parciales disponibles                              │
│ ✓ Aging detallado (0-30, 31-60, 61-90, 90+)                  │
│ ✓ Conversión FX con tasas históricas                          │
│ ✓ Filtros avanzados:                                          │
│   - Por moneda (EUR/USD/VES)                                   │
│   - Por tipo de cargo (RENT_EUR_FIXED, CONDO_USD, etc.)      │
│   - Por período (mes inicio - mes fin)                        │
│   - Solo vencidos (overdue_only)                              │
│ ✓ Vista by_local (desglose por espacio)                       │
│ ✓ Resumen en múltiples monedas                                │
│ ✓ Eventos recientes (timeline)                                │
│ ✓ Exportación CSV/JSON                                        │
│ ✓ Snapshot a fecha específica ("at date")                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔍 Análisis Detallado de Diferencias

### **1. SCOPE (Alcance)**

| Aspecto          | Dashboard Deudas                 | Perfil Económico                       |
| ---------------- | -------------------------------- | -------------------------------------- |
| **Vista**        | Agregada (todos vs todos)        | Individual (1 concesionario o 1 local) |
| **Datos**        | Solo ISSUED/PARTIAL vencidos HOY | ISSUED/PARTIAL con filtros históricos  |
| **Granularidad** | Totales y top-N                  | Cargo por cargo con detalle            |
| **Contexto**     | Riesgo global del negocio        | Situación completa de 1 entidad        |

### **2. CÁLCULO DE DEUDA**

#### Dashboard (simplificado):

```php
// Calcula deuda vencida rápido (optimizado para velocidad)
SUM(ch.amount_minor * fx_rate) - SUM(allocations)
WHERE due_on < TODAY
GROUP BY concessionaire
```

#### Perfil Económico (completo):

```php
// Calcula deuda con contexto completo
FOR EACH charge:
  amount_bs = charge.amount_bs_minor_issued ?? (amount_minor * fx_rate_at_date)
  allocated = SUM(payment_allocations.amount_bs_minor)
  credited = SUM(credit_applications with FX conversion)
  outstanding = MAX(0, amount_bs - allocated - credited)

  // Aging buckets
  IF overdue:
    days = TODAY - due_on
    aging[bucket] += outstanding

  // By local breakdown
  by_local[local_id] += outstanding
```

**Diferencia clave:** Perfil Económico considera **credits** (no solo allocations), convierte FX históricamente, y da contexto completo.

### **3. FEATURES EXCLUSIVAS**

#### Solo en Dashboard:

- ✅ Vista comparativa (todos los morosos vs solventes)
- ✅ Severidad visual por color (crítico/alto/medio)
- ✅ Tasa de morosidad % del total
- ✅ Promedio días atraso global
- ✅ Integración con otros KPIs (contratos, locales, pagos)

#### Solo en Perfil Económico:

- ✅ **Credits abiertos** (customer_credits + credit_applications)
- ✅ **Payments parciales** disponibles para aplicar
- ✅ **Desglose by_local** dentro de un concesionario
- ✅ **Aging distribution** (0-30, 31-60, 61-90, 90+)
- ✅ **Resumen multi-moneda** (rent en EUR, condo en USD)
- ✅ **Eventos recientes** (timeline de cambios)
- ✅ **Snapshot histórico** (ver deuda a fecha pasada)
- ✅ **Filtros avanzados** (moneda, tipo, período)
- ✅ **Exportación** contable (CSV/JSON)

### **4. CASOS DE USO**

#### Dashboard - "¿Cómo estamos?"

```
Gerente General: "¿Cuánto deben en total?"
→ Dashboard: € 102,698.45 vencidos, 267 morosos

CFO: "¿Quiénes son los 10 peores?"
→ Dashboard: Top 10 con barras rojas (>90 días)

Director: "¿Está empeorando?"
→ Dashboard: Tasa morosidad 38.5% (badge rojo)
```

#### Perfil Económico - "¿Qué pasa con [X]?"

```
Cobrador: "¿Cuánto debe exactamente GRUPO CHILANGO?"
→ Perfil: Buscar → Ver 2 locales, €1,330 (LOCAL 9: €370, LOCAL 10: €960)
          → Ver 15 cargos vencidos con fechas
          → Ver que tiene 1 pago parcial disponible Bs. 5,000

Contador: "¿Qué debe el LOCAL 25 de condominio?"
→ Perfil: Buscar LOCAL 25 → Filtrar kind=CONDO_USD
         → Ver $450 USD en 3 cargos (ago, sep, oct)
         → Ver aging: $200 (31-60 días), $250 (61-90 días)
         → Exportar CSV para contabilidad

Analista: "¿Cuánto debía este cliente en enero?"
→ Perfil: Buscar → Cambiar date "at" → 2025-01-31
          → Ver snapshot histórico con FX rate de enero
```

---

## 💡 Estrategia de Integración Recomendada

### **OPCIÓN 1: Integración Progresiva (RECOMENDADA)**

**Fase actual (implementada):**

```
Dashboard Tab "Deudas" → Botón "Ir a Perfil Económico"
```

**Mejora incremental:**

1. Agregar deep links desde Dashboard a Perfil Económico:

    ```tsx
    // En DebtRankingBar, al hacer click en barra:
    onClick={() => router.visit(`/admin/economic-profile/concessionaires/${id}`)}

    // En KPI "Morosos", abrir filtrado:
    href="/admin/economic-profile?type=concessionaire&overdue_only=true"
    ```

2. Agregar micro-widget en Dashboard Tab "Deudas":

    ```tsx
    // Componente rápido de búsqueda
    <QuickDebtSearch>
        <Input placeholder="Buscar concesionario o local..." />→ Abre Perfil Económico con resultado
    </QuickDebtSearch>
    ```

3. Agregar atajos visuales:
    ```tsx
    // Badge en Top 10 Morosos:
    <Badge>Ver detalle completo →</Badge>
    // Click abre Perfil Económico preconfigurado
    ```

### **OPCIÓN 2: Fusión Completa (NO RECOMENDADA)**

**Por qué NO fusionar:**

- ❌ Dashboard se volvería lento (queries complejas con FX + credits + allocations)
- ❌ Violación del principio "progressive disclosure"
- ❌ Sobrecarga cognitiva (demasiada info en snapshot ejecutivo)
- ❌ Se perdería velocidad de carga del dashboard
- ❌ Mobile UX se degradaría

### **OPCIÓN 3: Tabs Anidados (POSIBLE, NO NECESARIO)**

Agregar sub-tabs en "Deudas":

```
Tab: Deudas y Riesgo
  ├── Sub-tab: Resumen (actual)
  ├── Sub-tab: Por Concesionario (tabla paginada)
  ├── Sub-tab: Por Local (tabla paginada)
  └── Sub-tab: Solventes (lista)
```

**Evaluación:** Posible pero redundante, mejor usar Perfil Económico existente.

---

## 🎯 Plan de Acción Recomendado

### **Corto plazo (implementar ahora):**

1. ✅ **Mantener ambos módulos separados** (ya está así)
2. ✅ **Dashboard → Perfil Económico con deep links:**

    ```tsx
    // En DebtRankingBar.tsx, agregar href a cada barra:
    onClick={() => window.location.href = `/admin/economic-profile/concessionaires/${id}`}
    ```

3. ✅ **Agregar quick search en tab Deudas:**
    ```tsx
    <Card>
        <Input placeholder="🔍 Buscar concesionario o local específico..." onEnter={() => router.visit('/admin/economic-profile')} />
    </Card>
    ```

### **Mediano plazo (próximas mejoras):**

4. **Agregar filtros básicos en Perfil Económico desde Dashboard:**

    ```
    /admin/economic-profile?overdue_only=true&min_debt_eur=500
    ```

5. **Agregar botón "Exportar morosos" en Dashboard:**

    ```tsx
    <Button
        onClick={() => {
            const ids = topMorosos.map((m) => m.id).join(',');
            window.open(`/admin/economic-profile/export?ids=${ids}&format=csv`);
        }}
    >
        Exportar Top 10 (CSV)
    </Button>
    ```

6. **Mejorar breadcrumbs:**
    ```
    Dashboard → Deudas → Ver detalle GRUPO CHILANGO (Perfil Económico)
    [<- Volver al Dashboard]
    ```

### **Largo plazo (opcional):**

7. **API unificada** (si se necesita en otros módulos):

    ```php
    // Nueva facade que combine ambos:
    DebtAnalyticsService::summary() // Dashboard data
    DebtAnalyticsService::detail($id) // Perfil Económico data
    ```

8. **Widget embebido** (solo si se requiere):
    ```tsx
    // Mini perfil económico en modal desde Dashboard
    <DebtDetailModal concessionaireId={id} />
    ```

---

## 📊 Tabla Comparativa Final

| Característica             | Dashboard Deudas    | Perfil Económico          | Redundancia |
| -------------------------- | ------------------- | ------------------------- | ----------- |
| **Vista agregada global**  | ✅ Sí               | ❌ No                     | ❌ No       |
| **Top-N morosos**          | ✅ Sí (10)          | ❌ No                     | ❌ No       |
| **KPIs de riesgo**         | ✅ Sí               | ❌ No                     | ❌ No       |
| **Búsqueda individual**    | ❌ No               | ✅ Sí                     | ❌ No       |
| **Desglose cargo x cargo** | ❌ No               | ✅ Sí                     | ❌ No       |
| **Credits y allocations**  | ⚠️ Solo allocations | ✅ Ambos                  | ⚠️ Parcial  |
| **Filtros avanzados**      | ❌ No               | ✅ Sí                     | ❌ No       |
| **Snapshot histórico**     | ❌ No               | ✅ Sí                     | ❌ No       |
| **Exportación**            | ❌ No               | ✅ Sí (CSV/JSON)          | ❌ No       |
| **Aging distribution**     | ❌ No               | ✅ Sí (4 buckets)         | ❌ No       |
| **Multi-moneda summary**   | ⚠️ Solo Bs          | ✅ EUR+USD+Bs             | ⚠️ Parcial  |
| **Velocidad de carga**     | ✅ Rápido (caché)   | ⚠️ Más lento (FX+credits) | -           |
| **Complejidad UI**         | ✅ Simple           | ⚠️ Complejo               | -           |

**REDUNDANCIA TOTAL: < 10%** (solo en concepto básico de "suma de deuda")

---

## 🏆 Conclusión Final

### **DECISIÓN: MANTENER AMBOS + INTEGRAR CON DEEP LINKS**

**Razones:**

1. ✅ **Audiencias diferentes** (ejecutivos vs analistas)
2. ✅ **Propósitos diferentes** (snapshot vs drill-down)
3. ✅ **Complejidad diferente** (KPIs vs transacciones)
4. ✅ **Performance diferente** (rápido vs completo)
5. ✅ **NO hay redundancia significativa**

**Implementación:**

```tsx
// Dashboard → Deep link en cada elemento clickeable
<DebtRankingBar
  onBarClick={(id) => `/admin/economic-profile/concessionaires/${id}`}
/>

<KpiCard
  title="Concesionarios morosos"
  href="/admin/economic-profile?overdue_only=true"
/>

// Tab Deudas → Quick search
<SearchBox
  placeholder="Buscar análisis detallado..."
  action="/admin/economic-profile"
/>
```

**Beneficio:** Mejor experiencia de usuario con navegación fluida entre resumen ejecutivo y análisis profundo, sin duplicar código ni complejidad.

---

## 📚 Referencias

**Dashboard de Deudas:**

- Endpoints: `/api/dashboard/debt/*`
- Componentes: `DebtRankingBar`, KPIs en tab "Deudas"
- Servicio: `DashboardService::getDebtMetrics()`

**Perfil Económico:**

- Endpoints: `/admin/economic-profile/*`
- Componente: `resources/js/pages/admin/economic-profile/`
- Servicio: `EconomicProfileService` (implementa `EconomicProfileServiceInterface`)
- Queries complejas: `loadChargesDataForLocals()`, considera allocations + credits + FX

**Documentación:**

- `/docs/dashboard/DASHBOARD_V2_REDESIGN.md`
- Este archivo: `/docs/dashboard/DEBT_ANALYSIS_COMPARISON.md`
