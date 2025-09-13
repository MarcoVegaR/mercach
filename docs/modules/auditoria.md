---
title: 'Módulo: Auditoría'
summary: 'Referencia del módulo de Auditoría: funcionalidades, permisos, campos, filtros, exportación y arquitectura técnica (backend + frontend).'
icon: material/clipboard-text-clock
tags:
    - referencia
    - módulo
    - auditoría
---

# Módulo de Auditoría

El módulo de auditoría proporciona un sistema completo para consultar y analizar el historial de actividad del sistema, basado en owen-it/laravel-auditing.

## Funcionalidades

### Solo Lectura

- **Listado de auditoría**: Visualización paginada de todos los eventos del sistema
- **Búsqueda avanzada**: Filtros por usuario, evento, entidad, IP, URL y rango de fechas
- **Exportación**: Soporte para CSV, XLSX, PDF y JSON
- **Detalles del evento**: Visualización de valores anteriores/nuevos y metadatos

### Sin Create/Edit

Este módulo es de **solo consulta**. Los registros de auditoría se generan automáticamente por el sistema y no pueden ser creados o modificados manualmente.

## Permisos

### Permisos Disponibles

- `auditoria.view`: Permite acceder al listado de auditoría
- `auditoria.export`: Permite exportar registros de auditoría

### Configuración

Los permisos se definen en `config/permissions/auditoria.php` y son integrados automáticamente por el `PermissionsSeeder`.

```php
return [
    'permissions' => [
        'auditoria.view',
        'auditoria.export',
    ],
];
```

## Campos Disponibles

### Información Básica

- **ID**: Identificador único del registro de auditoría
- **Fecha**: Timestamp de cuando ocurrió el evento
- **Usuario**: Nombre del usuario que realizó la acción (o "Sistema")
- **Evento**: Tipo de evento (created, updated, deleted, login, logout, etc.)

### Información de la Entidad

- **Entidad**: Tipo de modelo auditado (Usuario, Rol, etc.)
- **ID Entidad**: Identificador del registro específico auditado

### Información Técnica

- **IP**: Dirección IP desde donde se realizó la acción
- **URL**: Ruta de la aplicación donde ocurrió el evento
- **User Agent**: Información del navegador/cliente
- **Tags**: Etiquetas personalizadas del evento

### Detalles del Cambio

- **Valores Anteriores**: Estado previo de los campos modificados
- **Valores Nuevos**: Estado posterior de los campos modificados

## Filtros Disponibles

### Filtros de Usuario

- **ID Usuario**: Buscar por ID específico del usuario
- **Evento**: Filtrar por tipo de evento (created, updated, deleted, login, logout)

### Filtros de Entidad

- **Tipo de Entidad**: Filtrar por clase del modelo (App\Models\User, App\Models\Role, etc.)
- **ID Entidad**: Buscar por ID específico de la entidad auditada

### Filtros Técnicos

- **Dirección IP**: Buscar por IP específica o parcial
- **URL**: Filtrar por ruta de la aplicación
- **Tags**: Buscar por etiquetas del evento

### Filtros Temporales

- **Rango de Fechas**: Seleccionar período específico usando calendario

## Búsqueda Global

La búsqueda global (campo `q`) busca en los siguientes campos:

- Evento
- Dirección IP
- URL
- Tags

## Ordenamiento

### Columnas Ordenables

- ID
- Fecha de creación
- ID de usuario
- Evento
- Tipo de entidad
- ID de entidad
- Dirección IP
- URL

### Ordenamiento por Defecto

Los registros se ordenan por **fecha de creación descendente** (más recientes primero).

## Exportación

### Formatos Soportados

- **CSV**: Para análisis en hojas de cálculo
- **XLSX**: Excel nativo con formato
- **PDF**: Documento imprimible
- **JSON**: Para integración con APIs

### Columnas de Exportación

Por defecto se exportan las columnas más relevantes:

- ID
- Fecha
- Usuario
- Evento
- Entidad
- ID Entidad
- Dirección IP
- URL

### Limitaciones

- Límite de throttling para prevenir abuso
- Respeta los permisos del usuario (`auditoria.export`)

## Arquitectura Técnica

### Backend

```
├── Models/Audit.php (extiende owen-it/laravel-auditing)
├── Requests/AuditoriaIndexRequest.php
├── Policies/AuditPolicy.php
├── Repositories/AuditRepository.php
├── Services/AuditService.php
├── Controllers/AuditoriaController.php
└── routes/auditoria.php
```

### Frontend

```
├── pages/auditoria/index.tsx (página principal)
├── pages/auditoria/columns.tsx (definición de columnas)
└── pages/auditoria/AuditFilters.tsx (componente de filtros)
```

### Integración con el Sistema Base

- Extiende `BaseIndexController` para funcionalidad estándar
- Usa `BaseRepository` y `BaseService` para consistencia
- Integra con el sistema de permisos de Spatie
- Compatible con Inertia.js y TanStack Table v8

## Seguridad

### Control de Acceso

- Requiere autenticación (`auth` middleware)
- Validación de permisos vía `AuditPolicy`
- Throttling en endpoint de exportación

### Validación

- Validación de parámetros de consulta
- Sanitización de filtros
- Límites de paginación

## Casos de Uso

### Auditoría de Seguridad

- Revisar intentos de login/logout
- Monitorear cambios en usuarios privilegiados
- Detectar actividad desde IPs sospechosas

### Auditoría Operacional

- Rastrear cambios en configuraciones
- Revisar creación/modificación de registros
- Generar reportes de actividad por período

### Auditoría de Cumplimiento

- Exportar logs para auditorías externas
- Demostrar trazabilidad de cambios
- Mantener registros para regulaciones

## Consideraciones de Rendimiento

### Paginación

- Paginación server-side para manejar grandes volúmenes
- Límite máximo de 100 registros por página
- Paginación por defecto de 25 registros

### Índices de Base de Datos

La migración incluye índices optimizados para:

- Consultas por fecha de creación
- Filtros por usuario
- Búsquedas por evento
- Filtros por entidad auditada

### Carga de Relaciones

- Eager loading de la relación `user`
- Optimización de consultas N+1
