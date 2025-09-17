---
title: 'Catálogos — Visión general'
summary: 'Guía de uso de los catálogos administrativos: navegación, permisos, acciones comunes (búsqueda, filtros, crear/editar, activar/desactivar, exportar) y enlaces a cada catálogo.'
icon: material/view-list
tags:
    - manual
    - catalogos
    - administracion
---

# Catálogos — Visión general

Los catálogos centralizan valores maestros usados en todo el sistema (contratos, pagos, locales, etc.). Desde aquí puedes gestionar opciones como bancos, tipos y estados, sin entrar en módulos operativos.

## ¿Dónde encontrarlos?

- Menú lateral → Catálogos → Selecciona el catálogo deseado.

## Acciones comunes

Las siguientes acciones están disponibles en la mayoría de los catálogos:

- Búsqueda global (barra superior) y filtros por columnas (código, nombre, rango de fechas).
- Ordenamiento por columnas y paginación.
- Crear nuevo y editar existentes.
- Activar / desactivar registros.
- Ver detalle del registro.
- Exportar a CSV, XLSX o JSON.

Consulta la página "Tareas comunes" para pasos detallados.

## Permisos

Cada catálogo utiliza un prefijo de permisos del tipo `catalogs.<recurso>.*` (ver Roles & Permisos). Ejemplos:

- Ver: `catalogs.bank.viewAny`
- Crear: `catalogs.bank.create`
- Editar: `catalogs.bank.update`
- Eliminar: `catalogs.bank.delete`
- Exportar: `catalogs.bank.export`
- Activar/Desactivar: `catalogs.bank.update` (o específico según tu política)

## Catálogos disponibles

- Bancos
- Tipos de concesionario
- Modalidades de contrato
- Estados de contrato
- Tipos de contrato
- Tipos de documento
- Tipos de gasto
- Tipos de local
- Estados de local
- Tipos de pago
- Estados de pago
- Códigos de área telefónica
- Categorías comerciales

Para instrucciones específicas y campos de cada catálogo, abre su página dedicada en el menú.
