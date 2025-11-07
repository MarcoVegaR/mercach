---
title: 'Dashboard — Visión general'
summary: 'Cómo usar el tablero: KPIs, gráficos y filtros, y qué permisos se requieren.'
icon: material/view-dashboard
---

# Dashboard — Visión general

- Propósito: una vista rápida del estado general (contratos, locales, distribuciones y rankings) con enlaces a detalle.
- Acceso: desde el menú → Dashboard.
- Permisos:
    - `dashboard.view`: acceso a la página.
    - `dashboard.view.cards`: KPIs (tarjetas superiores).
    - `dashboard.view.charts`: gráficos y rankings.

## Componentes

- KPIs (tarjetas): totales clave (según versión de la app).
- Gráficos:
    - Contratos por estatus (VIG, EXT, VENC, TERM).
    - Contratos por tipo (CONTR, CONV, …).
    - Locales disponibles por tipo (anillos/barras).
    - Timeline de contratos (inicio/fin) y Rankings (concesionarios por contratos o m²).

## Filtros

- Mercado (cuando aplique), tipo de contrato, orden y límite en rankings.
- Los filtros se aplican a las llamadas BFF (`/api/dashboard/*`).

## Consejos

- Si no ves tarjetas/gráficos, verifica permisos `dashboard.view.cards`/`dashboard.view.charts`.
- Los datos se cachean brevemente; si cambias parámetros, espera unos segundos o recarga.
