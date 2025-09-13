---
title: 'Auditoría — visión general'
summary: 'Qué es el módulo de Auditoría, a quién va dirigido, requisitos de acceso y mapa de pantallas con flujos habituales.'
icon: material/clipboard-text-clock
tags:
    - manual
    - auditoria
---

# Auditoría — Visión general

## Propósito

Registrar y consultar eventos relevantes del sistema (creación/edición/eliminación de registros, logins, cambios de configuración), con filtros y exportación para seguimiento y cumplimiento.

## A quién va dirigido

- Personal de soporte y operaciones
- Responsables de seguridad y compliance
- Líderes funcionales que necesiten trazabilidad

## Requisitos previos (roles/permisos)

- Ver auditoría: `auditoria.view`
- Exportar auditoría: `auditoria.export`

!!! note "Sin edición"
La Auditoría es de solo lectura. Los registros no se crean ni modifican manualmente.

## Mapa de pantallas

- Pantalla principal: listado con búsqueda y filtros (usuario, evento, entidad, IP, URL, rango de fechas)
- Panel de detalle: vista expandida con valores anteriores y nuevos, y metadatos
- Exportación: descarga CSV/XLSX/JSON respetando filtros aplicados

!!! tip "Atajos útiles" - Presiona `/` para enfocar la búsqueda global - Usa las teclas ↑/↓ para navegar filas (si tu navegador y lector de pantalla lo permiten)

## Ejemplo visual (referencial)

[![Listado de auditoría — ejemplo](https://via.placeholder.com/1200x680?text=Auditoria+-+Listado 'Listado de auditoría')](https://via.placeholder.com/1600x900?text=Auditoria+-+Listado)

[![Detalle de evento — ejemplo](https://via.placeholder.com/1200x680?text=Auditoria+-+Detalle 'Detalle de evento')](https://via.placeholder.com/1600x900?text=Auditoria+-+Detalle)
