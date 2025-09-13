---
title: 'Flash Messages y Manejo de Errores (Inertia)'
summary: 'Patrón de redirects HTTP 303 con mensajes flash, integración con Inertia y Sonner, y criterios para usar flash vs páginas de error.'
icon: material/message-alert-outline
tags:
    - how-to
    - backend
    - inertia
    - errores
    - flash
---

# Flash Messages y Manejo de Errores (Inertia)

Esta guía explica el manejo consistente de mensajes flash y errores en el backend Laravel para una UX uniforme con Inertia.js y Sonner.

## Arquitectura

### Shared Data con Flash Messages

El middleware `HandleInertiaRequests` proporciona datos compartidos automáticamente a todas las páginas Inertia:

```php
// app/Http/Middleware/HandleInertiaRequests.php
public function share(Request $request): array
{
    return [
        // ... otros datos
        // Flash messages para toasts con Sonner
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error'   => fn () => $request->session()->get('error'),
            'info'    => fn () => $request->session()->get('info'),
        ],
        // Request ID para tracking y debugging
        'requestId' => $request->attributes->get('request_id'),
    ];
}
```

**Referencias:**

- [Inertia Shared Data](https://inertiajs.com/shared-data)
- [Laravel Session Flash Data](https://laravel.com/docs/session#flash-data)

### Patrón de Redirects con Flash

Los controladores que usan `HandlesIndexAndExport` siguen un patrón consistente:

```php
// ✅ Éxito: redirect con flash success
return $this->ok('users.index', [], '3 usuarios eliminados exitosamente');

// ❌ Error: redirect con flash error
return $this->fail('users.index', [], 'Error durante la operación');
```

Esto genera redirects **HTTP 303** (See Other) que Inertia convierte automáticamente en visitas de página.

## Implementación en Controladores

### Helper Methods

El trait `HandlesIndexAndExport` proporciona helpers para redirects consistentes:

```php
/**
 * Redirigir con mensaje de éxito
 */
protected function ok(string $routeName, array $params = [], ?string $message = null): RedirectResponse
{
    $redirect = redirect()->route($routeName, $params);

    if ($message !== null) {
        $redirect->with('success', $message);
    }

    return $redirect;
}

/**
 * Redirigir con mensaje de error
 */
protected function fail(string $routeName, array $params = [], ?string $message = null): RedirectResponse
{
    $redirect = redirect()->route($routeName, $params);

    if ($message !== null) {
        $redirect->with('error', $message);
    }

    return $redirect;
}
```

### Operaciones Bulk

Las operaciones masivas usan redirects en lugar de JSON responses:

```php
public function bulk(Request $request): RedirectResponse
{
    // ... validación y autorización

    try {
        $count = $this->service->bulkDeleteByIds($ids);
        $message = sprintf('%d registro(s) eliminados exitosamente', $count);

        return $this->ok($this->indexRouteName(), [], $message);
    } catch (DomainActionException $e) {
        return $this->fail($this->indexRouteName(), [], $e->getMessage());
    } catch (\Exception $e) {
        return $this->fail($this->indexRouteName(), [], 'Error durante la operación masiva');
    }
}
```

### Export con Error Handling

Las exportaciones manejan errores redirigiendo al index:

```php
public function export(BaseIndexRequest $request): StreamedResponse|RedirectResponse
{
    $this->authorize('export', $this->policyModel());

    try {
        // ... lógica de exportación
        return $this->service->export($dto, $format);
    } catch (DomainActionException $e) {
        return $this->fail($this->indexRouteName(), [], $e->getMessage());
    } catch (\Exception $e) {
        return $this->fail($this->indexRouteName(), [], 'Error durante la exportación');
    }
}
```

## Exception Handler

El manejador de excepciones convierte `DomainActionException` en redirects con flash para requests de Inertia:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->renderable(function (\App\Exceptions\DomainActionException $e, \Illuminate\Http\Request $request) {
        // Si es una request de Inertia, redirigir con flash error
        if ($request->hasHeader('X-Inertia')) {
            return back()->with('error', $e->getMessage());
        }

        // Para requests normales, usar el comportamiento por defecto
        return null;
    });
})
```

## Frontend Integration

### Layout con Sonner

El layout de React debe mostrar los toasts automáticamente:

```tsx
// resources/js/layouts/AppLayout.tsx
import { Toaster, toast } from 'sonner';
import { usePage } from '@inertiajs/react';

export default function AppLayout({ children }) {
    const { flash } = usePage().props;

    // Mostrar toasts cuando hay flash messages
    React.useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
        if (flash.info) {
            toast.info(flash.info);
        }
    }, [flash]);

    return (
        <div>
            {children}
            <Toaster position="top-right" />
        </div>
    );
}
```

### Operaciones Bulk desde Frontend

Las operaciones bulk se envían como formularios normales:

```tsx
// Ejemplo: Eliminar usuarios seleccionados
const handleBulkDelete = (selectedIds: number[]) => {
    router.post(route('users.bulk'), {
        action: 'delete',
        ids: selectedIds,
    });
    // El redirect + flash se maneja automáticamente
};
```

## Validación vs Flash Messages

**⚠️ Importante:** La validación Laravel sigue usando errores de campo, **NO** flash messages:

- **Validación (422):** Muestra errores específicos por campo
- **Flash Messages:** Para resultados de operaciones (éxito/error general)

```php
// ❌ NO hacer esto para errores de validación
return $this->fail('users.index', [], 'El email es requerido');

// ✅ Dejar que Laravel maneje validación normalmente
$request->validate(['email' => 'required']);
```

## Referencias Inertia

- [Shared Data](https://inertiajs.com/shared-data) - Datos disponibles en todas las páginas
- [Redirects](https://inertiajs.com/redirects) - Cómo Inertia maneja redirects HTTP 303
- [Validation](https://inertiajs.com/validation) - Manejo de errores de validación

## Casos de Uso

### ✅ Usar Flash Messages Para:

- Confirmaciones de operaciones bulk
- Resultados de exportación fallida
- Errores de negocio/dominio
- Mensajes informativos generales

### ❌ NO Usar Flash Messages Para:

- Errores de validación de formularios
- Errores HTTP (404, 500, etc.)
- Estados de carga/loading

## Testing

Los tests pueden verificar redirects y flash messages:

```php
$response = $this->post('/users/bulk', [
    'action' => 'delete',
    'ids' => [1, 2]
]);

$response->assertRedirect(route('users.index'));
$response->assertSessionHas('success', '2 registro(s) eliminados exitosamente');
```

## Ejemplo Completo

```php
class UserController extends Controller
{
    use HandlesIndexAndExport;

    public function bulk(Request $request): RedirectResponse
    {
        $this->authorize('update', User::class);

        $request->validate([
            'action' => 'required|in:delete,restore',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:users,id',
        ]);

        try {
            $count = match($request->action) {
                'delete' => $this->userService->bulkDelete($request->ids),
                'restore' => $this->userService->bulkRestore($request->ids),
            };

            $message = sprintf('%d usuario(s) %s exitosamente',
                $count,
                $request->action === 'delete' ? 'eliminados' : 'restaurados'
            );

            return $this->ok('users.index', [], $message);
        } catch (DomainActionException $e) {
            return $this->fail('users.index', [], $e->getMessage());
        }
    }

    protected function indexRouteName(): string
    {
        return 'users.index';
    }
}
```

Esta arquitectura garantiza una UX consistente y predecible en toda la aplicación.
