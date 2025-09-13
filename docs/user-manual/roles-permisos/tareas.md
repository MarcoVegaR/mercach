---
title: 'Roles & Permisos — tareas'
summary: 'Crear roles, asignar permisos y roles a usuarios, y verificar acceso.'
icon: material/shield-check
tags:
    - manual
    - roles
    - permisos
---

# Roles & Permisos — Tareas

## Crear rol y asignar permisos

Requisitos: `roles.create`.

Pasos:

1. Abre `Roles` → `Nuevo`.
2. Define nombre y `guard`.
3. Selecciona permisos (puedes buscar y usar selección por grupo).
4. Guarda y confirma.

!!! note "Permisos por guard"
Asegúrate de elegir permisos compatibles con el `guard` del rol (p.ej., `web`).

## Asignar roles a usuarios

Requisitos: `users.update` (u otra política según tu instalación).

Pasos:

1. Ve a `Usuarios` → `Editar`.
2. En "Roles", selecciona el rol a asignar.
3. Guarda.

## Verificar acceso

- Inicia sesión con un usuario de prueba.
- Navega a la sección objetivo. Si la acción no aparece, revisa permisos del rol.

=== "Tabla"

- La columna de permisos muestra primeros 2 y un +N (ver más) si hay muchos.

=== "Detalle"

- Puedes ver permisos completos del rol desde la vista de detalle si está habilitada.
