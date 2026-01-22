---
title: 'Integración de Laravel Nightwatch (Laravel Cloud)'
summary: 'Checklist de instalación, configuración y validación para monitoreo con Nightwatch, cuidando costos y PII.'
icon: material/chart-timeline-variant
---

# Integración de Laravel Nightwatch (Laravel Cloud)

## Objetivo

Estandarizar los cambios requeridos para habilitar Laravel Nightwatch en este proyecto, priorizando:

- Observabilidad (requests, queries, outgoing requests, exceptions, jobs).
- Control de costos (sampling y niveles de log).
- Privacidad (redacción de PII en headers y payloads).

## Alcance

- Producción en Laravel Cloud.
- Staging (si existe) con configuración similar.

## Requisitos

- PHP ^8.2.
- Laravel ^10 (este proyecto usa Laravel ^12).

## 1) Instalación del paquete

En el repositorio:

- Ejecutar:
    - `composer require laravel/nightwatch`

Notas:

- El agente se ejecuta como un proceso separado (ver sección “Background processes” en Cloud).

## 2) Variables de entorno (Laravel Cloud)

Configurar en el environment del proyecto:

- `NIGHTWATCH_TOKEN`

Recomendado para iniciar (control de costos):

- `NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1`
- `NIGHTWATCH_QUERY_SAMPLE_RATE=0.1`
- `NIGHTWATCH_OUTGOING_REQUEST_SAMPLE_RATE=1.0`
- `NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0`

Recomendado para logs (evitar explosión de eventos):

- `NIGHTWATCH_LOG_LEVEL=warning`

Notas:

- Si se sube `NIGHTWATCH_LOG_LEVEL=info`, el módulo de pagos puede generar muchos eventos por el volumen de `Log::info(...)`.

## 3) Logging: enviar logs a Nightwatch (opcional, recomendado con filtro)

### Opción A (recomendada al inicio): Nightwatch sin logs

- Mantener logging solo a `stderr` (o `laravel-cloud-socket`), sin incluir `nightwatch`.

Esto reduce eventos y riesgo de PII accidental en logs.

### Opción B: Nightwatch con logs (controlado)

En Laravel Cloud, usar el stack recomendado incluyendo Nightwatch:

- `LOG_CHANNEL=stack`
- `LOG_STACK=laravel-cloud-socket,nightwatch`

Y mantener:

- `NIGHTWATCH_LOG_LEVEL=warning` (o `error`)

## 4) Redacción (PII / secretos)

Recomendado configurar redacción para evitar enviar datos sensibles.

### Headers

- `NIGHTWATCH_REDACT_HEADERS=Authorization,Cookie,Proxy-Authorization,X-XSRF-TOKEN`

### Payload

Si se habilita captura de payloads, redacción mínima sugerida:

- `NIGHTWATCH_REDACT_PAYLOAD_FIELDS=password,password_confirmation`

Ajuste recomendado para este sistema (pagos):

- Evaluar agregar campos como:
    - `payer_document_number`
    - `payer_phone_e164`
    - `payer_account_number`
    - `gateway_request`
    - `gateway_response`

Nota:

- Este sistema ya aplica masking en logs del gateway, pero Nightwatch puede capturar payloads/atributos por otros caminos.

## 5) Proceso Nightwatch Agent (Laravel Cloud)

En Laravel Cloud, habilitar un background process para el agente:

- Comando:
    - `php artisan nightwatch:agent`

Notas:

- Mantener 1 proceso del agente.
- Ajustar recursos si el consumo lo amerita.

## 6) Recomendación de “tags” / contexto

Para facilitar análisis por dominio, usar convenciones:

- Tag de dominio: `payment`
- Identificador de request: usar `X-Request-Id` si aplica (ya existe `App\Logging\RequestIdTap`).

## 7) Checklist de validación post-despliegue

### A) Verificación técnica

- Confirmar que el agente está corriendo y reportando.
- Confirmar que se ven eventos de:
    - Requests.
    - Queries.
    - Outgoing requests.
    - Exceptions.

### B) Smoke de pagos

Ejecutar un flujo real de pago (idealmente en staging):

- Portal:
    - Registrar pago (TRF o PMOV).
    - Validar que aparece:
        - 1 request del endpoint.
        - 1 outgoing request al banco.
        - Queries asociadas.

### C) Control de costo

- Revisar “Events/day” y proyección mensual.
- Ajustar sampling:
    - Subir o bajar `NIGHTWATCH_REQUEST_SAMPLE_RATE` y `NIGHTWATCH_QUERY_SAMPLE_RATE`.

### D) Privacidad

- Revisar que no se envíen:
    - Cuentas bancarias completas.
    - Documentos.
    - Teléfonos.
    - API keys.

## 8) Configuración recomendada para tu caso (baseline)

Si tu objetivo principal es observar pagos sin disparar costos:

- `NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1`
- `NIGHTWATCH_QUERY_SAMPLE_RATE=0.1`
- `NIGHTWATCH_OUTGOING_REQUEST_SAMPLE_RATE=1.0`
- `NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0`
- `NIGHTWATCH_LOG_LEVEL=warning`

## 9) Estimación rápida vs plan gratis (300.000 events/mes)

Con 500 registros de pago/mes, estimación típica solo para “registrar + verificar”:

- Sin logs en Nightwatch: ~15–25 events/pago → ~7.500–12.500 events/mes.
- Con logs a `warning`/`error`: impacto bajo.

Riesgo de exceder 300.000:

- Capturar 100% de requests/queries en toda la app con tráfico alto.
- Habilitar logs `info` hacia Nightwatch.
