---
title: 'Contratos — Visión general'
summary: 'Contratos multi‑local/multi‑firmante, estados y efectos operativos.'
icon: material/file-document
---

# Contratos — Visión general

- Propósito: regular la ocupación de locales por parte de concesionarios.
- Características clave:
  - Multi‑local y multi‑firmante.
  - Modalidades: FIJO y M2.
  - Estado: DRAFT → ACTIVE → ENDED/EXPIRED (state machine).
  - Efectos: ACTIVE marca locales como OCUPADOS (no se permiten solapes). ENDED/EXPIRED libera a DISPONIBLE.
- Requisitos previos: permisos (p. ej., `contracts.view`, `contracts.create`, `contracts.update`).
