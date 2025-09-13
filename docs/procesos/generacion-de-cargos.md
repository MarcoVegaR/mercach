---
title: 'Generación de cargos'
summary: 'Programación y reglas para cargos M2, FIJO y condominio.'
icon: material/receipt
---

# Generación de cargos

## M2 (EUR)
- Se generan el día 1 de cada mes para cada contrato ACTIVE.
- Vencen el día 6 del mismo mes.
- Monto = tarifa vigente (EUR/m²) × m² del local × periodo.

## FIJO (EUR)
- Se generan según el `billing_day` del contrato.
- Vencen el mismo día (o con periodo de gracia, si aplica).

## Condominio (USD)
- Se genera el día 1 a partir del snapshot oficializado del periodo.
- Vence el día 5.
- Prorrateo por m² entre participantes del snapshot.

## Idempotencia y reintentos
- Cada cargo se identifica por hash de combinación (periodo/target/tipo).
- Reintentos no duplican cargos.

## Scheduler
- Día 1: cargos M2 y condominio.
- Diario: cargos FIJO.
- Jobs adicionales: expiración de contratos, sincronización BCV.
