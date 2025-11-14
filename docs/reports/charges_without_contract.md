# Cargos creados sin contrato activo (historical_debts)

Fecha de extracción: ahora (consulta directa a la BD vía MCP)

## Resumen

- Total de cargos en BD: 4312
- Cargos con contract_id = NULL: 778
- Motivos principales:
    - Locales recuperados sin contrato registrado para esos períodos (en este entorno, los contratos TERMINADOS están deshabilitados en dev).
    - Locales con contrato vigente hoy, pero la deuda corresponde a meses anteriores al start_date del contrato (por eso no se vincula como “activo para el período”).

## Casos NO recuperados (con deuda sin contrato activo)

Listado de locales cuyo cargo quedó sin contrato (agrupado por local). Rango de períodos afectado y estado de contratos asociados al local.

- SS-C

    - Cargos sin contrato: 14
    - Períodos: 2024-08 → 2025-09
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-10-01 → end 2026-10-01
    - Causa: todos los meses listados son previos al inicio del contrato

- B-01

    - Cargos sin contrato: 8
    - Períodos: 2025-04 → 2025-11
    - Contratos asociados al local: 0
    - Causa: sin contrato registrado para esos meses

- S-49

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-50

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-51

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-52

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-53

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-54

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-55

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- S-56

    - Cargos sin contrato: 5
    - Períodos: 2024-08 → 2024-12
    - Contratos asociados al local: 1 (VIG, CONTR)
    - Ventana contrato: start 2025-01-01 → end 2026-01-01
    - Causa: meses previos al inicio del contrato

- G-21A

    - Cargos sin contrato: 1
    - Período: 2025-11
    - Contratos asociados al local: 0
    - Causa: sin contrato registrado para ese mes

- G-21B
    - Cargos sin contrato: 1
    - Período: 2025-11
    - Contratos asociados al local: 0
    - Causa: sin contrato registrado para ese mes

## Casos recuperados (con deuda sin contrato activo)

Los siguientes locales son recuperados; en este entorno no se generan contratos TERMINADOS (TERM), por lo que la deuda queda sin contrato vinculado para el período.

- BM-08 — 205 meses (2008-11 → 2025-11)
- BM-09 — 205 meses (2008-11 → 2025-11)
- HM-26 — 43 meses (2022-05 → 2025-11)
- H-01 — 38 meses (2022-10 → 2025-11)
- A-21 — 36 meses (2022-12 → 2025-11)
- A-22 — 36 meses (2022-12 → 2025-11)
- G-16 — 31 meses (2023-05 → 2025-11)
- G-04 — 29 meses (2023-07 → 2025-11)
- GM-20 — 16 meses (2024-08 → 2025-11)
- HM-19 — 14 meses (2024-10 → 2025-11)
- E-10 — 13 meses (2024-11 → 2025-11)
- C-18 — 12 meses (2024-12 → 2025-11)
- C-19 — 12 meses (2024-12 → 2025-11)
- BM-17 — 8 meses (2022-08 → 2023-03) — contrato CONV desde 2023-04-17 (meses previos quedan sin contrato)
- BM-19 — 8 meses (2022-08 → 2023-03) — contrato CONV desde 2023-04-17
- BM-21 — 8 meses (2022-08 → 2023-03) — contrato CONV desde 2023-04-17

## Notas

- El seeder de deudas fue ajustado para crear cargos aunque no exista concesionario o contrato activo para el período; en esos casos el cargo se genera y se registra la alerta.
- En producción, habilitar contratos TERMINADOS (TERM) para recuperados reduciría gran parte de estas alertas al vincular la deuda a dichos contratos.
- Alternativamente, el reporte puede agrupar alertas por local y rango de meses (en lugar de por cada mes) para disminuir ruido visual.

## Consultas SQL usadas (referencia)

Total y distribución por local:

```sql
SELECT COUNT(*) AS total_charges FROM charges;
SELECT COUNT(*) AS no_contract FROM charges WHERE contract_id IS NULL;

-- Top locales no recuperados con cargos sin contrato
WITH recovered(code) AS (
  VALUES ('A-21'),('A-22'),('C-18'),('C-19'),('E-10'),('G-04'),('G-16'),('H-01'),('BM-08'),('BM-09'),('BM-17'),('BM-19'),('BM-21'),('BM-21"'),('K-01'),('GM-20'),('CM-02'),('CM-03'),('CM-12'),('CM-13'),('CM-14'),('DM-03'),('DM-04'),('HM-26'),('HM-19'),('AM-15'),('AM-16'),('AM-17')
)
SELECT l.code,
       COUNT(c.id) AS charges_without_contract,
       MIN(c.period) AS first_period,
       MAX(c.period) AS last_period,
       COUNT(DISTINCT ct.id) AS contracts_count,
       MIN(ct.start_date) AS min_contract_start,
       MAX(ct.end_date) AS max_contract_end,
       STRING_AGG(DISTINCT cs.code, ',') AS contract_statuses,
       STRING_AGG(DISTINCT ctt.code, ',') AS contract_types
FROM charges c
JOIN locals l ON l.id=c.local_id
LEFT JOIN recovered r ON r.code=l.code
LEFT JOIN contract_local cl ON cl.local_id=l.id
LEFT JOIN contracts ct ON ct.id=cl.contract_id
LEFT JOIN contract_statuses cs ON cs.id=ct.contract_status_id
LEFT JOIN contract_types ctt ON ctt.id=ct.contract_type_id
WHERE c.contract_id IS NULL AND r.code IS NULL
GROUP BY l.code
ORDER BY charges_without_contract DESC, l.code;
```
