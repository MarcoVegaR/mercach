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

## Vista "Mostrar contrato"

- Pestañas:
    - Detalles: información básica, metadatos (creado/actualizado) y relaciones.
    - Documentos: visor del PDF del contrato (si fue cargado). Si no hay archivo, se indica claramente.
- Relaciones visibles en Detalles:
    - Locales asociados: lista con enlaces hacia cada `Local`.
    - Concesionarios asociados: lista con enlaces y etiqueta "Titular" para el firmante principal.
- Acciones:
    - Editar: siempre que el usuario cuente con permiso de actualización.
    - Eliminar: el botón se muestra si el usuario tiene permiso, pero la eliminación de contratos está bloqueada por reglas de negocio.
