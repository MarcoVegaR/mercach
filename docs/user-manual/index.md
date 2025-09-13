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

Este manual recopila tareas frecuentes orientadas a las personas que usan Mercach en la operación diaria del Mercado de Chacao. Está organizado por módulos y cada uno incluye:

- Visión general (propósito, a quién va dirigido, requisitos previos y pantallas)
- Tareas paso a paso (crear, buscar, editar, exportar, etc.)
- Preguntas frecuentes (FAQ) y solución rápida de problemas

!!! tip "Diátaxis y estructura"
Este Manual se centra en tareas (How‑to). Para conceptos y decisiones de arquitectura, consulta Explicaciones. Para API y parámetros, ve a Referencia.

## Índice de tareas frecuentes

- Locales
    - [Registrar un local](locales/tareas.md)
    - [Editar datos de un local](locales/tareas.md)
    - [Cambiar estado / disponibilidad](locales/tareas.md)
    - [Exportar listado](locales/tareas.md)
- Espacios abiertos
    - [Crear una reserva](espacios-abiertos/tareas.md)
    - [Editar / mover una reserva](espacios-abiertos/tareas.md)
    - [Cancelar una reserva](espacios-abiertos/tareas.md)
    - [Exportar agenda](espacios-abiertos/tareas.md)
- Asignaciones
    - [Asignar un local](asignaciones/tareas.md)
    - [Reasignar](asignaciones/tareas.md)
    - [Liberar local](asignaciones/tareas.md)
    - [Ver historial de un local](asignaciones/tareas.md)
- Pagos
    - [Registrar un pago](pagos/tareas.md)
    - [Consultar estado de cuenta](pagos/tareas.md)
    - [Emitir comprobante](pagos/tareas.md)
- Inspecciones
    - [Programar inspección](inspecciones/tareas.md)
    - [Registrar acta](inspecciones/tareas.md)
    - [Cerrar hallazgos](inspecciones/tareas.md)
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
- Auditoría
    - [Buscar y filtrar eventos](auditoria/tareas.md#buscar-y-filtrar-eventos)
    - [Ver detalle de un evento](auditoria/tareas.md#ver-detalle-de-un-evento)
    - [Exportar resultados](auditoria/tareas.md#exportar-resultados)

## Requisitos de acceso por módulo (resumen)

- Auditoría: requiere `auditoria.view` (ver) y `auditoria.export` (exportar)
- Usuarios: `users.view`, `users.create`, `users.update`, `users.setActive`, `users.delete` (según la tarea)
- Roles & Permisos: `roles.view`, `roles.create`, `roles.update`, `roles.delete` (según la tarea)
- Settings: permisos específicos del área (p.ej. `settings.update`) según tu organización

!!! note "Estados vacíos y accesibilidad"
Las pantallas mantienen controles visibles cuando no hay resultados. Usa el teclado para navegar y los atajos indicados en cada tarea cuando estén disponibles.
