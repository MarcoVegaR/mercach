# Seguimiento de hallazgos del sistema

**Fecha:** 2026-02-17  
**Solicitado por:** Producto/Operaciones  
**Objetivo:** Consolidar hallazgos detectados en Dashboard, Contratos y Pagos para análisis técnico y estrategia de corrección.

---

## 1) Dashboard

### 1.1 Tab: Panorama

#### Hallazgo 1 — Tarjeta "Deuda total"

- Se muestra la deuda total, y debajo aparecen montos por moneda:
    - `€ 32.068,80 · $ 124.155,51`
- Problema: no queda claro si esos montos corresponden a:
    - tasa de uso (M2),
    - tasa fija,
    - gastos comunes,
    - u otra combinación.

**Impacto:** ambigüedad para toma de decisiones y control operativo.

**Estrategia de corrección (propuesta):**

1. Definir explícitamente la composición del total (por `kind` y moneda).
2. Mostrar subtítulo/tooltip con desglose por concepto.
3. Validar que backend y frontend usen la misma definición de “deuda total”.

---

#### Hallazgo 2 — Tarjeta "Recaudación del mes"

- No está claro si la métrica representa:
    - **recaudación estimada** del mes (proyección), o
    - **recaudación efectivamente cobrada** en el mes en curso.

**Impacto:** riesgo de interpretación errónea de KPIs financieros.

**Estrategia de corrección (propuesta):**

1. Definir una única semántica del KPI (estimada vs real).
2. Renombrar el título si aplica (ej.: “Recaudación estimada mensual” o “Recaudado del mes”).
3. Agregar ayuda contextual (tooltip) con fórmula/fuente.

---

#### Hallazgo 3 — Gráfica "Cargos por status"

- Los cargos anulados o parcialmente pagados se aprecian poco cuando son pocos.

**Impacto:** baja visibilidad de estados minoritarios, posible ocultamiento de señales operativas.

**Estrategia de corrección (propuesta):**

1. Evaluar cambio de visualización (stacked bar / donut con etiquetas).
2. Aumentar contraste visual para categorías de baja frecuencia.
3. Mostrar valores absolutos y porcentajes en leyenda/tooltip.

---

### 1.2 Tab: Finanzas

#### Hallazgo 4 — "Deuda total" vs "Deuda vencida" (fecha de corte 17 de febrero)

- Observación funcional: para esta fecha, se espera que todas las deudas del mes estén vencidas.
- Problema: existe discrepancia entre deuda total y deuda vencida.
- Hipótesis planteada: diferencia por fecha de inicio de contratos, aunque la política de pago es en los primeros 5 días.

**Impacto:** inconsistencia percibida en métricas críticas.

**Estrategia de corrección (propuesta):**

1. Revisar regla de vencimiento (due date) aplicada en backend.
2. Confirmar si existen excepciones por contrato/modo de cobro.
3. Verificar que las consultas de deuda total y vencida usen misma base de datos/filtros de fecha.
4. Documentar regla final de negocio (sin ambigüedad).

---

#### Hallazgo 5 — Hover de "Top morosos"

- Se solicita enriquecer hover con:
    - locales asociados,
    - distribución de la deuda,
    - siempre que no sobrecargue el sistema.

**Impacto:** actualmente hay poca trazabilidad del porqué del ranking.

**Estrategia de corrección (propuesta):**

1. Diseñar tooltip expandido con top locales por moroso.
2. Incluir desglose resumido (monto y porcentaje por local/concepto).
3. Evaluar costo de consulta y, si aplica, precalcular/caché.

---

#### Hallazgo 6 — Gráfica "Proyección por tipo de local"

- Debe analizarse porque existen locales en `$` y otros en `€`.

**Impacto:** mezcla de monedas puede distorsionar lectura de proyección.

**Estrategia de corrección (propuesta):**

1. Definir moneda base de la gráfica.
2. Convertir con tasa de referencia única por fecha de corte.
3. Mostrar tasa utilizada y advertencia de conversión.
4. Considerar vista dual por moneda como alternativa.

---

#### Hallazgo 7 — "Top 10 locales por aporte"

- Debe identificarse por qué aparecen aportes en euros y dólares.

**Impacto:** ranking no homogéneo si no hay normalización monetaria.

**Estrategia de corrección (propuesta):**

1. Normalizar a moneda base para ordenar ranking.
2. Mostrar moneda original + monto convertido.
3. Validar consistencia con reglas usadas en otras tarjetas.

---

#### Hallazgo 8 — Gráfica "Cargos por status" (mismo problema que Panorama)

- Repite la dificultad de visualización de estados con poca frecuencia (anulados/parciales).

**Estrategia de corrección (propuesta):**

- Aplicar la misma solución definida en Hallazgo 3 para mantener consistencia entre tabs.

---

## 2) Contratos

#### Hallazgo 9 — Index de contratos: activos = total (aparente error)

- Se observa que el total de contratos activos parece incluir contratos terminados.

**Impacto:** KPI incorrecto en gestión contractual.

**Estrategia de corrección (propuesta):**

1. Auditar query de conteo en backend (filtros por status).
2. Confirmar semántica de “activo” según negocio.
3. Corregir filtros y agregar prueba de regresión para evitar recidiva.

---

## 3) Pagos

#### Hallazgo 10 — Index de pagos: columna "Local"

- Se solicita que funcione como la columna "Local" del index de cesionarios:
    - mostrar locales involucrados/aplicados en el pago.

**Impacto:** menor trazabilidad operativa de aplicación de pagos.

**Estrategia de corrección (propuesta):**

1. Reutilizar patrón de render existente en index de cesionarios.
2. Incluir locales vinculados por asignaciones (`payment_allocations`) y/o deudor.
3. Definir formato de presentación (badge/lista/truncado + tooltip).

---

## 4) Priorización sugerida

### Alta prioridad

1. Hallazgo 4 (deuda total vs vencida)
2. Hallazgo 9 (contratos activos mal contados)
3. Hallazgo 2 (definición recaudación del mes)
4. Hallazgos 6 y 7 (mezcla de monedas en proyecciones/rankings)

### Prioridad media

1. Hallazgo 1 (desglose deuda total por concepto)
2. Hallazgo 10 (columna local en pagos)
3. Hallazgo 5 (detalle adicional en top morosos)

### Prioridad baja (UX visual)

1. Hallazgos 3 y 8 (visibilidad de estados minoritarios en gráfica)

---

## 5) Plan de ejecución propuesto

### Fase 1 — Definiciones funcionales (rápida)

- Cerrar semántica de KPIs: deuda total, deuda vencida, recaudación del mes, aporte por local.
- Definir política de moneda para dashboard financiero.

### Fase 2 — Validación de datos y consultas

- Auditar endpoints/queries del dashboard.
- Verificar filtros por status en contratos.
- Validar origen de datos para columna local en pagos.

### Fase 3 — Ajustes de backend

- Corregir agregaciones, filtros y normalización monetaria.
- Agregar pruebas de regresión (feature/unit) para métricas críticas.

### Fase 4 — Ajustes de frontend

- Mejorar etiquetas, tooltips y visualizaciones.
- Aplicar consistencia visual entre tabs.

### Fase 5 — QA y cierre

- Validación funcional con casos reales.
- Checklist de aceptación por módulo.
- Documentar criterios finales en dashboard/contratos/pagos.

---

## 6) Checklist de seguimiento

- [ ] Hallazgo 1 — Deuda total: desglose por concepto y moneda
- [ ] Hallazgo 2 — Recaudación del mes: definición y etiqueta final
- [ ] Hallazgo 3 — Cargos por status (Panorama): visualización mejorada
- [ ] Hallazgo 4 — Deuda total vs vencida: corrección de regla/consulta
- [ ] Hallazgo 5 — Top morosos: tooltip con locales y distribución
- [ ] Hallazgo 6 — Proyección por tipo de local: política de moneda
- [ ] Hallazgo 7 — Top 10 aporte: ranking normalizado por moneda
- [ ] Hallazgo 8 — Cargos por status (Finanzas): aplicar misma mejora
- [ ] Hallazgo 9 — Contratos activos: excluir terminados del conteo
- [ ] Hallazgo 10 — Pagos columna local: mostrar locales involucrados

---

## 7) Notas

Este documento es un consolidado de observaciones funcionales para seguimiento.  
No implica todavía que todos los casos sean defectos de código; algunos pueden ser definiciones de negocio no explicitadas en UI.
