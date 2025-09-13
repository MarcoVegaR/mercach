---
title: 'Settings — seguridad'
summary: 'Activa y gestiona la verificación en dos pasos (TOTP) y las sesiones activas del dispositivo.'
icon: material/shield-lock
tags:
    - manual
    - settings
    - seguridad
---

# Settings — Seguridad

En esta sección puedes reforzar la seguridad de tu cuenta activando la **autenticación en dos pasos (2FA)** con códigos temporales (TOTP) y administrar las **sesiones** abiertas en tus dispositivos.

La pantalla está implementada en `resources/js/pages/settings/security.tsx` y requiere Laravel Fortify.

## Requisitos previos

- Estar autenticado.
- Fortify instalado y configurado (rutas `/user/two-factor-*`).
- Rate limiter para `two-factor` (evita fuerza bruta de TOTP).

## Autenticación en dos pasos (TOTP)

La verificación en dos pasos añade un código extra (TOTP) generado por una app como Google Authenticator o Authy. Este código **cambia cada 30s**.

### Habilitar 2FA

1. Abre `Ajustes` → `Seguridad`.
2. Pulsa `Habilitar 2FA`.
3. Pulsa `Mostrar código QR` y escanéalo con tu app TOTP.
4. Ingresa el código de 6 dígitos en el **Input OTP** y pulsa `Confirmar 2FA`.
5. Verás una insignia `2FA activa` con la fecha de confirmación.

!!! note "Input OTP"
La pantalla usa un input de tipo OTP con 6 casillas. Puedes **pegar** los 6 dígitos y el componente los distribuirá automáticamente.

### Códigos de recuperación

Al habilitar 2FA se generan **códigos de recuperación** de un solo uso para cuando no tengas acceso a tu app TOTP.

- Botón `Ver códigos de recuperación`.
- Acciones disponibles:
    - `Copiar` al portapapeles.
    - `Descargar` como archivo de texto con nombre `appName-recovery-codes-YYYY-MM-DD.txt`.
    - `Regenerar códigos` (requiere confirmación). Los anteriores quedan invalidados.

!!! warning "Guárdalos en lugar seguro"
Los códigos de recuperación son tu respaldo para entrar si pierdes tu teléfono. Consérvalos en tu gestor de contraseñas.

### Deshabilitar 2FA

- Pulsa `Deshabilitar 2FA` y confirma. Por seguridad, se te pedirá la contraseña antes de completar la acción.

## Sesiones activas

En esta sección ves todas las sesiones abiertas con tu cuenta, agrupadas por **IP + dispositivo/navegador**.

- Cada fila muestra:
    - Icono del dispositivo (desktop o mobile) derivado del `User-Agent`.
    - IP de la sesión.
    - Ubicación aproximada (GeoIP) con un pin.
    - Fecha/hora de última actividad.
- Acciones disponibles:
    - `Refrescar` la lista.
    - `Cerrar` una sesión específica (no la actual).
    - `Cerrar todas (excepto esta)` con confirmación y verificación de contraseña.

!!! tip "Privacidad y precisión"
Para IPs privadas (por ejemplo `192.168.x.y`) se muestra "Red privada" o "Localhost". La ubicación aproximada se **cachea en localStorage** para evitar llamadas repetidas.

## Manejo de límites (429)

Si haces demasiadas peticiones (por ejemplo, QR/códigos seguidos), el sistema puede responder con **429 Too Many Requests**.

- Verás un contador de **enfriamiento** indicando en cuántos segundos puedes reintentar.
- La UI evita envíos mientras el contador está activo.

## Solución de problemas

- 2FA no funciona o las políticas devuelven 403:
    - Verifica que `AuthServiceProvider` está registrado en `bootstrap/providers.php` (Laravel 11/12 requiere registro explícito para cargar policies).
- El login de un usuario inactivo muestra el mensaje estándar y no el personalizado:
    - En este proyecto, `FortifyServiceProvider` personaliza el flujo para devolver `auth.inactive` cuando el usuario existe pero está inactivo.
- La ubicación no aparece:
    - Puede deberse a CORS del proveedor GeoIP o a que la IP es privada. Se muestra `Ubicación desconocida` de forma segura.

## Accesibilidad y UX

- Componentes y botones con `focus-visible` y `aria-label` donde procede.
- Insignias de estado: `Inactiva`, `Pendiente de confirmación`, `2FA activa`.
- Diálogos de confirmación para acciones destructivas (`Deshabilitar 2FA`, `Regenerar códigos`, `Cerrar todas`).

## Referencias

- Frontend: `resources/js/pages/settings/security.tsx`
- Controladores: `app/Http/Controllers/Settings/SecurityController.php`, `app/Http/Controllers/Settings/SessionsBrowserController.php`
- Fortify: `app/Providers/FortifyServiceProvider.php`
- Rate limiting: `app/Providers/AppServiceProvider.php`
