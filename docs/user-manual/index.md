---
title: 'Manual de usuario'
summary: 'Guías prácticas y centradas en tareas para los módulos Auditoría, Usuarios, Roles & Permisos y Settings.'
icon: material/book-check
tags:
    - manual
    - how-to
    - onboarding
---

# Manual de usuario

Este manual recopila tareas frecuentes orientadas a las personas que usan el sistema día a día. Está organizado por módulos y cada uno incluye:

- Visión general (propósito, a quién va dirigido, requisitos previos y pantallas)
- Tareas paso a paso (crear, buscar, editar, exportar, etc.)
- Preguntas frecuentes (FAQ) y solución rápida de problemas

!!! tip "Diátaxis y estructura"
Este Manual se centra en tareas (How‑to). Para conceptos y decisiones de arquitectura, consulta Explicaciones. Para API y parámetros, ve a Referencia.

## Índice de tareas frecuentes

- Auditoría
    - [Revisar eventos por usuario/fecha](auditoria/tareas.md#buscar-y-filtrar-eventos)
    - [Ver detalle de cambio](auditoria/tareas.md#ver-detalle-de-un-evento)
    - [Exportar resultados](auditoria/tareas.md#exportar-resultados)
- Usuarios
    - [Crear usuario](usuarios/tareas.md#crear-usuario)
    - [Editar y restablecer contraseña](usuarios/tareas.md#editar-usuario-y-restablecer-contraseña)
    - [Desactivar/reactivar](usuarios/tareas.md#desactivar-o-reactivar-usuario)
- Roles & Permisos
    - [Crear rol y asignar permisos](roles-permisos/tareas.md#crear-rol-y-asignar-permisos)
    - [Asignar roles a usuarios](roles-permisos/tareas.md#asignar-roles-a-usuarios)
    - [Verificar acceso](roles-permisos/tareas.md#verificar-acceso)
- Settings
    - [Cambiar idioma y zona horaria](settings/tareas.md#preferencias-de-idioma-y-zona-horaria)
    - [Configurar notificaciones](settings/tareas.md#notificaciones)
    - [Parámetros de seguridad](settings/tareas.md#seguridad)

## Requisitos de acceso por módulo (resumen)

- Auditoría: requiere `auditoria.view` (ver) y `auditoria.export` (exportar)
- Usuarios: `users.view`, `users.create`, `users.update`, `users.setActive`, `users.delete` (según la tarea)
- Roles & Permisos: `roles.view`, `roles.create`, `roles.update`, `roles.delete` (según la tarea)
- Settings: permisos específicos del área (p.ej. `settings.update`) según tu organización

!!! note "Estados vacíos y accesibilidad"
Las pantallas mantienen controles visibles cuando no hay resultados. Usa el teclado para navegar y los atajos indicados en cada tarea cuando estén disponibles.
