# 🚀 Guía de Despliegue: Mercach en Laravel Cloud

**Fecha:** Noviembre 2025  
**Versión:** 1.0.0  
**Ambiente:** Producción Controlada (40 → 300 → 630 usuarios)

---

## 📋 Tabla de Contenidos

1. [Resumen Ejecutivo](#resumen-ejecutivo)
2. [Servicios a Contratar](#servicios-a-contratar)
3. [Arquitectura de Despliegue](#arquitectura-de-despliegue)
4. [Configuración del Proyecto](#configuración-del-proyecto)
5. [Flujo de Despliegue GitHub → Laravel Cloud](#flujo-de-despliegue)
6. [Variables de Entorno](#variables-de-entorno)
7. [Primer Despliegue](#primer-despliegue)
8. [Operaciones Día a Día](#operaciones-dia-a-dia)

---

## Resumen Ejecutivo

### Stack Tecnológico

| Componente        | Tecnología                           |
| ----------------- | ------------------------------------ |
| **Backend**       | Laravel 12, PHP 8.2                  |
| **Frontend**      | React 19, TypeScript, Inertia.js SSR |
| **Base de Datos** | PostgreSQL 16                        |
| **Cache/Queue**   | Redis (en producción)                |
| **Storage**       | S3-compatible (Laravel Cloud)        |
| **CI/CD**         | GitHub Actions → Laravel Cloud       |

### Fases de Lanzamiento

| Fase  | Usuarios | Duración  | Costo Estimado |
| ----- | -------- | --------- | -------------- |
| **1** | 40       | Meses 1-2 | ~$45/mes       |
| **2** | 300      | Meses 3-6 | ~$117/mes      |
| **3** | 630      | Mes 7+    | ~$216/mes      |

---

## Servicios a Contratar

### Laravel Cloud (Panel Principal)

```
Proyecto: mercach
Región: us-east-1 (o más cercana a Venezuela)
```

**Fase 1 (40 usuarios):**

| Servicio   | Especificación          | Costo/mes |
| ---------- | ----------------------- | --------- |
| Web Server | Small (1 vCPU, 2GB RAM) | $15       |
| PostgreSQL | Small (10GB, 1GB RAM)   | $10       |
| Redis      | 512MB                   | $5        |
| S3 Storage | 10GB                    | $2        |
| Workers    | 1 dinámico              | $5        |
| **TOTAL**  |                         | **$37**   |

### Servicios Externos

| Servicio       | Proveedor     | Plan              | Costo/mes |
| -------------- | ------------- | ----------------- | --------- |
| SMS (opcional) | Twilio        | Pay-as-you-go     | ~$8       |
| Monitoring     | Laravel Pulse | Incluido          | $0        |
| Error Tracking | Sentry        | Free (<5k events) | $0        |

---

## Arquitectura de Despliegue

```
┌─────────────────────────────────────────────────────────────┐
│                        GITHUB                                │
│  ┌─────────────────────────────────────────────────────┐    │
│  │  main branch                                         │    │
│  │  ├── Push/Merge                                      │    │
│  │  └── Trigger deployment                              │    │
│  └─────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    LARAVEL CLOUD                             │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  BUILD PHASE                                          │   │
│  │  1. git clone                                         │   │
│  │  2. composer install --no-dev                         │   │
│  │  3. npm ci && npm run build:ssr                       │   │
│  │  4. php artisan config:cache                          │   │
│  │  5. php artisan route:cache                           │   │
│  │  6. php artisan view:cache                            │   │
│  └──────────────────────────────────────────────────────┘   │
│                              │                               │
│                              ▼                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │  DEPLOY PHASE                                         │   │
│  │  1. php artisan migrate --force                       │   │
│  │  2. php artisan storage:link                          │   │
│  │  3. Health check: GET /health                         │   │
│  │  4. Traffic switch (zero-downtime)                    │   │
│  └──────────────────────────────────────────────────────┘   │
│                              │                               │
│                              ▼                               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │  Web Server  │  │   Workers    │  │  Scheduler   │       │
│  │  (Nginx+PHP) │  │ (Queue jobs) │  │ (Cron jobs)  │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
│          │                 │                 │               │
│          └─────────────────┼─────────────────┘               │
│                            │                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐       │
│  │  PostgreSQL  │  │    Redis     │  │  S3 Storage  │       │
│  │  (Database)  │  │(Cache+Queue) │  │   (Files)    │       │
│  └──────────────┘  └──────────────┘  └──────────────┘       │
└─────────────────────────────────────────────────────────────┘
```

---

## Configuración del Proyecto

### Estructura de Archivos para Deploy

```
mercach/
├── .env.example              # Template para producción
├── .env.testing              # PHPUnit tests
├── .gitignore                # Incluye *.env excepto .example y .testing
├── composer.json             # Dependencias PHP
├── package.json              # Dependencias Node.js
├── vite.config.js            # Build config (incluye SSR)
└── resources/js/ssr.jsx      # Entry point SSR
```

### NO se requiere Dockerfile

Laravel Cloud genera automáticamente el contenedor optimizado basándose en:

- `composer.json` → versión PHP
- `package.json` → versión Node.js
- `vite.config.js` → configuración de build

---

## Flujo de Despliegue

### Cómo funciona GitHub → Laravel Cloud

```
1. DESARROLLADOR
   └── git push origin main

2. GITHUB
   └── Webhook notifica a Laravel Cloud

3. LARAVEL CLOUD
   ├── Clona repositorio
   ├── Ejecuta build steps
   ├── Ejecuta deploy steps
   ├── Health check
   └── Switch de tráfico (0 downtime)

4. PRODUCCIÓN
   └── Nueva versión activa
```

### Configurar Conexión GitHub

**En el Panel de Laravel Cloud:**

1. **New Project** → Nombre: `mercach`
2. **Connect Repository** → Seleccionar `MarcoVegaR/mercach`
3. **Branch** → `main`
4. **Auto-deploy** → ✅ Enabled (cada push a main despliega)

### Triggers de Deploy

| Evento              | Acción                       |
| ------------------- | ---------------------------- |
| Push a `main`       | Deploy automático            |
| Pull Request merged | Deploy automático            |
| Tag `v*`            | Deploy automático (opcional) |
| Manual              | Botón "Deploy" en panel      |

### Rollback

Si un deploy falla o introduce bugs:

1. Panel → Deployments
2. Seleccionar deployment anterior (green checkmark)
3. Click "Redeploy"
4. ~30 segundos para rollback

---

## Variables de Entorno

### Variables Públicas (en .env.example)

Estas pueden estar en el repositorio:

```env
APP_NAME="Merca Chacao"
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=America/Caracas
APP_URL=https://mercach.laravel.cloud

DB_CONNECTION=pgsql
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
FILESYSTEM_DISK=s3

BANK_GATEWAY_SCHEME=https
BANK_GATEWAY_HOST=www8.100x100banco.com
BANK_GATEWAY_PATH=/100p2p/api/v1/ValTrxIn
```

### Secrets (NUNCA en repositorio)

Configurar en: **Laravel Cloud → Environment → Secrets**

```env
# Auto-generados por Laravel Cloud
APP_KEY=base64:xxxxx
DB_HOST=xxxxx
DB_PASSWORD=xxxxx
REDIS_HOST=xxxxx
AWS_ACCESS_KEY_ID=xxxxx
AWS_SECRET_ACCESS_KEY=xxxxx

# Configurar manualmente
BANK_GATEWAY_KEY=tu_api_key_produccion
BANK_GATEWAY_SECRET=tu_secret_produccion
BANK_GATEWAY_MERCHANT_ID=341433
BANK_GATEWAY_TERMINAL_ID=userc2p

# Twilio (si usas SMS)
TWILIO_ACCOUNT_SID=ACxxxxx
TWILIO_AUTH_TOKEN=xxxxx
TWILIO_FROM_NUMBER=+1234567890

# Admin inicial
ADMIN_INITIAL_PASSWORD=TuPasswordSeguro2024!
```

---

## Primer Despliegue

### Checklist Pre-Deploy

```bash
# 1. Verificar que el código compila
npm run build:ssr
composer install --no-dev

# 2. Ejecutar tests
php artisan test
npm run typecheck
npm run lint

# 3. Commit y tag
git add .
git commit -m "chore: prepare for production deployment"
git tag -a v1.0.0 -m "Production release v1.0.0"
git push origin main --tags
```

### Pasos en Laravel Cloud

1. **Crear Proyecto**

    - New Project → `mercach`
    - Connect GitHub → `MarcoVegaR/mercach`
    - Branch: `main`

2. **Provisionar Servicios**

    - PostgreSQL → Create (Small)
    - Redis → Create (512MB)
    - S3 Storage → Create bucket `mercach-production`

3. **Configurar Secrets**

    - Environment → Secrets → Add all secrets

4. **Deploy Inicial**

    - Click "Deploy"
    - Esperar build (~5-8 min)

5. **Ejecutar Seeders (primera vez)**

    - Terminal → Connect

    ```bash
    php artisan migrate:fresh --seed --force
    ```

6. **Verificar**
    ```bash
    curl https://mercach.laravel.cloud/health
    # {"status":"healthy",...}
    ```

---

## Operaciones Día a Día

### Desplegar Nueva Versión

```bash
# Desde tu máquina local
git checkout main
git pull origin main
# ... hacer cambios ...
git add .
git commit -m "feat: nueva funcionalidad"
git push origin main
# → Deploy automático en ~5 minutos
```

### Ejecutar Comandos en Producción

**Terminal en Laravel Cloud:**

```bash
# Ver logs
php artisan pail

# Ejecutar comando manualmente
php artisan fx:ingest-bcv

# Ver estado del scheduler
php artisan schedule:list

# Limpiar cache
php artisan cache:clear
```

### Monitoreo

**Laravel Pulse:** `https://mercach.laravel.cloud/pulse`

- Requests/minuto
- Slow queries
- Exceptions
- Queue jobs

### Backup Manual

```
Panel → Databases → PostgreSQL → Create Backup
```

### Escalar Recursos

```
Panel → Services → Web Server → Resize
  Small → Medium (más CPU/RAM)
```

---

## Scheduler (Cron Jobs)

Los siguientes comandos se ejecutan automáticamente:

| Comando              | Frecuencia        | Hora (VE)   |
| -------------------- | ----------------- | ----------- |
| `fx:ingest-bcv`      | Cada 15 min       | 16:30-19:30 |
| `fx:ingest-bcv`      | Diario (fallback) | 08:15       |
| `contracts:expire`   | Diario            | 00:00       |
| `charges:rent-m2`    | Mensual (día 1)   | 01:00       |
| `charges:rent-fixed` | Diario            | 02:00       |
| `charges:condo`      | Mensual (día 1)   | 03:00       |

**Verificar en producción:**

```bash
php artisan schedule:list
```

---

## Troubleshooting

### Deploy Falla en Build

**Causa común:** Dependencias faltantes

```bash
# Verificar localmente
composer install --no-dev
npm ci
npm run build:ssr
```

### Health Check Falla

**Causa:** Servicios no disponibles

```bash
# En terminal Laravel Cloud
php artisan tinker
>>> DB::connection()->getPdo()  # Test DB
>>> Cache::get('test')          # Test Redis
>>> Storage::disk('s3')->exists('.health')  # Test S3
```

### Queue Jobs No Se Procesan

**Verificar:**

1. Panel → Workers → Status
2. `php artisan queue:monitor`
3. `php artisan queue:retry all`

---

## Contacto y Soporte

- **Laravel Cloud Docs:** https://cloud.laravel.com/docs
- **Laravel Discord:** https://discord.gg/laravel
- **Status Page:** https://status.laravel.com
