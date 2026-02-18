# Backlog técnico de correcciones - Dashboard Debt Analysis

**Fecha:** 2026-02-17  
**Módulo:** `/dashboard/debt-analysis`  
**Estado general:** ✅ Correcciones implementadas en esta entrega

---

## Resumen ejecutivo

Se corrigieron los hallazgos críticos y altos detectados en el análisis de consistencia del módulo:

1. Duplicación de filas en `delinquent-locals`.
2. Sub-reporte en `delinquent-concessionaires` por cargos `contract_id = NULL` y cargos del cesionario.
3. Distribuciones con lógica multimoneda incorrecta (USD tratado como EUR en agregados).
4. Filtros validados pero no aplicados.
5. Sobreconteo de pagos en solventes.
6. Inconsistencia de mapeo con dashboard para conteo de morosos.

---

## BA-01 - Duplicación de locales en delinquent-locals

**Prioridad:** P0  
**Tipo:** Bug de agregación

### Problema

El endpoint devolvía múltiples filas por el mismo local cuando existían relaciones contractuales históricas/múltiples concesionarios.

### Causa raíz

Agrupación por `cn.full_name` dentro del CTE `per_local`, lo que rompía la cardinalidad 1 local = 1 fila.

### Corrección aplicada

- Se reemplazó el join dependiente de `o.contract_id` por un mapeo de concesionario activo por local.
- Se consolidó `concessionaire_name` con `STRING_AGG` en CTE dedicado y se agrupó por identificadores de local.

### Archivos

- `app/Services/DebtAnalysisService.php`

### Verificación

- Test de regresión: `test_delinquent_locals_returns_single_row_per_local_even_with_multiple_contracts`.

---

## BA-02 - Morosos por cesionario omitía deuda válida

**Prioridad:** P0  
**Tipo:** Bug de cobertura de dominio

### Problema

No se consideraban correctamente:

- Cargos `LOCAL` con `contract_id = NULL`.
- Cargos `debtor_type = CONCESSIONAIRE`.

### Causa raíz

El mapeo dependía de `charge.contract_id` para relacionar local→contrato→cesionario.

### Corrección aplicada

- Se implementó CTE `active_contract_by_local` para mapear locales a su contrato activo aunque el cargo no tenga `contract_id`.
- Se incorporó CTE `overdue_concessionaire` para incluir cargos directos al cesionario.
- Se unificó la agregación en `all_overdue`.

### Archivos

- `app/Services/DebtAnalysisService.php`

### Verificación

- Test de regresión: `test_delinquent_concessionaires_include_contract_null_and_direct_concessionaire_charges`.

---

## BA-03 - Distribuciones con error multimoneda y créditos incompletos

**Prioridad:** P0  
**Tipo:** Bug financiero

### Problema

`by_aging`, `by_market` y `by_local_type` mezclaban moneda original y conversiones en Bs de forma inconsistente, sobre todo con cargos USD.

### Causa raíz

Agregación directa de `amount_minor` como EUR en varios bloques y descuento no uniforme de créditos.

### Corrección aplicada

- Se reescribió la base de distribuciones con CTE unificado:
    - `allocs`
    - `credits`
    - `overdue`
    - `outstanding`
- Se calcula `outstanding` por moneda original (EUR/USD/VES) y en Bs de forma consistente.
- Se aplican pagos y créditos en todos los agregados.

### Archivos

- `app/Services/DebtAnalysisService.php`

### Verificación

- Test de regresión: `test_distributions_apply_usd_credits_and_rates_consistently`.

---

## BA-04 - Filtros validados pero no aplicados

**Prioridad:** P1  
**Tipo:** Contrato API incumplido

### Problema

Parámetros validados en controller no tenían efecto real en consultas.

### Corrección aplicada

- `delinquent-concessionaires`: se aplican `max_debt_eur`, `min_days`, `market_id`.
- `delinquent-locals`: se aplican `local_type_id`, `min_debt_eur`, `min_days`, `market_id`.
- Se añadió validación de `min_days` para locals en controller.

### Archivos

- `app/Services/DebtAnalysisService.php`
- `app/Http/Controllers/Api/DebtAnalysisController.php`

### Verificación

- Test de regresión: `test_debt_analysis_filters_are_applied`.

---

## BA-05 - Solventes sobrecontaba pagos

**Prioridad:** P1  
**Tipo:** Bug de agregación

### Problema

`total_payments` se inflaba por conteo sobre joins de allocaciones/cargos.

### Corrección aplicada

- Se cambió `payment_info` para contar pagos reales del cesionario con `COUNT(DISTINCT p.id)` sobre `payments`.

### Archivos

- `app/Services/DebtAnalysisService.php`

---

## BA-06 - Inconsistencia de mapeo con dashboard

**Prioridad:** P1  
**Tipo:** Inconsistencia entre módulos

### Problema

El criterio de morosidad en debt-analysis no coincidía con el de dashboard (contrato activo por local).

### Corrección aplicada

- Se normalizó el mapeo con subquery reutilizable de contrato activo por local.
- Se usa el mismo patrón para delinquent y solvent.

### Archivos

- `app/Services/DebtAnalysisService.php`

---

## Cobertura de pruebas agregada

Se añadieron pruebas de regresión en:

- `tests/Feature/DebtReconciliationTest.php`

Nuevos casos:

- Dedupe de locales.
- Inclusión de `contract_id = NULL` y cargos del cesionario.
- Aplicación efectiva de filtros.
- Consistencia multimoneda/creditos en distribuciones.

---

## Resultado final

**Estado:** ✅ Implementado y validado con tests de regresión (`6 passed`).
