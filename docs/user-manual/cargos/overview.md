---
title: 'Cargos — Visión general'
summary: 'Generación de cargos por contrato: M2, Fijo y Condominio. Opción ALL para ejecutar los tres.'
icon: material/receipt-text
---

# Cargos — Visión general

## Tipos de cargo

- M2 (EUR): generado el día 1 de cada mes; vence el día 6.
- Fijo (EUR): en el billing day definido por el contrato; vence ese mismo día.
- Condominio (USD): prorrateo por m² a partir del snapshot del período; se emite día 1 y vence día 5.

Además, existe la opción ALL para ejecutar en un solo paso: M2, Fijo y Condominio.

## Campos requeridos

- Mercado: requerido para M2 y Condominio (y también en ALL).
- Periodo: requerido para todos los tipos (incluido ALL). Formato YYYY-MM-01 (el UI muestra un selector mensual YYYY-MM).
- Idempotency key (opcional): cadena única para reforzar la idempotencia. Si lo dejas vacío, los índices únicos también previenen duplicados.

## Flujo de ejecución (diálogo)

1. Abrir Cargos → Ejecutar ahora.
2. Elegir Tipo de cargo (o ALL).
3. Seleccionar Mercado (si aplica) y Periodo (YYYY-MM).
4. Presionar Ejecutar ahora. El sistema valida campos y ejecuta.
5. Se muestra un resumen en la lista de Cargos (banner verde) con totales generados, upserted y omitidos.

## Notas

- Idempotencia: se evita duplicar cargos según combinación única por tipo (ver detalles en la sección de procesos).
- Tarifas M2: se usa la tarifa vigente del mercado (EUR/m² por día); cambios impactan solo cargos futuros.
- Fijo: el billing_day proviene del día del start_date del contrato fijo.
