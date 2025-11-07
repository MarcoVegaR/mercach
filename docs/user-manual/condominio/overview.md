---
title: 'Condominio — Visión general'
summary: 'Gestión de períodos de condominio: gastos, participantes y confirmación final para emitir cargos.'
icon: material/home-city
---

# Condominio — Visión general

- Propósito: consolidar gastos del mes por mercado y prorratearlos entre locales participantes para emitir cargos de condominio (USD).
- Flujo general:
    1. Crear/actualizar el período del mercado (YYYY-MM).
    2. Cargar gastos del período (USD) y definir participantes (locales incluidos o excluidos, opcional snapshot de m²).
    3. Confirmar el período a estado FINAL. Solo entonces se podrán generar cargos de Condominio (USD) desde Cargos → Ejecutar.
- Estados del período: DRAFT → FINAL. Se puede reabrir a DRAFT para correcciones si tienes permiso.
- Reglas clave: deben existir gastos > 0, participantes con metraje válido, y el mercado estar activo.
