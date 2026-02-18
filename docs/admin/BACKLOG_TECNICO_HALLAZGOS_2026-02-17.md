# Backlog técnico de corrección - Hallazgos del sistema

**Fecha:** 2026-02-17  
**Base:** hallazgos funcionales consolidados en `SEGUIMIENTO_HALLAZGOS_SISTEMA_2026-02-17.md`  
**Objetivo:** convertir observaciones en problemas técnicos reales, con causa raíz y plan de corrección ejecutable.

---

## 1) Resumen ejecutivo

Se identifican **8 ítems técnicos**:

- **3 bugs/lógica de negocio**
- **3 brechas de contrato API/UI (datos incompletos o ambiguos)**
- **2 mejoras UX/visualización**

Priorización sugerida:

- **P0 (crítico):** BT-03, BT-07
- **P1 (alto):** BT-02, BT-04, BT-08
- **P2 (medio):** BT-01, BT-05, BT-06

---

## 2) Backlog técnico detallado

## BT-01 - Tarjeta "Deuda total" sin desglose por concepto

**Tipo:** Brecha de contrato API/UI  
**Prioridad:** P2

### Problema real

La tarjeta muestra total en Bs y subtítulo por moneda (`€` y `$`), pero **no explica composición por concepto** (M2, fija, condominio, etc.).

### Evidencia técnica

- UI usa solo totales por moneda en @resources/js/pages/dashboard.tsx#231-239
- API de deuda devuelve agregados globales; no devuelve un `breakdown_by_kind` completo para la tarjeta: @app/Services/DashboardService.php#1067-1099

### Causa raíz

Contrato del endpoint orientado a totales, no a explicación financiera por rubro.

### Corrección propuesta

1. Extender `getDebtMetrics()` para incluir:
    - `debt_by_kind`: `{ kind, currency, amount_minor, amount_bs_minor }[]`
    - `overdue_by_kind`: mismo esquema.
2. Mostrar en UI un tooltip/popover en la tarjeta con desglose por rubro.
3. Mantener backward compatibility de campos actuales.

### Criterio de aceptación

- La tarjeta permite ver claramente cuánto corresponde a cada rubro y moneda.
- La suma del desglose coincide exactamente con total deuda.

---

## BT-02 - KPI "Recaudación del mes" usa proyección pero etiqueta de recaudación real

**Tipo:** Ambigüedad funcional + wiring UI/API  
**Prioridad:** P1

### Problema real

La tarjeta titulada "Recaudación del mes" está alimentada por **proyección contractual**, no por recaudación efectivamente cobrada.

### Evidencia técnica

- Query del KPI llama `/api/dashboard/revenue/projection`: @resources/js/pages/dashboard.tsx#122-131
- Card usa ese resultado bajo el título "Recaudación del mes": @resources/js/pages/dashboard.tsx#249-257
- El endpoint de proyección calcula ingresos estimados por contratos: @app/Services/DashboardService.php#1206-1337

### Causa raíz

Desalineación entre nombre del KPI (recaudación real) y semántica del dato (proyección).

### Corrección propuesta

Definir una de estas dos rutas (decisión de producto):

1. **Ruta A (rápida):** renombrar KPI a "Proyección mensual" / "Proyección de ingresos".
2. **Ruta B (negocio):** mantener nombre actual y cambiar fuente a cobros reales (`payments`) del mes.

> Recomendación: aplicar A inmediatamente y planificar B si se requiere KPI de caja real.

### Criterio de aceptación

- El título, tooltip y fuente de datos cuentan la misma historia.

---

## BT-03 - Discrepancia "Deuda total" vs "Deuda vencida"

**Tipo:** Regla de vencimiento inconsistente / semántica no explicitada  
**Prioridad:** P0

### Problema real

En Finanzas, deuda total y deuda vencida divergen. A nivel técnico, no siempre es error: total = abierta; vencida = due_on pasado. El punto crítico es que **las reglas de `due_on` no son homogéneas** entre tipos de cargo.

### Evidencia técnica

- Deuda vencida filtra `due_on < hoy`: @app/Services/DashboardService.php#734-739
- Deuda total usa cargos abiertos sin filtro de vencimiento: @app/Services/DashboardService.php#741-745
- `due_on` varía por calculadora:
    - CONDO: día 5: @app/Services/Charges/CondoUsdCalculator.php#30-32
    - M2: día 6: @app/Services/Charges/RentM2Calculator.php#31-33
    - Fija: `due_on = billing_day` (puede ser cualquier día): @app/Services/Charges/RentFixedCalculator.php#186-190

### Causa raíz

Regla de vencimiento distinta por tipo/modalidad + ausencia de explicación en UI.

### Corrección propuesta

1. Cerrar regla única de negocio (ej. todos vencen día 5, o mantener por modalidad).
2. Si se unifica: actualizar calculadoras y regeneración hacia adelante.
3. Si se mantiene diferenciado: agregar tooltip en KPI con definición formal de "deuda vencida".
4. Agregar prueba de regresión de métricas con fecha de corte.

### Criterio de aceptación

- Discrepancia solo existe cuando está respaldada por regla explícita y visible.

---

## BT-04 - Proyección financiera sin estrategia multimoneda explícita

**Tipo:** Modelo financiero inconsistente entre módulos  
**Prioridad:** P1

### Problema real

Las vistas de proyección/top aporte trabajan en EUR, pero el dominio de deuda y cargos maneja EUR + USD. Esto genera interpretación inconsistente cuando se comparan widgets.

### Evidencia técnica

- Proyección por tipo devuelve solo `amount_eur_minor`: @app/Services/DashboardService.php#1211-1337
- Top locales por aporte devuelve solo valores en EUR: @app/Services/DashboardService.php#1341-1462
- UI de ambos componentes asume EUR:
    - @resources/js/components/analytics/ProjectedRevenueByLocalTypeDonut.tsx#8-16
    - @resources/js/components/analytics/TopRevenueLocalsBar.tsx#10-17
- Deuda y top morosos sí manejan EUR+USD (convertidos a Bs):
    - @app/Services/DashboardService.php#1067-1099
    - @app/Http/Controllers/Api/DashboardDebtRankingController.php#109-117

### Causa raíz

No existe contrato transversal de "moneda base de dashboard" para comparar KPIs.

### Corrección propuesta

1. Definir moneda base de dashboard (recomendado: Bs para ranking/comparables).
2. En proyección/top aporte, exponer:
    - montos originales por moneda,
    - monto normalizado (Bs) con tasa y fecha.
3. Mostrar en UI la tasa aplicada y fecha de corte.

### Criterio de aceptación

- KPIs comparables entre sí en una moneda base clara.

---

## BT-05 - "Top morosos" no muestra desglose por locales

**Tipo:** Gap de datos para trazabilidad operativa  
**Prioridad:** P2

### Problema real

El tooltip de Top morosos no trae locales ni distribución de deuda por local/concepto.

### Evidencia técnica

- Endpoint devuelve solo `id`, `name`, deuda agregada y días: @app/Http/Controllers/Api/DashboardDebtRankingController.php#106-139
- Tooltip actual muestra solo deuda y atraso: @resources/js/components/analytics/DebtRankingBar.tsx#214-230

### Causa raíz

El endpoint está modelado para ranking agregado, no para drill-down.

### Corrección propuesta

1. Extender endpoint con payload ligero por cada moroso:
    - `top_locals: [{local_id, local_code, debt_bs_minor, debt_pct}]` (ej. top 3).
2. Mostrar ese resumen en tooltip expandido.
3. Mantener caché y límites para no penalizar performance.

### Criterio de aceptación

- En hover se entienden rápidamente origen y distribución de la deuda.

---

## BT-06 - Gráfica "Cargos por estatus" pierde visibilidad en categorías pequeñas

**Tipo:** UX/visualización  
**Prioridad:** P2

### Problema real

Estados minoritarios (anulados/parciales) son casi imperceptibles visualmente.

### Evidencia técnica

- Donut sin `minAngle`, sin etiquetas de valor por segmento y con leyenda básica: @resources/js/components/analytics/ChargesByStatusDonut.tsx#61-89

### Causa raíz

Configuración visual no optimizada para long-tail (categorías pequeñas).

### Corrección propuesta

1. Añadir `minAngle` y `paddingAngle` al `Pie`.
2. Incluir leyenda con valor + porcentaje.
3. (Opcional) fallback a barra horizontal cuando haya alta asimetría.

### Criterio de aceptación

- Se distinguen claramente todos los estados presentes, incluso con pocos casos.

---

## BT-07 - Conteo de "Contratos activos" en index usa `is_active` y puede incluir terminados

**Tipo:** Bug de métrica  
**Prioridad:** P0

### Problema real

La tarjeta de "Contrato Activos" usa `is_active=true`, no estatus funcional del contrato, por lo que puede incluir terminados/no operativos.

### Evidencia técnica

- Cálculo actual: `where('is_active', true)`: @app/Services/ContractService.php#404-410
- Card del index consume `stats.active`: @resources/js/pages/catalogs/contract/index.tsx#246-251

### Causa raíz

Se confundió estado técnico del registro (`is_active`) con estado de negocio del contrato (`contract_status`).

### Corrección propuesta

1. Redefinir `stats.active` por `contract_status.code` (excluir `TERM`, `BORR`).
2. Alinear definición con negocio (si `VENC` cuenta o no como activo, dejar explícito).
3. Agregar test de servicio/controlador para la métrica.

### Criterio de aceptación

- Un contrato `TERM` no aparece contado como activo.

---

## BT-08 - Columna "Local" en pagos no refleja locales realmente involucrados

**Tipo:** Gap funcional en index de pagos  
**Prioridad:** P1

### Problema real

La columna muestra `local_id` plano, no locales aplicados/involucrados en el pago (especialmente en pagos con múltiples asignaciones).

### Evidencia técnica

- Columna Local usa `accessorKey: 'local_id'`: @resources/js/pages/catalogs/payment/columns.tsx#196-199
- `toRow()` solo expone `local_id` del pago, sin agregación de allocations: @app/Services/PaymentService.php#409-415
- Ya existe patrón UI rico para locales en cesionarios (badge + popover): @resources/js/pages/catalogs/concessionaire/columns.tsx#381-452

### Causa raíz

Modelo de fila de pagos no incluye `applied_locals` ni `applied_locals_count`.

### Corrección propuesta

1. Extender `PaymentService::toRow()` con:
    - `applied_locals_count`
    - `applied_locals` (códigos)
    - fallback a `payment.local_id`/deudor local cuando no haya allocations.
2. Reemplazar render de columna por badge/popover similar a cesionarios.
3. Añadir test feature para pago con asignación a múltiples locales.

### Criterio de aceptación

- En index de pagos se visualizan correctamente los locales impactados por cada pago.

---

## 3) Plan de implementación (orden sugerido)

### Fase 1 (P0)

1. BT-07 (contratos activos)
2. BT-03 (regla y semántica deuda vencida)

### Fase 2 (P1)

1. BT-02 (definir KPI recaudación/proyección)
2. BT-04 (estrategia multimoneda dashboard)
3. BT-08 (locales en index pagos)

### Fase 3 (P2)

1. BT-01 (desglose deuda por concepto)
2. BT-05 (drill-down top morosos)
3. BT-06 (mejora visual de gráfico por estatus)

---

## 4) Riesgos y decisiones pendientes

1. **Definición de "activo" en contratos**: ¿incluye `VENC` o no?
2. **Regla única de vencimiento**: ¿día fijo para todos o por modalidad?
3. **Moneda base del dashboard financiero**: ¿Bs como canónica para comparativas?

Sin estas decisiones, parte de las correcciones quedará técnica pero no funcionalmente cerrada.
