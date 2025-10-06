---
title: 'Generación de cargos'
summary: 'Programación y reglas para cargos M2, FIJO, Disponibles y Condominio.'
icon: material/receipt
---

# Generación de cargos

Esta página describe las reglas de negocio y los parámetros de ejecución para los cuatro tipos de cargos soportados por el sistema.

## M2 (EUR)

- **Ámbito**: locales ocupados con contrato vigente que cumplan: `contract_types.code = 'CONV'` y `contract_modalities.code = 'M2'`.
- **Vigencia**: `contract_statuses.code IN ('VIG','EXT')` y solape con el mes (`start_date <= fin_de_mes` y `end_date IS NULL OR end_date >= inicio_de_mes`).
- **Fórmula**: `monto_minor = tarifa_minor_por_m2_por_día × área_m2 × (365/12)`.
- **Tarifa**: `market_tariffs.is_current = true` para el mercado seleccionado.
- **Fechas**: `issued_on = día 1`, `due_on = día 6`, `period = YYYY-MM-01`.
- **Idempotencia**: único por (`debtor_type`,`.debtor_id`,`.kind,$period`).
- **Parámetros**: `market_id` (requerido), `period` (YYYY-MM-01).

## FIJO (EUR)

- **Ámbito**: locales ocupados con contrato vigente que cumplan: `contract_types.code = 'CONV'` y `contract_modalities.code = 'TFIJA'`.
- **Vigencia**: `contract_statuses.code IN ('VIG','EXT','VENC')` para permitir generación histórica. Además, el contrato debe estar activo el día de emisión (por fechas).
- **Fórmula**: `monto_minor = monthly_price_eur × 100` (prorrateado equitativamente entre los locales del contrato cuando aplica).
- **Día de cobro**: `billing_day` tomado del `start_date` (definido en el seeder para TFIJA).
- **Fechas**: `issued_on = billing_day del mes`, `due_on = issued_on`, `period = YYYY-MM-01`.
- **Idempotencia**: único por (`contract_id`,`.local_id,$kind,$issued_on`).
- **Parámetros**: `market_id` (opcional), `period` (YYYY-MM-01). También se soporta `date` (Y-m-d) para ejecución diaria puntual.

## Locales disponibles (EUR)

- **Ámbito**: locales sin contrato vigente en el mes. Regla canónica de disponibilidad: NO EXISTE contrato con `cs.code = 'VIG'` que se solape con el mes.
- **Fórmula**: misma que M2: `monto_minor = tarifa_minor_por_m2_por_día × área_m2 × (365/12)`.
- **Fechas**: `issued_on = día 1`, `due_on = día 6`, `period = YYYY-MM-01`.
- **Idempotencia**: único por (`debtor_type,$debtor_id,$kind,$period`).
- **Parámetros**: `market_id` (requerido), `period` (YYYY-MM-01).

## Condominio (USD)

- **Ámbito**: participantes del período de condominio (`condo_periods`). Modelo de exclusiones: por defecto todos incluidos; filas en `condo_participants` con `included = false` excluyen locales.
- **Base de costos**: suma de `condo_expenses.amount_usd_minor` del período.
- **Prorrateo**: `unit_minor = total_gastos_minor / suma_m2_participantes`. Cargo por local: `amount_minor = unit_minor × área_m2_participante`.
    - Si existe `area_m2_snapshot` (cuando el participante tiene fila incluida explícita), se utiliza; en caso contrario, `locals.area_m2`.
- **Fechas**: `issued_on = día 1`, `due_on = día 5`, `period = YYYY-MM-01`.
- **Idempotencia**: único por (`condo_period_id,$local_id,$kind`).
- **Parámetros**: `market_id` (requerido), `period` (YYYY-MM-01).

## Idempotencia y re intentos

- Cada cargo se identifica por hash de combinación (periodo/target/tipo).
- Re intentos no duplican cargos.

## Scheduler

- **Día 1**: M2, Locales disponibles y Condominio.
- **Diario**: FIJO (para emitir por `billing_day`).
- Jobs adicionales: expiración de contratos, sincronización BCV.
