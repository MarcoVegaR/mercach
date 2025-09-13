---
title: 'Rate Limiting para Endpoints Caros'
summary: 'Configurar y aplicar límites de tasa (exports, bulk) en Laravel 12 con limiters personalizados, middleware throttle y recomendaciones de UX.'
icon: material/speedometer
tags:
    - how-to
    - backend
    - seguridad
    - rendimiento
---

# Rate Limiting para Endpoints Caros

Este documento describe la implementación de rate limiting para proteger endpoints computacionalmente caros como exportaciones y operaciones bulk.

## Configuración de Rate Limiters

### Limiters Personalizados

```php
// bootstrap/app.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// Configurar limiters antes de Application::configure()
RateLimiter::for('exports', function (Request $request) {
    return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('bulk', function (Request $request) {
    return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
});
```

### Configuración por Usuario vs IP

- **Usuarios autenticados**: Rate limiting por `user_id`
- **Usuarios anónimos**: Rate limiting por `ip`
- **Ventaja**: Evita que un usuario autenticado abuse del sistema, pero permite múltiples usuarios anónimos desde la misma IP

## Aplicación en Rutas

### Middleware Throttle

```php
// En archivos de rutas (routes/web.php, etc.)
Route::middleware(['auth', 'throttle:exports'])
    ->get('/users/export', [UserController::class, 'export'])
    ->name('users.export');

Route::middleware(['auth', 'throttle:bulk'])
    ->post('/users/bulk', [UserController::class, 'bulk'])
    ->name('users.bulk');
```

### Aplicación Automática en HandlesIndexAndExport

```php
// En controladores que usan el trait
class UserController extends Controller
{
    use HandlesIndexAndExport;

    // Los métodos export() y bulk() del trait ya manejan rate limiting
    // cuando se aplica el middleware en las rutas
}
```

## Límites Recomendados

### Exportaciones (exports)

- **10 requests por minuto**
- **Justificación**: Las exportaciones suelen ser operaciones costosas de I/O y procesamiento
- **Casos de uso**: CSV, XLSX y JSON de grandes datasets

### Operaciones Bulk (bulk)

- **15 requests por minuto**
- **Justificación**: Operaciones de escritura masiva que afectan múltiples registros
- **Casos de uso**: Eliminar, restaurar, activar/desactivar múltiples elementos

### Consideraciones

- **Desarrollo**: Rate limiting deshabilitado en entorno local
- **Testing**: Usar `RateLimiter::clear()` en tests para reset
- **Producción**: Monitorear métricas de 429 responses

## Respuestas HTTP 429

### Headers de Rate Limit

Laravel incluye automáticamente headers informativos:

```http
HTTP/1.1 429 Too Many Requests
X-RateLimit-Limit: 10
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1640995200
Retry-After: 60
```

### Manejo en Frontend

```tsx
// Interceptor para requests con rate limiting
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 429) {
            const retryAfter = error.response.headers['retry-after'];
            toast.error(`Límite excedido. Intenta en ${retryAfter} segundos.`);
        }
        throw error;
    },
);
```

## Customización Avanzada

### Límites Dinámicos

```php
RateLimiter::for('exports', function (Request $request) {
    $user = $request->user();

    // Usuarios premium tienen límites más altos
    if ($user?->isPremium()) {
        return Limit::perMinute(50)->by($user->id);
    }

    // Límites estándar
    return Limit::perMinute(10)->by($user?->id ?: $request->ip());
});
```

### Múltiples Límites

```php
RateLimiter::for('api', function (Request $request) {
    return [
        Limit::perMinute(60),           // 60 por minuto
        Limit::perHour(1000),          // 1000 por hora
        Limit::perDay(10000),          // 10000 por día
    ];
});
```

### Límites por Ruta Específica

```php
// Para endpoints específicos muy costosos
RateLimiter::for('heavy-export', function (Request $request) {
    return Limit::perMinute(2)->by($request->user()?->id ?: $request->ip());
});

// Aplicar en ruta específica
Route::middleware(['auth', 'throttle:heavy-export'])
    ->get('/reports/full-export', [ReportController::class, 'fullExport']);
```

## Testing Rate Limiting

### Test Básico

```php
/** @test */
public function export_endpoint_is_rate_limited(): void
{
    // Hacer N requests hasta el límite
    for ($i = 0; $i < 10; $i++) {
        $response = $this->actingAs($user)->get('/users/export');
        $response->assertStatus(200);
    }

    // El siguiente request debe fallar
    $response = $this->actingAs($user)->get('/users/export');
    $response->assertStatus(429);
}
```

### Reset en Testing

```php
protected function setUp(): void
{
    parent::setUp();

    // Limpiar rate limiters entre tests
    RateLimiter::clear('exports');
    RateLimiter::clear('bulk');
}
```

## Monitoring y Alertas

### Logs de Rate Limiting

```php
// Opcional: Log cuando se alcanza el límite
RateLimiter::for('exports', function (Request $request) {
    return Limit::perMinute(10)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function () {
                    Log::warning('Rate limit exceeded for exports', [
                        'user_id' => request()->user()?->id,
                        'ip' => request()->ip(),
                    ]);
                });
});
```

### Métricas Recomendadas

- **Contador de 429 responses**
- **Rate limit hit rate por endpoint**
- **Usuarios que más exceden límites**
- **Tiempo promedio hasta reset**

## Beneficios

### Protección del Sistema

- Previene abusos de recursos costosos
- Mantiene performance para todos los usuarios
- Protege contra ataques DoS simples

### UX Predecible

- Headers informativos para el frontend
- Mensajes claros sobre límites
- Tiempo de espera conocido (Retry-After)

### Escalabilidad

- Rate limiting distribuido con Redis
- Configuración flexible por entorno
- Monitoring integrado con Laravel
