# 🚀 PLAN DE MIGRACIÓN A LARAVEL CLOUD

**Proyecto:** Mercach - Sistema de Gestión de Mercados Municipales  
**Fecha:** Diciembre 2025  
**Objetivo:** Migración controlada a producción con 40 usuarios iniciales

---

## 📊 ANÁLISIS PROFUNDO DEL CÓDIGO

### 1. Stack Tecnológico Actual

```
Backend:  Laravel 12 + PHP 8.2 + PostgreSQL
Frontend: React 19 + TypeScript + Inertia.js SSR + Vite
Testing:  PHPUnit + Pest + Playwright E2E
CI/CD:    GitHub Actions
```

### 2. Configuraciones que Requieren Cambios

#### 2.1 Drivers de Infraestructura

| Componente   | Local (actual)                 | Producción (Laravel Cloud)   | Archivo Config           |
| ------------ | ------------------------------ | ---------------------------- | ------------------------ |
| **Database** | PostgreSQL local (puerto 5434) | PostgreSQL Serverless (Neon) | `config/database.php`    |
| **Cache**    | `database`                     | `redis` (Valkey/Upstash)     | `config/cache.php`       |
| **Queue**    | `database`                     | `redis`                      | `config/queue.php`       |
| **Session**  | `database`                     | `redis`                      | `config/session.php`     |
| **Storage**  | `local` (storage/app/public)   | `s3` (Cloudflare R2)         | `config/filesystems.php` |

**¿Por qué cambiar?**

- **Cache/Queue en Redis:** Base de datos no está optimizada para operaciones de cache/queue. Redis es 10-100x más rápido.
- **Session en Redis:** Mejora rendimiento y permite escalado horizontal (múltiples servidores).
- **Storage en S3:** `storage/app/public` no persiste entre deployments en Laravel Cloud. S3 es el storage persistente.

#### 2.2 Archivos que Usan Storage Local

**Archivos identificados que subirán a S3:**

| Servicio                | Archivos                   | Ubicación Actual                                   | Migración Requerida |
| ----------------------- | -------------------------- | -------------------------------------------------- | ------------------- |
| `ConcessionaireService` | Fotos de concesionarios    | `storage/app/public/concessionaires/photos/`       | ✅ Automática       |
| `ConcessionaireService` | Documentos de identidad    | `storage/app/public/concessionaires/id_documents/` | ✅ Automática       |
| `CondoExpenseService`   | Adjuntos de gastos comunes | `storage/app/public/condo_expenses/`               | ✅ Automática       |
| `ReceiptPdfGenerator`   | PDFs de recibos            | `storage/app/public/receipts/`                     | ✅ Automática       |

**Código actual usa:**

```php
Storage::disk('public')->putFile('concessionaires/photos', $file);
Storage::disk('public')->url($path);
```

**En producción funcionará igual porque:**

- `FILESYSTEM_DISK=s3` hace que `public` disk apunte a S3
- URLs se generan automáticamente con dominio de R2
- No requiere cambios de código ✅

#### 2.3 Variables de Entorno Críticas

**Secrets (NUNCA en código):**

```env
# Auto-generados por Laravel Cloud
APP_KEY=base64:xxxxx
DB_HOST=xxxxx.neon.tech
DB_PASSWORD=xxxxx
REDIS_HOST=xxxxx.upstash.io
REDIS_PASSWORD=xxxxx
AWS_ACCESS_KEY_ID=xxxxx
AWS_SECRET_ACCESS_KEY=xxxxx
AWS_BUCKET=mercach-production

# Manuales (obtener de 100% Banco)
BANK_GATEWAY_KEY=xxxxx
BANK_GATEWAY_SECRET=xxxxx
BANK_GATEWAY_MERCHANT_ID=341433
BANK_GATEWAY_TERMINAL_ID=userc2p
```

**Variables Públicas:**

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mercach.laravel.cloud
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
FILESYSTEM_DISK=s3
BANK_GATEWAY_PATH=/100p2p/api/v1/ValTrxIn  # Producción (no /100p2pCert/)
```

---

## 🎯 DECISIÓN DE PLAN

### Comparativa de Planes

| Característica     | Starter ($0/mes) | Growth ($20/mes)     |
| ------------------ | ---------------- | -------------------- |
| **Costo base**     | $0               | $20                  |
| **Modelo**         | Pay-as-you-go    | Base + usage         |
| **Compute**        | Flex (básico)    | Pro (2x más potente) |
| **PostgreSQL**     | Hasta 1 vCPU     | Hasta 4 vCPU         |
| **Redis**          | Hasta 2.5GB      | Hasta 50GB           |
| **Autoscaling**    | ❌               | ✅                   |
| **Queue clusters** | ❌               | ✅                   |
| **Preview envs**   | ❌               | ✅                   |
| **WAF**            | ❌               | ✅ Básico            |

### Recomendación: **STARTER → GROWTH**

**Fase 1 (40 usuarios - Meses 1-2): STARTER**

- ✅ Sin costo fijo, solo pagas lo que usas
- ✅ Auto-hibernation (escala a 0 cuando no hay tráfico)
- ✅ Suficiente para 40 usuarios internos con baja concurrencia
- ✅ Custom domains incluido
- ✅ Todos los servicios necesarios (PostgreSQL, Redis, S3)

**Costo estimado:** ~$15-25/mes

**Fase 2 (300+ usuarios - Mes 3+): GROWTH**

- ✅ Autoscaling para manejar picos (horario laboral)
- ✅ Workers dedicados para jobs pesados (exports, PDFs masivos)
- ✅ Preview environments para testing antes de deploy
- ✅ Compute más potente (Pro: 2 vCPU, 512MB+ RAM)

**Costo estimado:** ~$70-100/mes

---

## 📝 CAMBIOS REQUERIDOS EN EL CÓDIGO

### Prioridad 1: CRÍTICOS (Bloqueantes)

#### 1.1 Actualizar `.env.example` con Documentación

**¿Por qué?**  
Este archivo es el template que usan desarrolladores y Laravel Cloud como referencia. Debe estar claro qué variables van en local vs producción.

**Cambios:**

```env
# =============================================================================
# MERCACH - Environment Configuration
# =============================================================================
# LOCAL:      database para cache/queue, local para storage
# PRODUCCIÓN: redis para cache/queue, s3 para storage
# =============================================================================

APP_NAME="Merca Chacao"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=America/Caracas
APP_URL=http://localhost

# =============================================================================
# CACHE, QUEUE & SESSION
# =============================================================================
# Local: database | Producción: redis
CACHE_STORE=database
CACHE_PREFIX=mercach_

QUEUE_CONNECTION=database

SESSION_DRIVER=database
SESSION_LIFETIME=120

# Redis (solo si usas redis en producción)
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# =============================================================================
# FILESYSTEM
# =============================================================================
# Local: local | Producción: s3
FILESYSTEM_DISK=local

# AWS S3 (Laravel Cloud provee estas credenciales automáticamente)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_URL=

# =============================================================================
# BANK GATEWAY (100% Banco)
# =============================================================================
# ⚠️ SECRETS: Configurar en Laravel Cloud Secrets, NUNCA commitear
BANK_GATEWAY_SCHEME=https
BANK_GATEWAY_HOST=www8.100x100banco.com
# Certificación: /100p2pCert/... | Producción: /100p2p/...
BANK_GATEWAY_PATH=/100p2pCert/api/v1/ValTrxIn
BANK_GATEWAY_KEY=
BANK_GATEWAY_SECRET=
BANK_GATEWAY_MERCHANT_ID=
BANK_GATEWAY_TERMINAL_ID=

# =============================================================================
# LOGGING
# =============================================================================
# Local: stderr | Producción: stderr,slack
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=debug
# LOG_SLACK_WEBHOOK_URL=  # Solo producción
```

**Archivo:** `.env.example`  
**Estado:** [ ] Pendiente

---

#### 1.2 Endpoint `/health` para Laravel Cloud

**¿Por qué?**  
Laravel Cloud necesita verificar que tu aplicación está funcionando correctamente antes de enviarle tráfico. Sin health check, los deployments pueden fallar.

**Implementación:**

```php
// routes/web.php

// Health check completo - verifica DB, Cache, Storage
Route::get('/health', function () {
    $checks = [
        'app' => 'ok',
        'database' => 'pending',
        'cache' => 'pending',
    ];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable) {
        $checks['database'] = 'error';
    }

    try {
        Cache::put('health_check', now()->timestamp, 10);
        Cache::get('health_check');
        $checks['cache'] = 'ok';
    } catch (\Throwable) {
        $checks['cache'] = 'error';
    }

    $healthy = !in_array('error', $checks);

    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
        'timestamp' => now()->toIso8601String(),
    ], $healthy ? 200 : 503);
})->middleware('throttle:60,1')->name('health');
```

**¿Por qué NO incluir storage en health check?**

- S3/R2 puede tener latencia variable
- Un timeout de storage no debería marcar toda la app como "unhealthy"
- Es mejor monitorearlo por separado con Laravel Pulse

**Archivo:** `routes/web.php`  
**Estado:** [ ] Pendiente

---

#### 1.3 Protección contra `migrate:fresh` en Producción

**¿Por qué?**  
`migrate:fresh` ELIMINA TODAS LAS TABLAS. Un error humano puede borrar toda la base de datos de producción. Esto es catastrófico.

**Implementación:**

```php
// app/Providers/AppServiceProvider.php

public function boot(): void
{
    // Prohibir comandos destructivos en producción
    if (app()->environment('production')) {
        Artisan::prohibit([
            'migrate:fresh',
            'migrate:refresh',
            'db:wipe',
        ]);
    }
}
```

**¿Qué pasa si alguien intenta ejecutarlo?**

```bash
$ php artisan migrate:fresh --force
Error: The "migrate:fresh" command is prohibited in production.
```

**Alternativa para reset de base de datos:**

1. Crear backup manual en Laravel Cloud panel
2. Usar consola de base de datos directamente
3. O contactar al equipo de desarrollo

**Archivo:** `app/Providers/AppServiceProvider.php`  
**Estado:** [ ] Pendiente

---

### Prioridad 2: RECOMENDADOS (Mejoran Operación)

#### 2.1 Logging con Slack para Alertas Críticas

**¿Por qué?**  
En producción necesitas saber INMEDIATAMENTE si hay errores críticos. Email es lento, Slack es instantáneo.

**Configuración:**

```env
# .env (producción)
LOG_CHANNEL=stack
LOG_STACK=stderr,slack
LOG_LEVEL=warning  # Solo warnings y errors
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx
```

**¿Cómo obtener el webhook?**

1. Ir a https://api.slack.com/apps
2. Create New App → From scratch
3. Incoming Webhooks → Activate → Add New Webhook
4. Copiar URL del webhook

**Archivo:** `config/logging.php` (ya configurado)  
**Variables:** Agregar `LOG_SLACK_WEBHOOK_URL` en Laravel Cloud  
**Estado:** [ ] Pendiente (configurar en deploy)

---

#### 2.2 Verificar Scheduler en Producción

**¿Por qué?**  
Tu aplicación tiene comandos programados (cron jobs) críticos:

| Comando              | Frecuencia                | Importancia                  |
| -------------------- | ------------------------- | ---------------------------- |
| `fx:ingest-bcv`      | Cada 15 min (16:30-19:30) | 🔴 Crítico (tasas de cambio) |
| `fx:ingest-bcv`      | Diario 08:15 (fallback)   | 🔴 Crítico                   |
| `contracts:expire`   | Diario 00:00              | 🟡 Importante                |
| `charges:rent-m2`    | Mensual día 1, 01:00      | 🔴 Crítico (facturación)     |
| `charges:rent-fixed` | Diario 02:00              | 🔴 Crítico (facturación)     |
| `charges:condo`      | Mensual día 1, 03:00      | 🟡 Importante                |

**Laravel Cloud ejecuta el scheduler automáticamente**, pero debes verificar:

```bash
# En terminal de Laravel Cloud después del deploy
php artisan schedule:list

# Debe mostrar todos los comandos programados
```

**Archivo:** `routes/console.php` (ya configurado)  
**Estado:** [✅] Ya configurado, solo verificar en deploy

---

#### 2.3 Configurar Pulse para Monitoreo

**¿Por qué?**  
Laravel Pulse está incluido en Laravel Cloud y te da métricas en tiempo real:

- Requests por minuto
- Slow queries
- Exceptions
- Queue jobs
- Cache hits/misses

**Configuración:**

```env
# .env (producción)
PULSE_ENABLED=true
PULSE_INGEST_DRIVER=database
```

**Acceso:** `https://mercach.laravel.cloud/pulse`

**Archivo:** `config/pulse.php` (si existe)  
**Estado:** [ ] Verificar en deploy

---

### Prioridad 3: OPCIONALES (Diferidos)

#### 3.1 SMS con Twilio

**Estado:** ⏸️ Diferido  
**Razón:** No es necesario para el MVP. Se puede agregar después sin cambios arquitectónicos.

#### 3.2 Sentry Error Tracking

**Estado:** ⏸️ Diferido  
**Razón:** Laravel Pulse (incluido) es suficiente para empezar. Sentry se puede agregar si necesitas más features (source maps, releases, etc.)

---

## 🔄 FLUJO DE DESPLIEGUE

### Cómo Funciona GitHub → Laravel Cloud

```
┌─────────────────────────────────────────────┐
│  1. DESARROLLADOR                           │
│     git push origin main                    │
└─────────────────────────────────────────────┘
                    │
                    ▼ Webhook automático
┌─────────────────────────────────────────────┐
│  2. LARAVEL CLOUD - BUILD (~4-6 min)        │
│     • git clone                             │
│     • composer install --no-dev             │
│     • npm ci && npm run build               │
│     • php artisan config:cache              │
│     • php artisan route:cache               │
│     • php artisan view:cache                │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  3. LARAVEL CLOUD - DEPLOY (~30 seg)        │
│     • php artisan migrate --force           │
│     • php artisan storage:link              │
│     • Health check: GET /health → 200       │
│     • Switch tráfico (zero-downtime)        │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  4. PRODUCCIÓN ACTIVA                       │
│     https://mercach.laravel.cloud           │
└─────────────────────────────────────────────┘
```

### Rollback Rápido

Si algo sale mal:

1. Panel → Deployments
2. Seleccionar deployment anterior (✅)
3. Click "Redeploy"
4. ~30 segundos para volver

---

## 📋 CHECKLIST DE IMPLEMENTACIÓN

### Pre-Deploy (En tu máquina)

- [ ] **1. Actualizar `.env.example`** con documentación clara
- [ ] **2. Agregar endpoint `/health`** en `routes/web.php`
- [ ] **3. Prohibir `migrate:fresh`** en `AppServiceProvider`
- [ ] **4. Commit cambios**
    ```bash
    git add .
    git commit -m "feat: prepare for Laravel Cloud deployment"
    git push origin main
    ```

### Configurar Laravel Cloud

- [ ] **5. Crear cuenta** en https://cloud.laravel.com
- [ ] **6. Crear proyecto** "mercach"
- [ ] **7. Conectar GitHub** repo `MarcoVegaR/mercach`
- [ ] **8. Seleccionar plan** Starter
- [ ] **9. Provisionar servicios:**
    - [ ] PostgreSQL Serverless (Neon)
    - [ ] Redis/Valkey (Upstash)
    - [ ] Object Storage (R2)

### Configurar Secrets

- [ ] **10. Environment → Secrets:**
    - [ ] `BANK_GATEWAY_KEY` (obtener de 100% Banco)
    - [ ] `BANK_GATEWAY_SECRET` (obtener de 100% Banco)
    - [ ] `BANK_GATEWAY_MERCHANT_ID` = `341433`
    - [ ] `BANK_GATEWAY_TERMINAL_ID` = `userc2p`
    - [ ] `LOG_SLACK_WEBHOOK_URL` (opcional)

### Configurar Variables

- [ ] **11. Environment → Variables:**
    ```env
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://mercach.laravel.cloud
    CACHE_STORE=redis
    QUEUE_CONNECTION=redis
    SESSION_DRIVER=redis
    FILESYSTEM_DISK=s3
    BANK_GATEWAY_PATH=/100p2p/api/v1/ValTrxIn
    LOG_CHANNEL=stack
    LOG_STACK=stderr
    LOG_LEVEL=warning
    ```

### Primer Deploy

- [ ] **12. Click "Deploy"** en panel
- [ ] **13. Esperar build** (~5-8 minutos)
- [ ] **14. Verificar health check:**
    ```bash
    curl https://mercach.laravel.cloud/health
    # Debe retornar: {"status":"healthy",...}
    ```

### Ejecutar Seeders (PRIMERA VEZ)

- [ ] **15. Terminal → Connect**
    ```bash
    php artisan migrate --seed --force
    ```

**⚠️ IMPORTANTE:** Esto ejecutará `HistoricalDebtsSeeder` que migra la deuda histórica. Solo se debe ejecutar UNA VEZ.

### Verificación Post-Deploy

- [ ] **16. Login admin** funciona
- [ ] **17. Cambiar contraseña** del admin
- [ ] **18. Verificar scheduler:**
    ```bash
    php artisan schedule:list
    ```
- [ ] **19. Verificar queue workers** en panel
- [ ] **20. Probar upload de foto** (debe ir a S3)
- [ ] **21. Probar validación bancaria** (ambiente cert primero)

### Antes de Producción Real

- [ ] **22. Cambiar a API de producción:**
    - Actualizar `BANK_GATEWAY_PATH=/100p2p/api/v1/ValTrxIn`
    - Re-deploy
- [ ] **23. Verificar credenciales** de producción del banco
- [ ] **24. Configurar backups** automáticos (Laravel Cloud panel)
- [ ] **25. Documentar** proceso de rollback para el equipo

---

## 💰 COSTOS ESTIMADOS

### Fase 1: Lanzamiento (40 usuarios)

| Recurso            | Uso Estimado         | Costo/mes       |
| ------------------ | -------------------- | --------------- |
| **Compute**        | ~400h activas/mes    | $8-12           |
| **PostgreSQL**     | 1GB storage, <1 vCPU | $3-5            |
| **Redis**          | 100MB usage          | $2-3            |
| **Object Storage** | 5GB + 2GB transfer   | $1-2            |
| **Bandwidth**      | 20GB                 | Incluido        |
| **TOTAL**          |                      | **~$15-25/mes** |

### Fase 2: Crecimiento (300 usuarios)

| Recurso            | Uso Estimado         | Costo/mes        |
| ------------------ | -------------------- | ---------------- |
| **Plan Growth**    | Base                 | $20              |
| **Compute**        | ~700h activas/mes    | $25-35           |
| **PostgreSQL**     | 5GB storage, 2 vCPU  | $10-15           |
| **Redis**          | 500MB usage          | $5-8             |
| **Object Storage** | 20GB + 10GB transfer | $3-5             |
| **TOTAL**          |                      | **~$70-100/mes** |

---

## 🎯 RESUMEN EJECUTIVO

### Cambios Mínimos Requeridos

1. ✅ **Fix CI** (ya completado)
2. 📝 **Actualizar `.env.example`** con documentación
3. 🏥 **Agregar `/health` endpoint**
4. 🔒 **Prohibir `migrate:fresh` en producción**

### Plan Recomendado

- **Inicio:** Plan Starter ($0 base + ~$15-25 usage)
- **Escalado:** Plan Growth ($20 base + ~$50-80 usage) cuando llegues a 100+ usuarios concurrentes

### Tiempo Estimado

| Fase                          | Duración      |
| ----------------------------- | ------------- |
| Implementar cambios en código | 1-2 horas     |
| Configurar Laravel Cloud      | 30 minutos    |
| Primer deploy + seeders       | 15 minutos    |
| Testing y validación          | 2-3 horas     |
| **TOTAL**                     | **4-6 horas** |

### Riesgos Identificados

| Riesgo                         | Probabilidad | Impacto | Mitigación                    |
| ------------------------------ | ------------ | ------- | ----------------------------- |
| Credenciales banco incorrectas | Media        | Alto    | Probar en cert primero        |
| Seeders fallan                 | Baja         | Medio   | Backup antes de ejecutar      |
| Storage S3 lento               | Baja         | Bajo    | R2 es muy rápido              |
| Scheduler no ejecuta           | Baja         | Alto    | Verificar con `schedule:list` |

---

## ❓ PREGUNTAS PARA CONFIRMAR

1. **¿Tienes las credenciales de PRODUCCIÓN de 100% Banco?**
2. **¿Quieres configurar alertas Slack desde el inicio?**
3. **¿Prefieres empezar con plan Starter o Growth?**
4. **¿Cuándo quieres hacer el primer deploy?**
5. **¿Necesitas ayuda para obtener el webhook de Slack?**

---

**Siguiente paso:** Implementar los 3 cambios críticos y proceder con la configuración de Laravel Cloud.
