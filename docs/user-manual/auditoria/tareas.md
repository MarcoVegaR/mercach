---
title: 'Auditoría — tareas'
summary: 'Buscar, filtrar, revisar detalle y exportar eventos de auditoría paso a paso.'
icon: material/clipboard-search
tags:
    - manual
    - auditoria
---

# Auditoría — Tareas

## Buscar y filtrar eventos

Requisitos: permiso `auditoria.view`.

Pasos:

1. Abre `Auditoría` en el menú lateral.
2. Escribe un término en la búsqueda global (atajo `/`) y presiona Enter.
3. Abre el panel de filtros y selecciona uno o más:
    - Usuario, Evento, Tipo de entidad, ID de entidad, IP, URL, Rango de fechas.
4. Aplica filtros. La tabla se actualizará manteniendo los controles visibles incluso si no hay resultados.

!!! tip "Atajos y navegabilidad" - Usa las flechas ↑/↓ para moverte por filas. - Presiona Enter para abrir el detalle (si está disponible en tu instalación).

## Ver detalle de un evento

Requisitos: `auditoria.view`.

Pasos:

1. Identifica el evento en la tabla y selecciónalo.
2. Abre el panel de detalle (o página detalle) para ver valores anteriores, nuevos y metadatos (IP, URL, user agent, tags).

!!! note "Interpretación de campos" - "Valores Anteriores" vs "Valores Nuevos" muestran el cambio exacto. - "Usuario" puede ser "Sistema" cuando el proceso no tiene sesión de usuario.

## Exportar resultados

Requisitos: `auditoria.export`.

Pasos:

1. Ajusta la búsqueda/filtros para el subconjunto deseado.
2. Haz clic en Exportar y elige formato (CSV, XLSX, JSON).
3. Descarga el archivo y ábrelo en tu herramienta preferida.

!!! warning "Volumen de datos"
Exportar grandes rangos de fechas puede demorar. Considera acotar por fecha o entidad.

## Tabs — Tabla / Detalle

=== "Tabla"

- Búsqueda global, orden por columnas, filtros múltiples.
- Estado vacío mantenido (controles visibles).

=== "Detalle"

- Campos técnicos (IP, URL, tags, user agent).
- Payloads previos/posteriores (cuando aplique).

## Accesibilidad

- Navegación por teclado en tabla.
- Enlaces y botones con etiquetas accesibles.
- Contraste suficiente en temas claro/oscuro.

## Capturas (referenciales)

[![Tabla de auditoría](https://via.placeholder.com/1200x680?text=Tabla+Auditoria 'Tabla de auditoría')](https://via.placeholder.com/1600x900?text=Tabla+Auditoria)

[![Detalle de evento](https://via.placeholder.com/1200x680?text=Detalle+Evento 'Detalle de evento')](https://via.placeholder.com/1600x900?text=Detalle+Evento)
