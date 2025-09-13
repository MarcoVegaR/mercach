---
title: 'Usuarios — tareas'
summary: 'Crear, buscar, editar, restablecer contraseña y desactivar/reactivar usuarios paso a paso.'
icon: material/account-cog
tags:
    - manual
    - usuarios
---

# Usuarios — Tareas

## Crear usuario

Requisitos: `users.create`.

Pasos:

1. Ve a `Usuarios` → `Nuevo`.
2. Completa nombre, email, roles y estado.
3. Guarda. Verás un mensaje de confirmación si todo salió bien.

!!! note "Conflictos comunes"
Si el email ya existe, la creación fallará. Corrige el email o edita el usuario existente.

## Editar usuario y restablecer contraseña {#editar-usuario-y-restablecer-contraseña}

Requisitos: `users.update`.

Pasos:

1. En la lista, abre el menú de acciones del usuario y elige `Editar`.
2. Actualiza campos y guarda.
3. Para restablecer contraseña, usa la acción `Restablecer contraseña` si está disponible en tu instalación.

## Desactivar o reactivar usuario

Requisitos: `users.setActive`.

Pasos:

1. En la tabla, abre `Acciones` → `Desactivar` (o `Reactivar`).
2. Confirma en el diálogo.

!!! warning "Restricciones"
Es posible que no puedas desactivar/eliminar algunos usuarios protegidos (p.ej., último admin). Consulta políticas internas.

## Búsqueda y orden

- Usa la búsqueda global para nombre o email.
- Haz clic en cabeceras para ordenar (ID, nombre, email, estado, fecha).

=== "Tabla"

- Selección múltiple para acciones masivas (si está habilitada).
- Exportación según columnas visibles.

=== "Detalle"

- Información enriquecida cuando la vista de detalle esté disponible.

## Accesibilidad y atajos

- `Tab`/`Shift+Tab` para navegar controles.
- `Enter` para activar botones/links enfocados.
- Lector de pantalla: los campos y acciones tienen etiquetas.
