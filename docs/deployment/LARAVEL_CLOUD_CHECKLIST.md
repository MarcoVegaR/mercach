# 📋 HOJA DE RUTA: Preparación de Mercach para Laravel Cloud

**Estado:** Documento de planificación  
**Fecha:** Noviembre 2025

---

## 🎯 Objetivo

Preparar el código de Mercach para desplegarse en Laravel Cloud de forma profesional, segura y escalable.

---

## 📊 Decisión de Plan: ¿Starter o Growth?

### Comparativa

| Característica               | **Starter** ($0/mes) | **Growth** ($20/mes) |
| ---------------------------- | -------------------- | -------------------- |
| Deployments automáticos      | ✅                   | ✅                   |
| Apps/environments ilimitados | ✅                   | ✅                   |
| Custom domains               | ✅                   | ✅                   |
| SSL certificates             | ✅                   | ✅                   |
| Auto-hibernation             | ✅ (ahorra dinero)   | ✅                   |
| DDoS mitigation              | ✅                   | ✅                   |
| **Autoscaling**              | ❌                   | ✅                   |
| **Queue/Worker clusters**    | ❌                   | ✅                   |
| **Preview environments**     | ❌                   | ✅                   |
| **WAF básico**               | ❌                   | ✅                   |
| Compute máximo               | Flex (básico)        | Pro (más potente)    |
| PostgreSQL                   | Hasta 1 vCPU         | Hasta 4 vCPU         |
| Redis                        | Hasta 2.5GB          | Hasta 50GB           |

### Recomendación

**Fase 1 (40 usuarios): STARTER**

- ✅ $0 de costo base, solo pagas lo que usas
- ✅ Auto-hibernation reduce costos en horas inactivas
- ✅ Suficiente para ~40 usuarios con baja concurrencia
- ✅ Puedes migrar a Growth cuando lo necesites

**Fase 2 (300+ usuarios): GROWTH**

- ✅ Autoscaling para manejar picos de tráfico
- ✅ Workers dedicados para jobs pesados (exports, PDFs)
- ✅ Preview environments para testing antes de deploy

**Costo estimado:**

| Fase | Plan    | Usuarios | Costo/mes |
| ---- | ------- | -------- | --------- |
| 1    | Starter | 40       | ~$15-25   |
| 2    | Growth  | 300      | ~$70-100  |
| 3    | Growth  | 630      | ~$100-150 |

---

## ✅ CHECKLIST DE CAMBIOS NECESARIOS

### 1. Archivos de Configuración

#### 1.1 `.env.example` - Actualizar para producción

**¿Por qué?** Este archivo sirve como template. Los desarrolladores lo copian para crear su `.env` local. Laravel Cloud también lo usa como referencia.

**Cambios necesarios:**

```env
# Agregar secciones claras para producción vs local
# Documentar qué variables van en Secrets vs Variables

# Ejemplo de sección de drivers:
# Local: database | Producción: redis
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database

# Local: local | Producción: s3
FILESYSTEM_DISK=local
```

**Estado:** [ ] Pendiente

---

#### 1.2 `.env.testing` - Verificar compatibilidad CI

**¿Por qué?** PHPUnit y Playwright usan este archivo. Debe funcionar tanto en local (puerto 5434) como en CI (puerto 5432).

**Problema actual:** El archivo tiene `DB_PORT=5434` pero CI usa `5432`. El workflow define la variable de entorno que sobreescribe, pero es confuso.

**Solución:** El workflow de CI ya define las variables correctas. No se requiere cambio si el workflow las define explícitamente.

**Estado:** [✅] Resuelto (workflow sobreescribe)

---

### 2. GitHub Workflows

#### 2.1 `playwright.yml` - Corregir referencia a .env.e2e

**¿Por qué?** Eliminamos `.env.e2e` como archivo redundante. El workflow debe usar `.env.testing`.

**Cambio:**

```yaml
# Antes:
cp .env.e2e .env

# Después:
cp .env.testing .env
```

**Estado:** [✅] Completado

---

### 3. Health Check Endpoint

#### 3.1 `/health` - Endpoint de verificación

**¿Por qué?** Laravel Cloud (y cualquier orquestador) necesita verificar que la aplicación está funcionando. El health check debe:

1. Verificar que el servidor PHP responde
2. Verificar conexión a base de datos
3. Verificar conexión a cache (Redis)
4. Opcionalmente verificar storage (S3)

**Implementación sugerida:**

```php
// routes/web.php
Route::get('/health', function () {
    $checks = ['app' => 'ok'];

    try {
        DB::connection()->getPdo();
        $checks['database'] = 'ok';
    } catch (\Throwable) {
        $checks['database'] = 'error';
    }

    try {
        Cache::put('health', now(), 10);
        $checks['cache'] = 'ok';
    } catch (\Throwable) {
        $checks['cache'] = 'error';
    }

    $healthy = !in_array('error', $checks);
    return response()->json([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'checks' => $checks,
    ], $healthy ? 200 : 503);
});
```

**¿Por qué NO incluir storage en health check crítico?**

- S3/R2 puede tener latencia variable
- Un timeout de storage no debería marcar toda la app como "unhealthy"
- Es mejor monitorearlo por separado

**Estado:** [ ] Pendiente

---

### 4. Seguridad en Producción

#### 4.1 Protección contra `migrate:fresh` accidental

**¿Por qué?** `migrate:fresh` ELIMINA TODAS LAS TABLAS. En producción, esto borraría todos los datos. Es un error común que puede ser catastrófico.

**Opciones:**

**Opción A: Middleware en Kernel** (recomendada)

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if (app()->environment('production')) {
        Artisan::prohibit(['migrate:fresh', 'db:wipe']);
    }
}
```

**Opción B: Comando wrapper** (más complejo, menos recomendado)

- Crear comando personalizado con confirmaciones
- Más código que mantener

**Recomendación:** Opción A es más simple y efectiva.

**Estado:** [ ] Pendiente

---

### 5. Configuración de Servicios Externos

#### 5.1 SMS con Twilio (OPCIONAL)

**¿Por qué decidimos NO incluirlo ahora?**

- Agrega complejidad innecesaria para el MVP
- Requiere cuenta Twilio y costos adicionales
- Se puede agregar después sin cambios arquitectónicos

**Cuándo agregarlo:**

- Cuando tengas requerimiento concreto de notificaciones SMS
- Después de validar el flujo con email primero

**Estado:** [ ] Diferido (no necesario para v1.0)

---

### 6. Logging para Producción

#### 6.1 Configurar Slack para alertas críticas

**¿Por qué?** En producción necesitas saber inmediatamente si hay errores críticos. Slack es el canal más rápido.

**Configuración:**

```env
# .env (producción)
LOG_CHANNEL=stack
LOG_STACK=stderr,slack
LOG_LEVEL=warning
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx
```

**¿Por qué `stderr` además de Slack?**

- Laravel Cloud captura stderr automáticamente
- Puedes ver logs en el panel de Laravel Cloud
- Slack es para alertas, stderr es para debugging

**Estado:** [ ] Pendiente (configurar en deploy)

---

### 7. Variables de Entorno en Laravel Cloud

#### 7.1 Secretos (nunca en código)

Estos van en **Environment → Secrets**:

| Variable                   | Fuente                 |
| -------------------------- | ---------------------- |
| `BANK_GATEWAY_KEY`         | 100% Banco             |
| `BANK_GATEWAY_SECRET`      | 100% Banco             |
| `BANK_GATEWAY_MERCHANT_ID` | 100% Banco             |
| `BANK_GATEWAY_TERMINAL_ID` | 100% Banco             |
| `LOG_SLACK_WEBHOOK_URL`    | Slack Incoming Webhook |

#### 7.2 Variables públicas

Estas van en **Environment → Variables**:

```env
APP_NAME="Merca Chacao"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.laravel.cloud

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
FILESYSTEM_DISK=s3

BANK_GATEWAY_PATH=/100p2p/api/v1/ValTrxIn
```

**Estado:** [ ] Configurar en deploy

---

## 🔄 Flujo de Despliegue: GitHub → Laravel Cloud

### ¿Cómo funciona?

```
┌─────────────────────────────────────────────┐
│  1. DESARROLLADOR                           │
│     git push origin main                    │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  2. GITHUB                                  │
│     • Recibe push                           │
│     • Envía webhook a Laravel Cloud         │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  3. LARAVEL CLOUD - BUILD                   │
│     • Clona repositorio                     │
│     • composer install --no-dev             │
│     • npm ci && npm run build               │
│     • php artisan config:cache              │
│     • php artisan route:cache               │
│     • php artisan view:cache                │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  4. LARAVEL CLOUD - DEPLOY                  │
│     • php artisan migrate --force           │
│     • php artisan storage:link              │
│     • Health check: GET /health → 200       │
│     • Cambio de tráfico (zero-downtime)     │
└─────────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────────┐
│  5. PRODUCCIÓN ACTIVA                       │
│     https://mercach.laravel.cloud           │
└─────────────────────────────────────────────┘
```

### Tiempo estimado de deploy

| Fase         | Duración |
| ------------ | -------- |
| Build        | 3-5 min  |
| Deploy       | 30 seg   |
| Health check | 10 seg   |
| **TOTAL**    | ~4-6 min |

### Rollback

Si algo sale mal:

1. Panel → Deployments
2. Seleccionar deploy anterior (✅)
3. Click "Redeploy"
4. ~30 segundos para volver a versión anterior

---

## 📝 RESUMEN DE CAMBIOS A IMPLEMENTAR

### Prioridad Alta (bloqueantes)

| #   | Cambio                        | Archivo                            | Estado |
| --- | ----------------------------- | ---------------------------------- | ------ |
| 1   | Fix referencia .env.e2e en CI | `.github/workflows/playwright.yml` | ✅     |

### Prioridad Media (recomendados)

| #   | Cambio                          | Archivo                                | Estado |
| --- | ------------------------------- | -------------------------------------- | ------ |
| 2   | Agregar endpoint /health        | `routes/web.php`                       | [ ]    |
| 3   | Prohibir migrate:fresh en prod  | `app/Providers/AppServiceProvider.php` | [ ]    |
| 4   | Documentar variables de entorno | `.env.example`                         | [ ]    |

### Prioridad Baja (diferidos)

| #   | Cambio                 | Razón de diferir            |
| --- | ---------------------- | --------------------------- |
| 5   | Integración Twilio SMS | No necesario para MVP       |
| 6   | Sentry error tracking  | Laravel Pulse es suficiente |

---

## 🚀 PRÓXIMOS PASOS

1. **Commit del fix de CI** (ya hecho)
2. **Revisar esta hoja de ruta** contigo
3. **Implementar cambios prioridad media** cuando apruebes
4. **Crear cuenta Laravel Cloud** y conectar repo
5. **Configurar secretos** en panel
6. **Primer deploy** con seeders
7. **Validar** sistema funcionando

---

## ❓ Preguntas para Confirmar

1. **¿Apruebas empezar con plan Starter?**
2. **¿Quieres implementar el health check `/health`?**
3. **¿Quieres la protección contra `migrate:fresh`?**
4. **¿Tienes las credenciales de producción de 100% Banco?**
5. **¿Quieres configurar alertas Slack?**
