---
title: 'Tarifas — Visión general'
summary: 'Tarifas por mercado con vigencias sin solape; base para cálculo de cargos M2/FIJO.'
icon: material/currency-eur
---

# Tarifa por mercado — Visión general

- Propósito: definir tarifas por mercado en EUR/m² (y/o FIJO) con vigencias sin solape.
- Efecto: los contratos M2 calculan según la tarifa vigente del mercado en la fecha de generación del cargo (histórico no se reescribe).
- Requisitos previos: permisos (p. ej., `tariffs.view`, `tariffs.create`, `tariffs.update`).

## Reglas
- Vigencias no deben solaparse dentro del mismo mercado.
- Cambios afectan solo cargos futuros; los cargos ya generados mantienen la tarifa aplicada.
