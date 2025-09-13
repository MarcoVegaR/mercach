---
title: 'Logging con Context en Laravel 12'
summary: 'Cómo añadir request_id y user_id automáticamente a los logs con Laravel Context, integrarlo con Inertia y aplicarlo en jobs/APM.'
icon: material/file-chart
tags:
    - explicación
    - backend
    - logging
---

# Logging con Context en Laravel 12

Este documento describe el sistema de logging con context automático usando Laravel Context, incluyendo request_id y user_id para correlación y debugging mejorado.

## Laravel Context Overview

Laravel Context permite agregar datos contextuales que se incluyen automáticamente en todos los logs escritos durante una request. Esto mejora significativamente el debugging y monitoring.

## Implementación en RequestId Middleware

### Configuración Automática

```php
// app/Http/Middleware/RequestId.php
use Illuminate\Support\Facades\Context;

public function handle(Request $request, Closure $next): Response
{
    $requestId = $request->headers->get('X-Request-Id') ?: (string) Str::uuid();

    // Compartir en request attributes
    $request->attributes->set('request_id', $requestId);

    // Agregar al Laravel Context para logging automático
    Context::add([
        'request_id' => $requestId,
        'user_id' => $request->user()?->id,
    ]);

    $response = $next($request);
    $response->headers->set('X-Request-Id', $requestId);

    return $response;
}
```

### Datos de Context Incluidos

- **request_id**: UUID único por request para correlación
- **user_id**: ID del usuario autenticado (null para guests)

## Beneficios del Context

### Correlación de Logs

Todos los logs de una misma request comparten el mismo `request_id`:

```php
// En cualquier parte del código
Log::info('User attempted login', ['email' => $email]);
Log::error('Login failed', ['reason' => 'invalid_credentials']);

// Logs resultantes:
// [2024-01-15 10:30:15] INFO: User attempted login {"email":"user@example.com","request_id":"550e8400-e29b-41d4-a716-446655440000","user_id":123}
// [2024-01-15 10:30:15] ERROR: Login failed {"reason":"invalid_credentials","request_id":"550e8400-e29b-41d4-a716-446655440000","user_id":123}
```

### Debugging Distribuido

Con microservicios o workers, el `request_id` permite seguir una operación completa:

```php
// Controller
Log::info('Starting export process');
// → request_id: abc-123

// Job/Queue
Log::info('Processing export in background');
// → request_id: abc-123 (si se pasa el context)

// External API
Log::info('Calling external service', ['api_endpoint' => $url]);
// → request_id: abc-123
```

## Configuración de Logging

### Canales de Log Compatibles

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily'],
        'ignore_exceptions' => false,
    ],

    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'replace_placeholders' => true,
    ],

    // Context se incluye automáticamente en Laravel 12
    // No se necesita configuración adicional
],
```

### Formato de Logs con Context

Los logs incluyen automáticamente el context:

```json
{
    "message": "User login successful",
    "context": {
        "user_email": "user@example.com"
    },
    "level": 200,
    "level_name": "INFO",
    "channel": "local",
    "datetime": "2024-01-15T10:30:15.123456+00:00",
    "extra": {
        "request_id": "550e8400-e29b-41d4-a716-446655440000",
        "user_id": 123
    }
}
```

## Casos de Uso Avanzados

### Context Temporal

```php
// Agregar context temporal para una operación específica
Context::add(['operation' => 'bulk_import']);

try {
    $this->processImport($file);
    Log::info('Import completed successfully');
} finally {
    Context::forget('operation');
}
```

### Context en Jobs

```php
// Pasar context a jobs en background
class ProcessExport implements ShouldQueue
{
    public function handle(): void
    {
        // Restaurar context desde la request original si es necesario
        Context::add([
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
        ]);

        Log::info('Processing export in background');
        // Los logs mantienen correlación con la request original
    }
}
```

### Context Personalizado por Controller

```php
class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            Context::add(['controller' => 'UserController']);
            return $next($request);
        });
    }

    public function update(Request $request, User $user)
    {
        Context::add(['target_user_id' => $user->id]);

        Log::info('Updating user profile');
        // → Incluye controller, target_user_id, request_id, user_id
    }
}
```

## Integración con Inertia

### Request ID en Frontend

El request_id está disponible en el frontend vía Inertia shared data:

```tsx
// Layout o componente React
import { usePage } from '@inertiajs/react';

const Layout = ({ children }) => {
    const { requestId } = usePage().props;

    // Incluir en reportes de error del frontend
    useEffect(() => {
        window.addEventListener('error', (event) => {
            console.error('Frontend error', {
                message: event.message,
                request_id: requestId,
            });
        });
    }, [requestId]);
};
```

### Debugging Cross-Stack

```tsx
// En el frontend, enviar request_id en errores
const reportError = (error: Error) => {
    fetch('/api/errors', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Request-Id': requestId, // Del shared data de Inertia
        },
        body: JSON.stringify({
            message: error.message,
            stack: error.stack,
            request_id: requestId,
        }),
    });
};
```

## Monitoring y Alertas

### Queries por Request ID

```bash
# Buscar todos los logs de una request específica
grep "550e8400-e29b-41d4-a716-446655440000" storage/logs/laravel.log

# Con herramientas como ELK Stack
GET /logs/_search
{
  "query": {
    "term": {
      "extra.request_id": "550e8400-e29b-41d4-a716-446655440000"
    }
  }
}
```

### Alertas por Usuario

```bash
# Monitorear errores por usuario específico
GET /logs/_search
{
  "query": {
    "bool": {
      "must": [
        {"term": {"level_name": "ERROR"}},
        {"term": {"extra.user_id": 123}}
      ]
    }
  }
}
```

## Testing Context

### Verificar Context en Tests

```php
/** @test */
public function context_includes_request_id_and_user_id(): void
{
    $user = User::factory()->create();

    Route::get('/test-context', function () {
        $context = Context::all();
        Log::info('Test log message');

        return response()->json(['context' => $context]);
    })->middleware('web');

    $response = $this->actingAs($user)->get('/test-context');

    $response->assertOk();
    $data = $response->json();

    $this->assertArrayHasKey('request_id', $data['context']);
    $this->assertArrayHasKey('user_id', $data['context']);
    $this->assertEquals($user->id, $data['context']['user_id']);
}
```

### Mock Context en Tests

```php
protected function setUp(): void
{
    parent::setUp();

    // Context limpio para cada test
    Context::flush();
}

/** @test */
public function can_add_custom_context(): void
{
    Context::add(['test_context' => 'test_value']);

    $this->assertEquals('test_value', Context::get('test_context'));
}
```

## Best Practices

### Context Apropiado

- **Siempre incluir**: request_id, user_id
- **Por operación**: operation_type, target_resource_id
- **Para debugging**: component, method, step

### Context Inapropiado

- **Datos sensibles**: passwords, tokens, PII
- **Datos grandes**: response bodies, file contents
- **Datos dinámicos**: timestamps (ya incluidos por Laravel)

### Performance

- Context es muy eficiente en Laravel 12
- Se serializa una vez por log entry
- Uso mínimo de memoria adicional

### Limpieza

```php
// Limpiar context específico cuando sea necesario
Context::forget('temporary_operation');

// O limpiar todo el context (raramente necesario)
Context::flush();
```

## Integración con APM

### New Relic

```php
// Agregar request_id como atributo personalizado
if (extension_loaded('newrelic')) {
    newrelic_add_custom_parameter('request_id', Context::get('request_id'));
    newrelic_add_custom_parameter('user_id', Context::get('user_id'));
}
```

### DataDog

```php
// Tags personalizados para DataDog
app('datadog')->increment('requests.total', 1, [
    'request_id' => Context::get('request_id'),
    'user_id' => Context::get('user_id'),
]);
```
