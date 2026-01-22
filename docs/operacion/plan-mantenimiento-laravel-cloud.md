---
title: 'Plan de mantenimiento y respaldos (Laravel Cloud)'
summary: 'Propuesta operativa: monitoreo, backups, restauración y ventanas de mantenimiento.'
icon: material/calendar-check
---

# Plan de mantenimiento y respaldos (Laravel Cloud)

## Arquitectura actual (Laravel Cloud)

Según el despliegue actual:

- **Edge network**: DDoS protection + CDN + edge caching.
- **Dominios**: `cobranzas.mercado.chacao.gob.ve` (verificado).
- **App cluster**: Flex **1 vCPU**.
    - **Scheduler**: habilitado.
    - **Background processes**: **1** (proceso en segundo plano).
- **Database**: **Serverless Postgres 18**.
- **Cache**: **Valkey** (Redis-compatible) **250 MB**.
- **Bucket**: `mercach-uploads` (S3) configurado como default.

## Objetivo

Definir una rutina operativa para:

- Mantener la aplicación estable y segura.
- Minimizar el riesgo de pérdida de datos.
- Asegurar que existen procedimientos y evidencias de restauración.

## Alcance

- Aplicación Laravel (API/SSR/Inertia), workers de cola, scheduler.
- Base de datos (PostgreSQL).
- Almacenamiento de archivos (preferiblemente S3 u objeto compatible).
- Logs y auditoría.

## Principios

- **Fuente de verdad para el dinero/estado**: Base de datos.
- **Backups verificables**: “Backup sin prueba de restore = no backup”.
- **Separación de entornos**: Producción, staging, desarrollo.
- **Menor privilegio**: accesos y llaves rotadas.

## Recomendaciones de arquitectura (para operar bien en Cloud)

- **Storage persistente**: todo archivo subido por usuarios debe ir a S3 (o equivalente). Evitar depender del disco del servidor.
- **Scheduler activo**: ejecutar `php artisan schedule:run` cada minuto en un scheduler administrado.
- **Queue workers**: workers administrados (auto-restart) y visibilidad de `failed_jobs`.
- **Timezone**: `APP_TIMEZONE=America/Caracas` y consistencia de parsing de fechas (pagos usan `paid_on` como DATE).

### Recomendación de variables de entorno (producción)

Estas variables alinean el código con la arquitectura (Valkey + S3 + TZ):

- **APP_TIMEZONE**: `America/Caracas`
- **QUEUE_CONNECTION**: preferible `redis` (Valkey) si el background process está configurado para workers; alternativa `database` si se decide mantener jobs en Postgres.
- **CACHE_STORE**: `redis` (para usar Valkey y evitar cache en DB).
- **UPLOADS_DISK**: `s3` (en `config/filesystems.php` se sugiere explícitamente para Laravel Cloud).
- **FILESYSTEM_DISK**: `s3` si quieres que también el default sea S3 (opcional; al menos uploads debe ser S3).
- **BCV\_\***: `BCV_URL`, `BCV_TIMEOUT`, `BCV_RETRY_ATTEMPTS`, `BCV_RETRY_DELAY`, `BCV_VERIFY_SSL` según política de red.

## Estrategia de respaldos

### 1) Base de datos (PostgreSQL)

**Objetivo**: RPO bajo (pérdida máxima aceptable) y restauración rápida.

- **Backups automáticos del proveedor**:
    - **Snapshots diarios**: mínimo 1 por día.
    - **PITR (Point-in-Time Recovery)** si está disponible (ideal).
- **Retención sugerida**:
    - **Diario**: 7-14 días.
    - **Semanal**: 4-8 semanas.
    - **Mensual**: 6-12 meses (depende de requisitos legales/operativos).
- **Cifrado**:
    - En reposo (proveedor) y en tránsito.

**Verificación**:

- Probar **restore** a un ambiente aislado (staging/restore) al menos **mensual**.
- Registrar evidencia: fecha, backup utilizado, tiempo de restore, validación de login y 2-3 consultas clave.

**Checks específicos para este sistema** (Postgres 18 serverless):

- Confirmar que las tablas operativas críticas existen y crecen razonablemente:
    - `payments`, `payment_allocations`, `charges`, `fx_rates`, `failed_jobs`, `audits`.
- Revisar índices/queries lentas en picos:
    - Perfil Económico (cargos y saldos) y listados de pagos.
- Control de soft deletes:
    - Pagos y tasas usan `deleted_at`; considerar limpieza programada (ver “Mensual”).

### 2) Archivos (S3 / Object Storage)

Si la app guarda archivos (comprobantes, reportes, adjuntos):

- **Versioning** habilitado en bucket.
- **Lifecycle policies**:
    - Retener versiones por 30-90 días.
    - Expirar versiones antiguas según volumen/costo.
- **Protección contra borrado** (según proveedor): MFA delete / object lock (si aplica).

**Ajuste a tu despliegue**:

- Bucket actual: **`mercach-uploads`**.
- Validar que las credenciales AWS y el bucket estén configurados y con permisos mínimos (solo el bucket necesario).

**Restore test**:

- Mensual: recuperar un set pequeño de objetos (aleatorio) y verificar integridad.

### 3) Configuración/Secretos

- Respaldar **inventario** de variables de entorno (sin exponer secretos). Guardar:
    - Lista de variables requeridas.
    - Procedimiento de rotación.
- Usar un **Secret Manager** del proveedor si existe.

## Plan de mantenimiento (rutinas)

### Diario (10-15 min)

- Revisar errores 5xx y endpoints críticos.
- Revisar cola:
    - Jobs en espera, latencia, `failed_jobs`.
- Confirmar que los **backups** del proveedor fueron ejecutados.
- Revisar espacio/uso (DB y storage) y alertas.

**Checks específicos de negocio (diario)**

- **Scheduler ejecutándose**: confirmar que se están ejecutando tareas programadas.
- **Tasa BCV**:
    - Verificar que `fx:ingest-bcv` está actualizando `fx_rates` (al menos 1 vez al día).
    - Si el BCV no publica temprano, el plan incluye ventana de tarde (ver abajo).

### Semanal (30-60 min)

- Revisar métricas:
    - Tiempo de respuesta, p95/p99.
    - Errores por método de pago/gateway.
- Revisar tabla `failed_jobs` (reintentos y causas).
- Revisar crecimiento de tablas críticas (pagos, auditorías, logs).
- Verificar integridad funcional rápida:
    - Login admin.
    - Registro de pago (DEB y/o método principal).
    - Consulta de perfil económico.

**Checks específicos de scheduler (semanal)**

Tus tareas programadas reales están definidas en `routes/console.php`:

- `contracts:expire` — **diario**.
- `charges:rent-m2` — **mensual** día 1 a las **01:00** (America/Caracas).
- `charges:rent-fixed` — **diario** a las **02:00** (America/Caracas).
- `charges:condo` — **mensual** día 1 a las **03:00** (America/Caracas).
- `fx:ingest-bcv` — **cada 15 min** entre **16:30–19:30** + fallback **08:15** (America/Caracas), con `onOneServer()` + `withoutOverlapping()`.

Recomendación: revisar 1 vez por semana que:

- Existen cargos del mes corriente (conteo razonable).
- No hay acumulación de fallos en `failed_jobs`.
- La tasa BCV del día está disponible antes del horario de mayor uso.

### Mensual (2-4 h)

- **Prueba de restauración** (DB) a entorno aislado.
- Revisión de seguridad:
    - Rotación de credenciales (si aplica).
    - Revisión de usuarios/admins y permisos.
- Dependencias:
    - Planificar actualización de Laravel/PHP/Composer deps.
    - Revisión de CVEs relevantes.
- Mantenimiento de DB:
    - Revisar índices faltantes.
    - Verificar autovacuum (Postgres) y bloat (si hay señales).

**Limpieza controlada (mensual)**

- Revisar retención de:
    - `audits` (auditoría) y `failed_jobs`.
    - Registros soft-deleted (según política).
- Revisar tamaño del bucket (y lifecycle/versioning funcionando).
- Revisar el uso de Valkey (250 MB) y claves con TTL (ej: cache de FX se guarda por 60s).

### Trimestral (medio día)

- Simulación de incidente:
    - "restore + smoke tests".
    - Medir RTO real.
- Revisión de costos (DB, storage, egress) y políticas de retención.

## Ventanas de mantenimiento (cambios y despliegues)

- Definir una ventana fija (ej: sábado 6:00–8:00am) para cambios mayores.
- Para migraciones:
    - Preferir migraciones compatibles (no bloquear largos periodos).
    - Para cambios grandes: estrategia expand/contract.
- Rollback:
    - Mantener release anterior listo.
    - Tener script/checklist de rollback.

## Monitoreo y alertas (mínimo viable)

- **Uptime** (HTTP): página de login + endpoint de salud.
- **Errores** (5xx) y excepciones: integrar un agregador (Sentry/Bugsnag) si se decide.
- **DB**:
    - Conexiones, locks, CPU, tamaño, tiempo de consultas.
- **Queue**:
    - Jobs fallidos por hora, tiempo promedio.

**Ajuste a tu stack**

- Laravel Cloud sugiere conectar **Nightwatch** (ideal para alertas y performance). Si se habilita:
    - Alertar por picos de 5xx.
    - Alertar si el scheduler deja de correr.
    - Alertar si el queue worker se cae (background process).

## Seguridad operativa

- Acceso por roles:
    - Admins mínimos necesarios.
    - MFA donde sea posible.
- Rotación:
    - Llaves de proveedor y credenciales DB (trimestral o ante incidentes).
- Auditoría:
    - Conservar logs/auditorías con retención alineada a compliance.

## Objetivos de continuidad (DR)

Definir y acordar:

- **RPO** (pérdida máxima de datos): sugerido 15 min–24 h (según disponibilidad de PITR).
- **RTO** (tiempo máximo para volver a operar): sugerido 1–4 h.

## Checklist de restauración (DB)

1. Crear instancia de DB restaurada desde snapshot/PITR.
2. Apuntar una app de staging/restore a esa DB.
3. Ejecutar:
    - Login.
    - Consulta de pagos recientes.
    - Registro de pago en modo prueba (si aplica).
4. Registrar evidencia: fecha, backup_id, duración, resultado.

## Checklist rápido de incidentes frecuentes (este sistema)

### A) No se actualiza la tasa BCV

- Verificar ejecuciones del scheduler (ventana 08:15 y 16:30–19:30).
- Revisar logs de `fx:ingest-bcv` (típico: cambios HTML del BCV o timeout).
- Aplicar contingencia:
    - Ejecutar sincronización manual desde catálogo de tasas (`FxRateController::sync`) o correr el comando.

### B) No aparecen cargos del mes/día

- Verificar scheduler (01:00, 02:00, 03:00 Caracas según tipo).
- Revisar errores del orquestador de cargos.
- Confirmar que existen mercados/tarifas vigentes.

## Entregables

- Calendario operativo (diario/semanal/mensual/trimestral).
- Lista de responsables y canal de alertas.
- Evidencias de restore mensuales.
