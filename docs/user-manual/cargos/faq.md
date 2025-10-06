---
title: 'Cargos — FAQ'
summary: 'Preguntas frecuentes sobre ejecución de cargos, idempotencia y resolución de problemas.'
icon: material/help-circle
---

# Cargos — FAQ

## ¿Qué tipos de cargos existen?

- M2 (EUR), Locales disponibles (EUR), Fijo (EUR) y Condominio (USD). También puedes usar la opción ALL para ejecutar los cuatro de una vez.

## ¿Qué campos son obligatorios en el diálogo?

- Mercado: requerido para ALL, M2, Disponibles y Condominio.
- Periodo: requerido para todos los tipos. Usa el selector mensual (YYYY-MM); el sistema guarda YYYY-MM-01.
- Idempotency key: opcional.

## ¿Para qué sirve Idempotency key?

- Permite reforzar la prevención de duplicados cuando repetimos ejecuciones. Aun si lo dejas vacío, los índices únicos por tipo evitan duplicados.

## ¿Por qué no se generaron cargos M2/Disponibles?

- Verifica que el mercado tenga una tarifa vigente (`market_tariffs.is_current = true`).
- Revisa que existan contratos M2 (para M2) o locales sin contrato vigente (para Disponibles) en el período seleccionado.
- Asegúrate de haber seleccionado Mercado y Periodo.

## ¿Por qué no se generaron cargos Fijos?

- Comprueba que los contratos `TFIJA` tengan precio mensual (> 0) y `billing_day`.
- Verifica que el contrato esté activo el día de emisión de ese mes (solape por fechas). La calculadora admite `VIG`, `EXT` y `VENC` para periodos históricos, pero exige actividad el día de emisión.
- Para Fijo, no se requiere Mercado; sí se requiere Periodo.

## ¿Cómo se calcula M2 y Disponibles?

- Fórmula mensual: `tarifa_minor_por_m2_por_día × área_m2 × (365/12)`.
- M2 aplica a locales con contrato M2 vigente; Disponibles aplica a locales sin contrato vigente.
- Fechas: emite día 1 y vence día 6.

## ¿Cómo se calcula Fijo?

- Monto: precio mensual del contrato (prorrateado entre locales si aplica).
- Día de emisión: `billing_day`; vence el mismo día.
- Contratos `TFIJA` deben tener tipo `CONV` y `billing_day` tomado del `start_date` (sembrado en el seeder).

## ¿Cómo se calcula Condominio?

- `unit_minor = total_gastos_minor / suma_m2_participantes`.
- `amount_minor_local = unit_minor × m2_local` (usa `area_m2_snapshot` si el participante tiene snapshot; de lo contrario, `locals.area_m2`).
- Requiere un `condo_period` para el mercado y período. Fechas: emite día 1, vence día 5.

## ¿Puedo ejecutar meses históricos?

- Sí. M2 y Disponibles usan solape mensual. Fijo admite `VENC` para históricos y exige que el contrato esté activo el día de emisión del mes.

## ¿Qué pasa si ejecuto dos veces el mismo período?

- No se duplican cargos por la idempotencia estructural (índices únicos por tipo) y, opcionalmente, por la `idempotency_key`.

## ¿Cómo verifico rápidamente lo generado?

- Filtra en la lista por `period = YYYY-MM-01` y por `kind` (`RENT_EUR_M2`, `RENT_EUR_M2_AVAIL`, `RENT_EUR_FIXED`, `CONDO_USD`).

## Vencimientos

- M2 y Disponibles: vencen el día 6 del mes.
- Fijo: vence el mismo día del `billing_day`.
- Condominio: vence el día 5.
