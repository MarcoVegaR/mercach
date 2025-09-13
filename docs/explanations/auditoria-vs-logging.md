---
title: 'Auditoría vs logging'
summary: 'Explica qué se audita vs qué se registra en logs, con implementación en este proyecto (laravel-auditing + RequestId/Context) y buenas prácticas.'
icon: material/file-compare
tags:
    - explicación
    - auditoría
    - logging
---

# Explicación: Auditoría vs Logging

Esta página explica qué se audita y qué se registra en logs en este proyecto, y por qué.

## ¿Por qué separar auditoría y logging?

- La auditoría responde a "quién cambió qué y cuándo".
- El logging responde a "qué ocurrió en la ejecución" (trazabilidad técnica) y facilita depuración.

## Auditoría (laravel-auditing)

- `app/Models/User.php` implementa `OwenIt\Auditing\Contracts\Auditable` y usa el trait `Auditable`.
    - Excluye atributos sensibles en `$auditExclude`.
    - Usa propiedades temporales (`$auditEvent`, `$auditCustomOld`, `$auditCustomNew`, `$isCustomEvent`).
- Eventos personalizados (en `app/Providers/AppServiceProvider.php`):
    - Escucha `Login` y `Logout`.
    - Arma `auditCustomNew` con `ip`, `user_agent` (recortado) y `guard`.
    - Establece `auditEvent` (login/logout) y dispara `OwenIt\Auditing\Events\AuditCustom`.
    - Restablece estado temporal tras emitir.
- Persistencia: tabla `audits` creada por el paquete.

## Logging con Request ID

- Middleware `app/Http/Middleware/RequestId.php`:
    - Lee header `X-Request-Id` o genera UUID.
    - Lo agrega al response como `X-Request-Id` y a la request como atributo `request_id`.
- Monolog Tap `app/Logging/RequestIdTap.php`:
    - Processor que inyecta `extra.request_id` en cada log.
- Configuración `config/logging.php`:
    - Canal `stack` con `tap` => `App\Logging\RequestIdTap::class`.
    - En CI/local, `LOG_STACK=stderr` envía logs a `stderr`.

## Tests

- `tests/Feature/Infrastructure/RequestIdMiddlewareTest.php` verifica header `X-Request-Id` y respeto de ID entrante.
- Auditoría: se generan auditorías de login/logout automáticamente; inspeccionables en `audits` en desarrollo.

## Buenas prácticas

- Incluir `request_id` en logs externos (Sentry, Papertrail, etc.).
- Evitar auditar campos sensibles; usar `auditExclude` y normalizar `auditCustomNew`.
- Añadir asserts en tests para auditorías clave cuando aplique.
