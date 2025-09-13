---
title: 'Páginas de Error (Inertia)'
summary: 'Cómo configurar y renderizar páginas de error 403, 404 y 500+ con Inertia en Laravel 12, y cuándo usar páginas vs flash messages.'
icon: material/alert-octagon-outline
tags:
    - how-to
    - backend
    - inertia
    - errores
---

# Manejo de Errores con Inertia

Este documento describe el sistema de manejo de errores coherente con Inertia.js, incluyendo páginas de error personalizadas y el uso de flash messages vs páginas de error.

## Páginas de Error Inertia

Para requests con el header `X-Inertia`, las excepciones HTTP se renderizan como componentes Inertia en lugar de páginas HTML tradicionales.

### Componentes de Error

#### 403 - Acceso Denegado

```tsx
// resources/js/Pages/Errors/403.tsx
export default function Error403() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center">
            <ShieldX className="text-destructive h-24 w-24" />
            <h1 className="text-4xl font-bold">403</h1>
            <p>No tienes permisos suficientes para acceder a esta página.</p>
        </div>
    );
}
```

#### 404 - Página No Encontrada

```tsx
// resources/js/Pages/Errors/404.tsx
export default function Error404() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center">
            <FileQuestion className="text-muted-foreground h-24 w-24" />
            <h1 className="text-4xl font-bold">404</h1>
            <p>La página que estás buscando no existe o ha sido movida.</p>
        </div>
    );
}
```

#### 500 - Error del Servidor

```tsx
// resources/js/Pages/Errors/500.tsx
export default function Error500() {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center">
            <ServerCrash className="text-destructive h-24 w-24" />
            <h1 className="text-4xl font-bold">500</h1>
            <p>Ha ocurrido un error interno del servidor.</p>
        </div>
    );
}
```

## Configuración del Exception Handler

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    // 403 - Access Denied
    $exceptions->renderable(function (AccessDeniedHttpException $e, Request $request) {
        if ($request->hasHeader('X-Inertia')) {
            return Inertia::render('Errors/403')->toResponse($request)->setStatusCode(403);
        }
        return null;
    });

    // 404 - Not Found
    $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
        if ($request->hasHeader('X-Inertia')) {
            return Inertia::render('Errors/404')->toResponse($request)->setStatusCode(404);
        }
        return null;
    });

    // 500+ - Server Errors
    $exceptions->renderable(function (HttpException $e, Request $request) {
        if ($request->hasHeader('X-Inertia') && $e->getStatusCode() >= 500) {
            return Inertia::render('Errors/500')->toResponse($request)->setStatusCode(500);
        }
        return null;
    });
})
```

## Flash Messages vs Páginas de Error

### Usar Flash Messages Para:

- **Errores de dominio/negocio**: `DomainActionException`
- **Validaciones fallidas**: Errores de input del usuario
- **Operaciones exitosas**: Confirmaciones de acciones
- **Advertencias**: Información contextual

```php
// Para errores de dominio - usar flash
if ($user->hasActiveSubscription()) {
    throw new DomainActionException('El usuario ya tiene una suscripción activa');
}
// → Se convierte en: back()->with('error', 'El usuario ya tiene...')
```

### Usar Páginas de Error Para:

- **Errores HTTP estructurales**: 403, 404, 500+
- **Errores de autorización**: Falta de permisos
- **Recursos no encontrados**: Entidades inexistentes
- **Errores del servidor**: Problemas técnicos

```php
// Para errores estructurales - usar páginas
Gate::authorize('delete', $post);
// Si falla → 403 Page
```

## DomainActionException

Excepción especial para errores de lógica de negocio que se convierten automáticamente en flash messages:

```php
// app/Exceptions/DomainActionException.php
namespace App\Exceptions;

use Exception;

class DomainActionException extends Exception
{
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
```

### Uso en Services y Controllers

```php
// En un Service
public function cancelSubscription(User $user): void
{
    if (!$user->hasActiveSubscription()) {
        throw new DomainActionException('No se puede cancelar una suscripción inexistente');
    }

    // Lógica de cancelación...
}

// En un Controller - se maneja automáticamente
public function cancel(Request $request)
{
    try {
        $this->subscriptionService->cancelSubscription($request->user());
        return $this->ok('subscriptions.index', [], 'Suscripción cancelada exitosamente');
    } catch (DomainActionException $e) {
        // Se maneja automáticamente por el Exception Handler
        // → back()->with('error', $e->getMessage())
    }
}
```

## Beneficios del Enfoque

### UX Coherente

- Flash messages para feedback inmediato
- Páginas de error para situaciones estructurales
- Transiciones suaves con Inertia

### Desarrollo Consistente

- Patron claro: DomainActionException = flash, HttpException = página
- Manejo automático en `bootstrap/app.php`
- Componentes reutilizables para errores

### Debugging Mejorado

- Request IDs en headers para correlación
- Context automático en logs
- Páginas de error informativas

## Testing

Nota (modo testing con Inertia):

- En entorno testing, el middleware `App\Http\Middleware\HandleInertiaRequests` elimina el header `X-Inertia` en solicitudes GET y añade un flag privado `_inertia_testing_view_mode` para forzar una respuesta Blade con la variable de vista `page`. Esto permite que `AssertableInertia` valide correctamente la respuesta.
- Los renderers en `bootstrap/app.php` también verifican ese flag y, para GET en testing, vuelven a quitar el header antes de renderizar `Errors/403`, `Errors/404` o `Errors/500` como páginas Inertia.

Ejemplo (resumen):

```php
// app/Http/Middleware/HandleInertiaRequests.php
if (app()->environment('testing') && $request->isMethod('GET') && $request->headers->has('X-Inertia')) {
    $request->attributes->set('_inertia_testing_view_mode', true);
    $request->headers->remove('X-Inertia');
}

// bootstrap/app.php (renderers)
if ($request->hasHeader('X-Inertia') || (bool) $request->attributes->get('_inertia_testing_view_mode')) {
    if (app()->environment('testing') && $request->isMethod('GET')) {
        $request->headers->remove('X-Inertia');
    }
    return Inertia::render('Errors/404')->toResponse($request)->setStatusCode(404);
}
```

Nota: si el componente inicial no tiene archivo físico (por ejemplo, `welcome`), puedes usar `->component('welcome', false)` en los tests para omitir la verificación de existencia del archivo.

```php
// Test para páginas de error
public function test_404_renders_inertia_page(): void
{
    $response = $this->withHeader('X-Inertia', 'true')
                     ->get('/nonexistent');

    $response->assertStatus(404);
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Errors/404')
    );
}

// Test para flash messages
public function test_domain_exception_redirects_with_flash(): void
{
    // Simular DomainActionException
    $response = $this->post('/action-that-fails');

    $response->assertRedirect();
    $response->assertSessionHas('error');
}
```
