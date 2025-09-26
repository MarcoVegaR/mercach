---
title: 'Usuarios — visión general'
summary: 'Gestión de usuarios: objetivo, perfiles a los que va dirigido, permisos requeridos y mapa de pantallas.'
icon: material/account-multiple
tags:
    - manual
    - usuarios
---

# Usuarios — Visión general

## Propósito

Crear, buscar, editar y administrar el estado de usuarios de la aplicación.

## A quién va dirigido

- Administradores y operadores con permisos de gestión de usuarios.

## Requisitos previos (permisos)

- Ver: `users.view`
- Crear: `users.create`
- Editar: `users.update`
- Activar/Desactivar: `users.setActive`
- Eliminar: `users.delete`

## Mapa de pantallas

- Lista de usuarios (búsqueda, filtros, orden, exportación y acciones individuales/masivas)
- Formulario de creación/edición (datos básicos, roles, estado)
- Vista de detalle (opcional)

!!! tip "Columnas y filtros"
Consulta la referencia de columnas y filtros en `modules/users.md` y `reference/datatable-api.md`.

## Estilo de columnas (tipografía y truncado)

Para mantener consistencia y legibilidad en la lista de usuarios, se aplican estas reglas:

- Nombre: texto con énfasis (`font-medium`), truncado con elipsis y `title` con el valor completo.
- Email: fuente monoespaciada (`font-mono`) y tamaño pequeño (`text-xs`), truncado con elipsis y `title` con el valor completo.
- Creado: fecha corta visible y tooltip con fecha completa y tiempo relativo.
- Estado: insignia “Activo/Inactivo” acompañada de un punto de color (verde/rojo).

Las mismas pautas se aplican en otros módulos (p. ej., Catálogos y Concesionarios) para una experiencia uniforme.
