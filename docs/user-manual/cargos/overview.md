---
title: 'Cargos — Visión general'
summary: 'Generación de cargos por contrato: M2, FIJO y condominio.'
icon: material/receipt-text
---

# Cargos — Visión general

- Tipos de cargo:
  - M2 (EUR): generado el día 1 del mes; vence el día 6.
  - FIJO (EUR): en el billing day definido por el contrato; vence ese día (o con gracia).
  - Condominio (USD): prorrateo por m² a partir del snapshot oficializado del período (día 1, vence día 5).
- Idempotencia: se evita duplicar cargos por hash de combinación (periodo/target/tipo).
- Efectos de cambios de tarifa: solo impactan cargos futuros (histórico intacto).
