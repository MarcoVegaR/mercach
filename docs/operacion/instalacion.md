---
title: 'Instalación y requisitos'
summary: 'Requisitos de sistema y pasos de despliegue.'
icon: material/server
---

# Instalación y requisitos

## Requisitos
- SO Linux, CPU 2 vCPU, RAM ≥ 4 GB
- PHP 8.2+, Composer 2.x
- PostgreSQL 14+ (o el que se defina)
- Node.js 20+
- Servidor web (Nginx o Apache) y SSL si es público

## Pasos generales de despliegue
1. Clonar repositorio y configurar `.env` (APP_URL, DB_*, MAIL_*).
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan key:generate`
4. `php artisan migrate --seed`
5. `npm ci && npm run build`
6. Configurar el servidor web y supervisor/queue si aplica.
