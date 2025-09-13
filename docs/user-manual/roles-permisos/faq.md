---
title: 'Roles & Permisos — FAQ'
summary: 'Problemas habituales con roles y permisos y cómo resolverlos.'
icon: material/help-circle
tags:
    - manual
    - roles
    - permisos
    - faq
---

# Roles & Permisos — Preguntas frecuentes (FAQ)

## No veo la acción X

- Falta el permiso correspondiente en tu rol.
- La acción puede depender de un estado (p.ej., solo activo/inactivo).

## Conflicto de permisos

- Asegúrate de no tener permisos con distinto `guard` al del rol.
- Limpia la caché de permisos si tu entorno lo requiere (`php artisan permission:cache-reset`).

## No carga el conteo de usuarios/permisos

- Es posible que el conteo provenga de subconsultas. Recarga la página o revisa filtros.
