---
title: 'Cloudflare Tunnel (dev)'
summary: 'Exponer Laravel y Vite en desarrollo con dos hosts (app y Vite HMR por WSS:443), proxies confiables y configuración del túnel.'
icon: material/cloud-outline
tags:
    - how-to
    - devops
    - cloudflare
---

# Cloudflare Tunnel (dev)

Esta guía explica cómo exponer tu entorno de desarrollo mediante Cloudflare Tunnel con dos hosts:

- demo.caracoders.com.ve → Laravel en http://127.0.0.1:8000
- vite.caracoders.com.ve → Vite Dev Server en http://127.0.0.1:5176 con HMR por WSS (puerto 443)

Con esto, el HTML de Laravel se sirve por `https://demo.caracoders.com.ve` y los módulos/recursos de Vite por `https://vite.caracoders.com.ve` con recarga en caliente (HMR) por `wss://vite.caracoders.com.ve:443`.

---

## Requisitos

- Tener instalado y autenticado `cloudflared`.
- Configurar DNS en Cloudflare para ambos subdominios (normalmente CNAME gestionado por el túnel).
- Proyecto Laravel 12 + Vite (React) en modo local.

---

## Cambios en el proyecto

### 1) Vite: origen y HMR por WSS (puerto 443)

Archivo: `vite.config.js`

```ts
export default defineConfig({
    // ...
    server: {
        host: '127.0.0.1',
        port: 5176,
        strictPort: true,
        cors: true,
        origin: 'https://vite.caracoders.com.ve',
        hmr: {
            protocol: 'wss',
            host: 'vite.caracoders.com.ve',
            clientPort: 443,
        },
    },
});
```

- `origin`: obliga a que las etiquetas de script/módulos apunten a `https://vite.caracoders.com.ve`.
- `hmr`: el cliente HMR se conecta por WSS al puerto 443 del túnel (Cloudflare termina TLS). Localmente Vite sigue sirviendo en 5176.
- `strictPort`: evita puertos aleatorios.

### 2) Laravel: confiar el proxy para HTTPS correcto

Archivo: `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    // ...
    $middleware->trustProxies(
        at: '*',
        headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO,
    );
})
```

Con esto, Laravel respeta los encabezados `X-Forwarded-*` que envía Cloudflare, detecta HTTPS y genera URLs absolutas y cookies seguras correctamente.

### 3) Flujo de desarrollo

Tu script actual `composer run dev` ya arranca Vite. El puerto fijo (5176) garantiza que el túnel pueda apuntar de forma estable.

---

## Configuración de Cloudflare Tunnel

Ejemplo mínimo de `~/.cloudflared/config.yml`:

```yaml
# Nombre/ID de túnel
# tunnel: <tu-tunnel-id-o-nombre>
# credentials-file: /home/<usuario>/.cloudflared/<tu-tunnel-id>.json

ingress:
    - hostname: demo.caracoders.com.ve
      service: http://127.0.0.1:8000
    - hostname: vite.caracoders.com.ve
      service: http://127.0.0.1:5176
    - service: http_status:404
```

Lanza el túnel:

```bash
cloudflared tunnel run <tu-tunnel>
```

---

## Puesta en marcha (desarrollo)

- Terminal A:

```bash
composer run dev
```

- Terminal B:

```bash
cloudflared tunnel run <tu-tunnel>
```

Abre `https://demo.caracoders.com.ve`. Verifica en DevTools que:

- Los módulos JS se carguen desde `https://vite.caracoders.com.ve`.
- El WebSocket HMR se conecte por `wss://vite.caracoders.com.ve:443`.

---

## Desactivar al pasar a producción

No necesitas revertir cambios de código para producción:

- En producción NO se usa Vite Dev Server, por lo que `server.*` de `vite.config.js` no aplica en `npm run build`.
- Mantener `trustProxies()` es recomendable si tu app seguirá detrás de Cloudflare o de un reverse proxy (Nginx/ALB). Si no hay proxy en producción, puedes restringirlo o eliminarlo.

Pasos típicos:

1. Detén `cloudflared` (no ejecutes el túnel en el servidor de producción).
2. Asegura `APP_URL=https://<tu-dominio>` en `.env` de producción.
3. Genera assets de producción y limpia caches:

```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

4. (Opcional) Restringe `trustProxies` si no usas proxy en prod:

```php
$middleware->trustProxies(at: ['127.0.0.1']);
// o comenta la línea si no la necesitas en absoluto.
```

5. Configura tu reverse proxy (Nginx/Apache) para pasar encabezados `X-Forwarded-*` si continúas detrás de un proxy/CDN.

---

## Notas y buenas prácticas

- En Cloudflare, usa modo SSL "Full" (o superior) y fuerza HTTPS.
- Si ves "mixed content" o el HMR intenta `ws://127.0.0.1:5176`, borra caché y valida `origin`/`hmr` en `vite.config.js`.
- Para trabajar sin túnel, basta con no ejecutar `cloudflared`; Vite seguirá en `http://127.0.0.1:5176`.
