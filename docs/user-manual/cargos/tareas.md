---
title: 'Cargos — Tareas'
summary: 'Cómo ejecutar cargos por mes (ALL y por tipo), validaciones y resultado.'
icon: material/play-circle
---

# Cargos — Tareas

## Ejecutar todos (ALL)

- Abre Cargos → botón "Ejecutar ahora".
- Mercado: selección obligatoria.
- Periodo (YYYY-MM): selección obligatoria (el selector mensual guarda YYYY-MM-01).
- Idempotency key (opcional): puedes dejarlo vacío; el sistema evita duplicados por índices únicos.
- Presiona "Ejecutar ahora". Verás un banner con el resumen (generados, upserted, omitidos, errores).

Qué se genera:

- M2 (EUR): día 1; vence día 6.
- Fijo (EUR): día `billing_day`; vence el mismo día.
- Condominio (USD): día 1; vence día 5.

## Ejecutar por tipo

### M2 (EUR)

- Requiere: Mercado y Periodo.
- Fórmula: tarifa (EUR/m²·día) × m² × (365/12).
- Fechas: emite día 1, vence día 6.

### Fijo (EUR)

- Requiere: Periodo.
- Criterio: contratos `TFIJA` con tipo `CONV`, activos en el `billing_day` del mes.
- Monto: precio mensual del contrato (prorrateo entre locales si aplica).
- Fechas: emite en el `billing_day`, vence el mismo día.

### Condominio (USD)

- Requiere: Mercado y Periodo (de un `condo_period` existente).
- Cálculo: (suma de gastos del periodo / suma m² participantes) × m² del local (snapshot si aplica).
- Fechas: emite día 1, vence día 5.

## Validaciones del diálogo

- Mercado: requerido para ALL, M2 y Condominio.
- Periodo: requerido para todos los tipos (incl. ALL).
- El botón "Ejecutar ahora" se desactiva mientras falten campos requeridos.
- Idempotencia: opcional; ayuda a reforzar anti-duplicados.

## Resultado y verificación

- Tras ejecutar, vuelve a la lista de Cargos con un banner de resumen.
- Puedes filtrar por `period = YYYY-MM-01` y por `kind` (`RENT_EUR_M2`, `RENT_EUR_FIXED`, `CONDO_USD`).
- Si el resultado fue 0, consulta la sección FAQ.
