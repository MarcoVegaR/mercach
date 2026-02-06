# Dashboard V2 — Propuesta de Rediseño Completo

> **Fecha:** 2026-02-05  
> **Estado:** Propuesta  
> **Autor:** Análisis automatizado  
> **Stack:** React 19 · Recharts 2.15 · shadcn/ui · Tailwind 4 · OKLCH

---

## Índice

1. [Diagnóstico del estado actual](#1-diagnóstico-del-estado-actual)
2. [¿Por qué no impacta? — Comparativa con dashboards profesionales](#2-por-qué-no-impacta)
3. [Propuesta de rediseño](#3-propuesta-de-rediseño)
4. [Sistema visual](#4-sistema-visual)
5. [Componentes nuevos y rediseñados](#5-componentes-nuevos-y-rediseñados)
6. [Wireframes ASCII](#6-wireframes-ascii)
7. [Backend: cambios necesarios](#7-backend-cambios-necesarios)
8. [Plan de implementación](#8-plan-de-implementación)

---

## 1. Diagnóstico del estado actual

### 1.1 Bug crítico: Gráfica de Recaudación invisible

**Archivo:** `resources/js/components/analytics/PaymentTrendLine.tsx:26`

```tsx
// ❌ BUGGY — hsl() wrapping an oklch() value = CSS inválido
color: 'hsl(var(--chart-1))',

// ✅ CORRECTO — como lo hacen los otros 20 componentes
color: 'var(--chart-1)',
```

**Causa raíz:** `--chart-1` está definido como `oklch(0.78 0.2 255)` en `app.css`. Al envolverlo en `hsl()`, el navegador recibe `hsl(oklch(0.78 0.2 255))` que es CSS inválido → stroke transparente → línea invisible.

**Impacto:** Es el **único componente** con este bug. Los otros 20 usan `var(--chart-N)` directamente.

**Fix:** Cambiar la línea 26 de `'hsl(var(--chart-1))'` a `'var(--chart-1)'`.

### 1.2 Problemas estructurales

| Problema                                      | Dónde                                       | Impacto                    |
| --------------------------------------------- | ------------------------------------------- | -------------------------- |
| **4 tabs con duplicación masiva**             | `dashboard.tsx`                             | KPIs repetidos en 2-3 tabs |
| **18 KPI cards** en total (6+7+2+3)           | Tabs 1-4                                    | Sobrecarga cognitiva       |
| **"Deuda total"** aparece 3 veces             | Resumen, Finanzas, indirecto en Cesionarios | Confusión                  |
| **"Cesionarios morosos/solventes"** duplicado | Finanzas + Cesionarios                      | Redundante                 |
| **"Contratos vigentes"** duplicado            | Resumen + Operaciones                       | Redundante                 |
| **10+ API calls** independientes al montar    | Todas las tabs                              | Rendimiento, layout shift  |

### 1.3 Problemas visuales

| Problema                     | Detalle                                                                          |
| ---------------------------- | -------------------------------------------------------------------------------- |
| **Cards sin profundidad**    | Card bg `oklch(0.9911)` = Page bg `oklch(0.9911)` — mismo color, cero separación |
| **Emojis en headers**        | `📊📈💸💵💰🏆📄🏢` — informal para dashboard administrativo                      |
| **Iconos monótonos**         | 4 cards de deuda usan `AlertTriangle`; 3 de cesionarios usan `Users`             |
| **Donuts diminutos**         | `max-h-[250px]` en grid 3-col ≈ 300px de ancho en 1440px                         |
| **Charts bajos**             | Line chart 280px, bar charts 280-340px — poco espacio para datos                 |
| **Grid 3-col para donuts**   | Demasiado apretado, imposible leer labels                                        |
| **Sin leyendas**             | Donuts solo muestran datos en tooltip, no en leyenda visible                     |
| **Sin deltas/tendencia**     | KpiCard soporta `deltaLabel` pero **ninguna card lo usa**                        |
| **Sin sparklines**           | `KpiCardSparkline.tsx` existe pero **no se usa en dashboard.tsx**                |
| **Sin gradientes en charts** | Líneas planas sin área fill — se ve vacío                                        |

---

## 2. ¿Por qué no impacta?

### Comparativa directa: Grafana vs. Mercach actual

| Aspecto                     | Grafana / Dashboards Pro                             | Mercach actual                          |
| --------------------------- | ---------------------------------------------------- | --------------------------------------- |
| **Densidad de información** | Cada pixel comunica algo                             | Mucho espacio vacío, cards dispersas    |
| **Jerarquía visual**        | Hero chart grande + stats compactos                  | Todo del mismo tamaño, sin protagonista |
| **Color semántico**         | Verde=bueno, rojo=malo, umbrales automáticos         | Colores decorativos sin significado     |
| **Profundidad**             | Paneles con sombra/borde sutil sobre fondo oscuro    | Cards blancas sobre fondo blanco        |
| **Micro-tendencias**        | Sparklines en cada stat panel                        | Solo número + texto                     |
| **Área charts**             | Gradiente suave debajo de la línea, crea peso visual | Línea pelada (y actualmente invisible)  |
| **Tiempo global**           | Un selector de tiempo controla todo                  | Cada chart tiene su propio control      |
| **Auto-refresh**            | Indicador "Last 5m · ↻ 30s" visible                  | Sin indicador de frescura               |
| **Thresholds**              | Valores se colorean según umbral                     | Todos los números en el mismo color     |
| **Drill-down**              | Click en cualquier panel → detalle                   | Inconsistente (algunos sí, otros no)    |

### Los 5 principios que faltan

1. **"Glanceable"** — Poder entender la salud del negocio en 3 segundos sin leer texto
2. **Jerarquía F-pattern** — Lo más importante arriba-izquierda, detalle abajo-derecha
3. **Data-ink ratio** — Maximizar píxeles que muestran datos vs. decoración
4. **Progressive disclosure** — Resumen → click → detalle, no todo a la vez
5. **Emotional design** — El color del dashboard debe reflejar el estado del negocio (verde si va bien, rojo si hay alertas)

---

## 3. Propuesta de rediseño

### 3.1 Nueva arquitectura de tabs: 3 tabs (antes 4)

| Tab actual                | →   | Tab nuevo       | Razón                                      |
| ------------------------- | --- | --------------- | ------------------------------------------ |
| Resumen Ejecutivo         | →   | **Panorama**    | Nombre más conciso, contenido simplificado |
| Finanzas                  | →   | **Finanzas**    | Se mantiene, reorganizado                  |
| Operaciones + Cesionarios | →   | **Operaciones** | Merge: elimina tab de bajo valor           |

### 3.2 Tab "Panorama" — La primera impresión

**Objetivo:** Responder "¿Cómo va el negocio?" en 3 segundos.

**Contenido:**

- **Header bar** — Global refresh + "Actualizado hace X min"
- **4 stat cards con sparkline** — Deuda total, Recaudación del mes, Morosidad %, Contratos vigentes
- **Alert banner** (condicional) — Solo si hay algo urgente
- **Hero chart** — Recaudación tendencia, full-width, AreaChart con gradiente, 360px alto
- **2-col grid** — Deuda por tipo de local (donut 300px) + Cargos por estado (donut 300px)

**Lo que se elimina de Resumen actual:**

- 2 KPIs redundantes (Gastos Comunes y Alquiler fijo separados → consolidados en "Deuda total")
- Grid 3-col de donuts diminutos → 2-col más grandes
- Duplicación de "Cesionarios activos" aquí (se mueve a Operaciones)

### 3.3 Tab "Finanzas" — Deep dive financiero

**Contenido:**

- **4 stat cards** — Deuda total Bs., Deuda vencida Bs., Morosidad %, Promedio días atraso
    - Cada uno con sparkline de los últimos 6 meses
    - Deltas MoM (mes vs. mes anterior)
- **Top 10 Morosos** — Horizontal bar chart (nombres visibles en Y-axis), full-width
- **2-col** — Proyección ingresos (donut) + Top locales por aporte (stacked bar)
- **Recaudación por banco** — Con date range (existente, mejorado)
- **Tendencia recaudación** — AreaChart daily/monthly (bug fixed)
- **3-col compacto** — Cargos por tipo + Cargos por estado + Deuda por tipo (donuts mejorados)

**Lo que cambia:**

- 7 KPI cards → 4 (consolidar deudas en una sola con tooltip de desglose)
- DebtRankingBar: vertical → horizontal con labels visibles
- PaymentTrendLine: LineChart → AreaChart con gradiente

### 3.4 Tab "Operaciones" — Contratos + Locales + Cesionarios

**Contenido:**

- **4 stat cards** — Vigentes, Disponibles, Cesionarios activos, Cesionarios morosos
    - Con deltas
- **2-col** — Contratos por estado (enhanced donut) + Contratos por tipo (donut)
- **Timeline table** — Existente, se mantiene
- **2-col** — Locales por tipo + Locales por ubicación
- **Ranking cesionarios** — Por contratos/m²

**Lo que se fusiona desde tab "Cesionarios":**

- KPIs de cesionarios
- Ranking cesionarios
- Se **elimina**: "Cesionarios por tipo" donut (1 dato: 99% Natural) y "Personas naturales por documento" (V vs E, bajo valor)

---

## 4. Sistema visual

### 4.1 Fondo y profundidad

```css
/* PROPUESTO: Crear separación card vs. background */

/* Light mode */
:root {
    --background: oklch(0.965 0 0); /* página: gris muy sutil */
    --card: oklch(1 0 0); /* cards: blanco puro */
}

/* Dark mode */
.dark {
    --background: oklch(0.145 0 0); /* página: casi negro */
    --card: oklch(0.195 0 0); /* cards: gris oscuro */
}
```

**Efecto:** Las cards "flotan" sobre el fondo, creando la profundidad visual tipo Grafana.

### 4.2 Paleta de charts semántica

Además de los 5 tokens genéricos `--chart-1..5`, agregar tokens semánticos:

```css
:root {
    /* Revenue / Positive */
    --chart-revenue: oklch(0.72 0.18 160); /* emerald vibrante */

    /* Debt / Negative */
    --chart-debt: oklch(0.65 0.2 25); /* coral/rojo cálido */

    /* Neutral / Info */
    --chart-info: oklch(0.75 0.17 255); /* azul */

    /* Warning */
    --chart-warning: oklch(0.82 0.16 70); /* ámbar */

    /* Contrast accent */
    --chart-accent: oklch(0.7 0.15 300); /* violeta */
}
```

**Uso:** Recaudación siempre en `--chart-revenue`, deuda siempre en `--chart-debt`. El usuario asocia verde=ingresos, rojo=deuda instintivamente.

### 4.3 Tipografía de secciones

```
ANTES:  📊 Métricas Clave          (emoji + text-base)
DESPUÉS: Métricas clave             (text-lg font-semibold tracking-tight, sin emoji)
         ─────────── subtle line     (border-b border-border/50 + pb-2)
```

### 4.4 Spacing y tamaños

| Elemento                     | Actual         | Propuesto            |
| ---------------------------- | -------------- | -------------------- |
| Gap entre secciones          | `gap-4` (16px) | `gap-6` (24px)       |
| Chart height mínimo          | 250-280px      | 320-360px            |
| Donut max-h                  | 250px          | 300px                |
| Donut grid                   | 3-col          | 2-col                |
| KPI card padding             | `p-4`          | `p-5`                |
| Section header margin-bottom | implícito      | `mb-1` con `text-lg` |

---

## 5. Componentes nuevos y rediseñados

### 5.1 `KpiStatCard` — Stat card con sparkline (evolución de KpiCardSparkline)

Ya existe `KpiCardSparkline.tsx` (118 líneas) con soporte para sparkline. Se propone evolucionarlo:

```
┌──────────────────────────────────────┐
│ Deuda total                    ↑3.7% │  ← title + delta badge
│                                      │
│ Bs. 59,322,462.97                    │  ← valor principal (text-3xl bold)
│ € 8,234.56 · $ 1,250.00             │  ← desglose (text-xs muted)
│                                      │
│ ▁▂▃▄▅▆▇█▇▆▅▄▃▂▁▂▃▄▅▆              │  ← sparkline (AreaChart, 48px)
│ ════════════════════════════ (rojo)  │  ← borde inferior semántico
└──────────────────────────────────────┘
```

**Cambios sobre KpiCardSparkline actual:**

- Borde inferior coloreado (no lateral) — más moderno
- Background tint sutil basado en estado: `bg-destructive/3` para deuda, `bg-success/3` para positivo
- Soporte para `subtitle` con desglose multi-moneda
- Sparkline con color semántico (no siempre `--chart-1`)
- Altura sparkline: 48px (actual `min-h-[80px]` es excesivo)

**Props adicionales:**

```tsx
type KpiStatCardProps = {
    // ... existentes de KpiCardSparkline
    sparkColor?: string; // 'var(--chart-revenue)' | 'var(--chart-debt)' | etc.
    borderColor?: string; // color del borde inferior
    tintVariant?: 'success' | 'destructive' | 'warning' | 'neutral';
    subtitle?: string; // línea debajo del valor
};
```

### 5.2 `AreaTrendChart` — Reemplazo de PaymentTrendLine

Convertir de `LineChart` a `AreaChart` con gradiente:

```tsx
// Gradient definition
<defs>
  <linearGradient id="gradRevenue" x1="0" y1="0" x2="0" y2="1">
    <stop offset="5%" stopColor="var(--chart-revenue)" stopOpacity={0.25} />
    <stop offset="95%" stopColor="var(--chart-revenue)" stopOpacity={0} />
  </linearGradient>
</defs>

// Area with gradient fill
<Area
  type="monotone"
  dataKey="value"
  stroke="var(--chart-revenue)"
  fill="url(#gradRevenue)"
  strokeWidth={2.5}
  dot={{ r: 3, fill: 'var(--card)' }}
  activeDot={{ r: 5, strokeWidth: 2 }}
/>
```

**Efecto visual:** La línea tiene un "peso" visual con el gradiente debajo, similar a los panels de Grafana.

**Dimensiones:** `h-[360px]` (antes 280px), full-width, sin padding lateral excesivo.

### 5.3 `HorizontalRankingBar` — Top Morosos mejorado

Cambiar DebtRankingBar de vertical (nombres ocultos) a horizontal (nombres visibles):

```
  García & Asoc. ████████████████████ € 5,234
    López María  ██████████████████   € 4,890
  Rodríguez C.A. ████████████████     € 4,120
     Pérez J.F.  ████████████         € 3,456
        ...
```

**Implementación:** `layout="vertical"` en BarChart (Recharts), con `YAxis` mostrando nombres truncados a 20 chars.

### 5.4 `AlertBanner` — Alertas contextuales (NUEVO)

```tsx
type Alert = {
    level: 'critical' | 'warning' | 'info';
    message: string;
    href?: string;
};
```

**Condiciones para mostrar alertas:**

- Morosidad > 80% → `critical`: "89% de morosidad — X cesionarios con deuda vencida"
- Contratos que vencen en <30 días → `warning`: "X contratos vencen este mes"
- Promedio atraso > 300 días → `critical`: "Promedio de atraso: 325 días"

**Visual:**

```
┌─ ⚠ ──────────────────────────────────────────────┐
│ CRÍTICO  89% de morosidad • 242 cesionarios con  │
│          deuda vencida                     [Ver →]│
├─ ℹ ──────────────────────────────────────────────┤
│ INFO     12 contratos vencen en los próximos     │
│          30 días                           [Ver →]│
└──────────────────────────────────────────────────┘
```

Colapsable. Se oculta si no hay alertas activas.

### 5.5 `DashboardHeader` — Barra global (NUEVO)

```
┌──────────────────────────────────────────────────────┐
│  Dashboard                                           │
│  [Panorama] [Finanzas] [Operaciones]                 │
│                                                      │
│  Actualizado hace 2 min                [↻ Refrescar] │
└──────────────────────────────────────────────────────┘
```

- Muestra `generated_at` más reciente de las queries cargadas
- Calcula "hace X min" en real-time
- Botón refrescar con spinner durante invalidación

### 5.6 Donuts mejorados

**Cambios globales a todos los donuts:**

1. **Tamaño:** `max-h-[300px]` (antes 250px)
2. **Leyenda visible** debajo del chart (no solo tooltip):
    ```tsx
    <ChartLegend items={chartData.map((d) => ({ label: d.label, color: d.fill }))} />
    ```
3. **Active sector highlight:** `activeShape` con `outerRadius + 8` en hover
4. **Animation:** `isAnimationActive` con ease-in-out
5. **Grid:** Siempre 2-col (`md:grid-cols-2`), nunca 3-col

---

## 6. Wireframes ASCII

### Tab "Panorama" — Desktop (1440px+)

```
╔══════════════════════════════════════════════════════════════════╗
║  Dashboard                                                      ║
║  ● Panorama   ○ Finanzas   ○ Operaciones    Actualizado 2m  ↻  ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           ║
║  │Deuda     │ │Recaudac. │ │Morosidad │ │Contratos │           ║
║  │total     │ │del mes   │ │    %     │ │vigentes  │           ║
║  │Bs.59.3M  │ │Bs.1.79M  │ │   89%    │ │   392    │           ║
║  │▁▃▅▇▆▅▃▂ │ │▂▃▅▆▇▆▅▃ │ │▅▆▇▇▇▆▇▇ │ │▃▃▄▄▅▅▅▅ │           ║
║  │══(rojo)══│ │══(verde)═│ │══(rojo)══│ │══(azul)══│           ║
║  └──────────┘ └──────────┘ └──────────┘ └──────────┘           ║
║                                                                  ║
║  ┌─ ⚠ CRÍTICO ───────────────────────────────────────────┐     ║
║  │ 89% de morosidad · 325 días de atraso promedio  [Ver] │     ║
║  └────────────────────────────────────────────────────────┘     ║
║                                                                  ║
║  ┌──────────────────────────────────────────────────────────┐   ║
║  │  Recaudación — Tendencia mensual              [Mes|Día] │   ║
║  │                                                          │   ║
║  │                            ╱╲                            │   ║
║  │                    ╱──────╱  ╲──╲                        │   ║
║  │            ╱──────╱              ╲───╲                   │   ║
║  │    ╱──────╱░░░░░░░░░░░░░░░░░░░░░░░░░░╲──               │   ║
║  │   ╱░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░╲              │   ║
║  │  ═══════════════════════════════════════════             │   ║
║  │  Oct   Nov   Dic   Ene   Feb                            │   ║
║  │                                     Total: Bs. 1.97M    │   ║
║  └──────────────────────────────────────────────────────────┘   ║
║                                                                  ║
║  ┌──────────────────────┐  ┌──────────────────────┐            ║
║  │ Deuda por tipo local │  │ Cargos por estado    │            ║
║  │                      │  │                      │            ║
║  │    ┌──────────┐      │  │    ┌──────────┐      │            ║
║  │    │  DONUT   │      │  │    │  DONUT   │      │            ║
║  │    │ Bs.59.3M │      │  │    │  7,629   │      │            ║
║  │    └──────────┘      │  │    └──────────┘      │            ║
║  │                      │  │                      │            ║
║  │ ● Local   ● Oficina  │  │ ● Emitido ● Parcial │            ║
║  │ ● Kiosco  ● Depósito │  │ ● Pagado  ● Cancel. │            ║
║  └──────────────────────┘  └──────────────────────┘            ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝
```

### Tab "Finanzas" — Desktop

```
╔══════════════════════════════════════════════════════════════════╗
║  ○ Panorama   ● Finanzas   ○ Operaciones                       ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  Métricas de riesgo                                             ║
║  ────────────────────                                           ║
║  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           ║
║  │Deuda     │ │Deuda     │ │Morosos   │ │Prom.     │           ║
║  │total Bs. │ │vencida   │ │          │ │atraso    │           ║
║  │Bs.59.3M  │ │Bs.45.2M  │ │  242     │ │ 324.8d   │           ║
║  │▁▃▅▇▆▅▃▂ │ │▅▆▇▇▆▅▆▇ │ │▅▆▇▇▇▆▇▇ │ │▇▇▇▆▆▅▅▄ │           ║
║  │══(rojo)══│ │══(rojo)══│ │══(rojo)══│ │══(ambar)═│           ║
║  └──────────┘ └──────────┘ └──────────┘ └──────────┘           ║
║                                                                  ║
║  Top 10 morosos                                                 ║
║  ────────────────────                                           ║
║  ┌──────────────────────────────────────────────────────────┐   ║
║  │ García & Asoc.    ████████████████████████████ € 5,234   │   ║
║  │ López María       █████████████████████████    € 4,890   │   ║
║  │ Rodríguez C.A.    ████████████████████         € 4,120   │   ║
║  │ Pérez J.F.        ██████████████████           € 3,456   │   ║
║  │ Martínez L.       █████████████████            € 3,200   │   ║
║  │ ...                                                      │   ║
║  └──────────────────────────────────────────────────────────┘   ║
║                                                                  ║
║  ┌──────────────────────┐  ┌──────────────────────┐            ║
║  │ Proyección ingresos  │  │ Top 10 locales       │            ║
║  │ (donut por tipo)     │  │ (stacked bar)        │            ║
║  └──────────────────────┘  └──────────────────────┘            ║
║                                                                  ║
║  ┌──────────────────────────────────────────────────────────┐   ║
║  │ Recaudación por banco y método      [Desde] [Hasta] [→] │   ║
║  └──────────────────────────────────────────────────────────┘   ║
║                                                                  ║
║  ┌──────────────────────────────────────────────────────────┐   ║
║  │ Tendencia de recaudación                     [Mes|Día]   │   ║
║  │ (AreaChart con gradiente, 360px)                         │   ║
║  └──────────────────────────────────────────────────────────┘   ║
║                                                                  ║
║  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐         ║
║  │Cargos por tipo│ │Cargos estado  │ │Deuda por tipo │         ║
║  │  (donut)      │ │  (donut)      │ │  (donut)      │         ║
║  └───────────────┘ └───────────────┘ └───────────────┘         ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝
```

### Tab "Operaciones" — Desktop

```
╔══════════════════════════════════════════════════════════════════╗
║  ○ Panorama   ○ Finanzas   ● Operaciones                       ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           ║
║  │Contratos │ │Locales   │ │Cesion.   │ │Cesion.   │           ║
║  │vigentes  │ │disponib. │ │activos   │ │morosos   │           ║
║  │   392    │ │   130    │ │   270    │ │   242    │           ║
║  │▃▃▄▄▅▅▅▅ │ │▅▅▄▃▃▃▂▂ │ │▃▄▄▅▅▅▅▅ │ │▅▆▇▇▇▆▇▇ │           ║
║  │══(azul)══│ │═(neutro)═│ │══(azul)══│ │══(rojo)══│           ║
║  └──────────┘ └──────────┘ └──────────┘ └──────────┘           ║
║                                                                  ║
║  Gestión de contratos                                           ║
║  ────────────────────                                           ║
║  ┌──────────────────────┐  ┌──────────────────────┐            ║
║  │ Contratos por estado │  │ Contratos por tipo   │            ║
║  │ (enhanced donut)     │  │ (donut)              │            ║
║  └──────────────────────┘  └──────────────────────┘            ║
║                                                                  ║
║  ┌──────────────────────────────────────────────────────────┐   ║
║  │ Timeline de contratos                                    │   ║
║  │ (tabla con progress bars)                                │   ║
║  └──────────────────────────────────────────────────────────┘   ║
║                                                                  ║
║  Infraestructura                                                ║
║  ────────────────────                                           ║
║  ┌──────────────────────┐  ┌──────────────────────┐            ║
║  │ Locales por tipo     │  │ Locales por ubicación│            ║
║  │ (donut)              │  │ (bar chart)          │            ║
║  └──────────────────────┘  └──────────────────────┘            ║
║                                                                  ║
║  Top cesionarios                                                ║
║  ────────────────────                                           ║
║  ┌──────────────────────────────────────────────────────────┐   ║
║  │ Ranking por contratos / m²                               │   ║
║  └──────────────────────────────────────────────────────────┘   ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝
```

---

## 7. Backend: cambios necesarios

### 7.1 Sparkline data en KPIs

Agregar un array `trend` a la respuesta de `/api/dashboard/kpis` y `/api/dashboard/debt/metrics`:

```json
{
    "total_debt_bs_minor": 5932246297,
    "total_debt_trend_6m": [
        { "month": "2025-09", "value_minor": 4800000000 },
        { "month": "2025-10", "value_minor": 5100000000 },
        { "month": "2025-11", "value_minor": 5400000000 },
        { "month": "2025-12", "value_minor": 5600000000 },
        { "month": "2026-01", "value_minor": 5800000000 },
        { "month": "2026-02", "value_minor": 5932246297 }
    ],
    "total_debt_delta_pct": 2.3,
    "total_debt_delta_direction": "up"
}
```

**Implementación backend:** Calcular snapshot mensual de deuda total. Puede usar tabla `charges` con `DATE_TRUNC('month', due_on)` agrupado, comparando saldo abierto al final de cada mes. Cachear con `staleTime` largo (5 min).

### 7.2 Delta MoM (Month-over-Month)

Para cada KPI, calcular:

```php
$delta_pct = $current > 0 && $previous > 0
    ? round(($current - $previous) / $previous * 100, 1)
    : 0;
$delta_direction = $delta_pct > 0 ? 'up' : ($delta_pct < 0 ? 'down' : 'neutral');
```

### 7.3 Alertas endpoint

Nuevo endpoint `/api/dashboard/alerts`:

```json
{
    "alerts": [
        {
            "level": "critical",
            "code": "HIGH_DELINQUENCY",
            "message": "89% de morosidad — 242 cesionarios con deuda vencida",
            "href": "/admin/economic-profile"
        },
        {
            "level": "warning",
            "code": "CONTRACTS_EXPIRING",
            "message": "12 contratos vencen en los próximos 30 días",
            "href": "/catalogs/contract?filters[expiring]=30"
        }
    ]
}
```

**Reglas:**
| Condición | Nivel | Mensaje |
|---|---|---|
| `morosidad_rate > 75%` | critical | "X% de morosidad — Y cesionarios" |
| `average_days_overdue > 300` | critical | "Promedio de atraso: X días" |
| Contratos vencen en <30d | warning | "X contratos vencen este mes" |
| `solvent_count / total < 0.2` | warning | "Solo X% de cesionarios solventes" |

### 7.4 Consolidar endpoints (opcional, Fase 5)

Actual: 10+ calls. Propuesto:

- `/api/dashboard/overview` → kpis + sparklines + alerts + revenue summary
- `/api/dashboard/finance` → debt metrics + sparklines + revenue breakdown
- `/api/dashboard/operations` → contracts + locals + concessionaires

Esto reduce latencia y layout shift.

---

## 8. Plan de implementación

### Fase 0: Bug fix (30 min) ← **HACER PRIMERO**

- [ ] `PaymentTrendLine.tsx:26` — cambiar `hsl(var(--chart-1))` → `var(--chart-1)`
- [ ] Verificar que la línea se renderiza correctamente

### Fase 1: Fundación visual (2-3 horas)

- [ ] **CSS:** Ajustar `--background` para crear separación con `--card`
- [ ] **CSS:** Agregar tokens semánticos `--chart-revenue`, `--chart-debt`, etc.
- [ ] **Headers:** Eliminar emojis de todas las secciones (9 emojis en `dashboard.tsx`)
- [ ] **Spacing:** `gap-4` → `gap-6` entre secciones
- [ ] **Charts:** Aumentar heights (280px → 320-360px)
- [ ] **Donuts:** `max-h-[250px]` → `max-h-[300px]`, grid 3-col → 2-col
- [ ] **Leyendas:** Agregar `ChartLegend` debajo de cada donut

### Fase 2: KpiStatCard con sparklines (3-4 horas)

- [ ] Evolucionar `KpiCardSparkline.tsx` con `tintVariant`, `sparkColor`, `borderColor`, `subtitle`
- [ ] Backend: agregar `trend_6m` y `delta_pct` a endpoints de KPIs
- [ ] Reemplazar todas las `KpiCard` en `dashboard.tsx` por `KpiStatCard`
- [ ] Iconos diferenciados: `Banknote` (deuda EUR), `DollarSign` (USD), `TrendingUp` (recaudación), `Shield` (solventes), `Calendar` (contratos)
- [ ] Borde inferior coloreado en vez de lateral

### Fase 3: Charts pro (3-4 horas)

- [ ] `PaymentTrendLine` → `AreaChart` con gradiente (defs + linearGradient)
- [ ] `DebtRankingBar` → layout horizontal con Y-axis labels visibles
- [ ] `TopRevenueLocalsBar` → ajustar para labels visibles
- [ ] Todos los donuts: active sector highlight, legends, tamaño mayor
- [ ] Color semántico en todas las gráficas (revenue=verde, debt=rojo)

### Fase 4: Reestructuración de tabs (2-3 horas)

- [ ] Merge 4 tabs → 3 tabs (Panorama, Finanzas, Operaciones)
- [ ] Eliminar duplicación de KPIs
- [ ] Nuevo layout per wireframes
- [ ] `DashboardHeader` con timestamp "Actualizado hace X min"
- [ ] `AlertBanner` condicional

### Fase 5: Backend optimization (2-3 horas)

- [ ] Endpoint `/api/dashboard/alerts`
- [ ] Sparkline data en KPI responses
- [ ] Delta MoM calculation
- [ ] (Opcional) Consolidar endpoints

---

## Resumen de impacto esperado

| Métrica                                     | Antes                      | Después                     |
| ------------------------------------------- | -------------------------- | --------------------------- |
| **Tiempo para entender estado del negocio** | ~30s (leer múltiples KPIs) | ~3s (color + sparklines)    |
| **Tabs**                                    | 4 (con duplicación)        | 3 (sin duplicación)         |
| **KPI cards totales**                       | 18 (repetidas)             | 12 (únicas, con sparklines) |
| **Charts visibles por tab**                 | 3-5 pequeños               | 2-3 grandes + stats         |
| **Emojis**                                  | 9                          | 0                           |
| **Gráficas rotas**                          | 1 (line invisible)         | 0                           |
| **Información drill-down**                  | Inconsistente              | Todos clickeables           |
| **Feedback de frescura**                    | Ninguno                    | "Actualizado hace X min"    |
| **Alertas proactivas**                      | 0                          | Contextuales automáticas    |
| **Profundidad visual**                      | Plano (mismo bg)           | Cards flotan sobre fondo    |
| **Color semántico**                         | Decorativo                 | Significativo (verde/rojo)  |

---

## Archivos impactados

### Modificaciones:

- `resources/css/app.css` — tokens de fondo + chart semánticos
- `resources/js/pages/dashboard.tsx` — layout completo, 3 tabs
- `resources/js/components/analytics/KpiCardSparkline.tsx` — evolucionar
- `resources/js/components/analytics/PaymentTrendLine.tsx` — AreaChart + fix bug
- `resources/js/components/analytics/DebtRankingBar.tsx` — horizontal layout
- `resources/js/components/analytics/ChargesByStatusDonut.tsx` — legends + size
- `resources/js/components/analytics/DebtByLocalTypeDonut.tsx` — legends + size
- (Todos los donuts — legends + size)
- `app/Services/DashboardService.php` — sparkline data + deltas
- `app/Http/Controllers/Api/DashboardApiController.php` — alerts endpoint

### Nuevos:

- `resources/js/components/analytics/AlertBanner.tsx`
- `resources/js/components/analytics/DashboardHeader.tsx`

### Eliminados (merge):

- Tab "Cesionarios" como tab independiente
- `ConcessionairesByTypeDonut.tsx` (bajo valor) — se puede mantener pero remover del dashboard
- `ConcessionairesNaturalByDocBar.tsx` (bajo valor) — idem
