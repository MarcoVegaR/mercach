---
title: 'BaseIndexRequest — Validación de parámetros de Index'
summary: 'Referencia de BaseIndexRequest: validación, normalización, hooks (allowedSorts, filterRules, maxPerPage, sanitize) e integración con Inertia/TanStack.'
icon: material/filter
tags:
    - referencia
    - backend
    - requests
---

# BaseIndexRequest — Validación de parámetros de Index

El `BaseIndexRequest` es una clase abstracta que proporciona validación y normalización consistente para todos los endpoints de listado/índice en la aplicación. Se integra perfectamente con el sistema `BaseRepository`/`BaseService` a través del DTO `ListQuery`.

## Características principales

- **Validación completa**: parámetros de búsqueda, paginación, ordenamiento y filtros
- **Normalización automática**: tipos de datos, booleanos, rangos y valores por defecto
- **Extensibilidad**: hooks personalizables por módulo
- **Integración**: directa con `ListQuery`, `BaseRepository` y `BaseService`
- **Frontend-ready**: optimizado para Inertia.js y TanStack Table v8

## Campos soportados

| Campo     | Tipo           | Descripción                | Ejemplo                   |
| --------- | -------------- | -------------------------- | ------------------------- |
| `q`       | `string\|null` | Término de búsqueda global | `?q=john`                 |
| `page`    | `int`          | Página actual (≥1)         | `?page=2`                 |
| `perPage` | `int`          | Elementos por página       | `?perPage=25`             |
| `sort`    | `string\|null` | Campo de ordenamiento      | `?sort=created_at`        |
| `dir`     | `string\|null` | Dirección (`asc`\|`desc`)  | `?dir=desc`               |
| `filters` | `array`        | Filtros anidados           | `?filters[status]=active` |

## Implementación básica

### 1. Crear FormRequest específico

```php
<?php

namespace App\Http\Requests;

class UserIndexRequest extends BaseIndexRequest
{
    protected function allowedSorts(): array
    {
        return [
            'id',
            'name',
            'email',
            'created_at',
            'updated_at'
        ];
    }

    protected function filterRules(): array
    {
        return [
            'filters.status' => ['nullable', 'string', 'in:active,inactive'],
            'filters.role' => ['nullable', 'string', 'in:admin,user'],
            'filters.created_between' => ['nullable', 'array'],
            'filters.created_between.from' => ['nullable', 'date'],
            'filters.created_between.to' => ['nullable', 'date'],
            'filters.is_verified' => ['nullable', 'boolean'],
        ];
    }

    protected function maxPerPage(): int
    {
        return 50; // Límite específico para usuarios
    }
}
```

### 2. Usar en Controller

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserIndexRequest;
use App\Services\UserService;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService
    ) {}

    public function index(UserIndexRequest $request)
    {
        // Conversión automática a ListQuery
        $listQuery = $request->toListQuery();

        // Usar con el service
        $result = $this->userService->list($listQuery);

        // Respuesta optimizada para Inertia
        return inertia('Users/Index', [
            'users' => $result['rows'],
            'meta' => $result['meta'],
            'filters' => $request->input('filters', []),
        ]);
    }
}
```

### 3. Frontend (React + TanStack Table v8)

```tsx
import { useQuery } from '@tanstack/react-query';
import { router } from '@inertiajs/react';

export function UsersIndex({ users: initialUsers, meta: initialMeta, filters }) {
    const [params, setParams] = useState({
        q: '',
        page: 1,
        perPage: 15,
        sort: 'created_at',
        dir: 'desc',
        filters: filters || {},
    });

    // TanStack Query con Inertia partial reloads
    const { data } = useQuery({
        queryKey: ['users', params],
        queryFn: () => {
            return router.visit(route('users.index'), {
                method: 'get',
                data: params,
                only: ['users', 'meta'], // Partial reload
                preserveState: true,
            });
        },
        initialData: { users: initialUsers, meta: initialMeta },
    });

    // Integración con TanStack Table
    const table = useReactTable({
        data: data.users.data,
        columns,
        manualPagination: true,
        manualSorting: true,
        manualFiltering: true,
        pageCount: data.meta.pageCount,
        state: {
            pagination: {
                pageIndex: params.page - 1,
                pageSize: params.perPage,
            },
            sorting: params.sort
                ? [
                      {
                          id: params.sort,
                          desc: params.dir === 'desc',
                      },
                  ]
                : [],
        },
        onPaginationChange: (updater) => {
            const newPagination = typeof updater === 'function' ? updater({ pageIndex: params.page - 1, pageSize: params.perPage }) : updater;

            setParams((prev) => ({
                ...prev,
                page: newPagination.pageIndex + 1,
                perPage: newPagination.pageSize,
            }));
        },
    });

    return <DataTable table={table} />;
}
```

## Tipos de filtros soportados

### Filtros simples

```php
// URL: ?filters[status]=active
'filters.status' => ['nullable', 'string', 'in:active,inactive']
```

### Filtros de rango (between)

```php
// URL: ?filters[created_between][from]=2024-01-01&filters[created_between][to]=2024-12-31
'filters.created_between' => ['nullable', 'array'],
'filters.created_between.from' => ['nullable', 'date'],
'filters.created_between.to' => ['nullable', 'date'],
```

### Filtros de array (IN)

```php
// URL: ?filters[ids][]=1&filters[ids][]=2&filters[ids][]=3
'filters.ids' => ['nullable', 'array'],
'filters.ids.*' => ['integer', 'exists:users,id'],
```

### Filtros booleanos

```php
// URL: ?filters[is_verified]=true
'filters.is_verified' => ['nullable', 'boolean'],
```

### Filtros de texto con like

```php
// URL: ?filters[name_like]=john
'filters.name_like' => ['nullable', 'string', 'max:100'],
```

### Filtros nulos

```php
// URL: ?filters[deleted_at]=null
'filters.deleted_at' => ['nullable', 'string', 'in:null,notnull'],
```

## Normalización automática

### Booleanos

```php
// Input: ?filters[is_active]=true
// Normalizado: ['is_active' => true]

// Input: ?filters[is_active]=false
// Normalizado: ['is_active' => false]

// Input: ?filters[is_active]=1
// Normalizado: ['is_active' => true]
```

### Rangos between

```php
// Input: ?filters[age_between][from]=50&filters[age_between][to]=18
// Normalizado: ['age_between' => ['from' => '18', 'to' => '50']] // Intercambiado automáticamente
```

### Direcciones de ordenamiento

```php
// Input: ?dir=ASC
// Normalizado: 'asc'

// Input: ?dir=DESC
// Normalizado: 'desc'
```

## Hooks de extensibilidad

### Reglas de filtro personalizadas

```php
protected function filterRules(): array
{
    return [
        'filters.department_id' => ['nullable', 'integer', 'exists:departments,id'],
        'filters.salary_range' => ['nullable', 'array'],
        'filters.salary_range.min' => ['nullable', 'numeric', 'min:0'],
        'filters.salary_range.max' => ['nullable', 'numeric', 'min:0'],
        'filters.skills' => ['nullable', 'array'],
        'filters.skills.*' => ['string', 'in:php,javascript,python'],
    ];
}
```

### Límites personalizados

```php
protected function maxPerPage(): int
{
    // Límite más estricto para reportes complejos
    return 25;
}

protected function defaultPerPage(): int
{
    // Paginación más pequeña por defecto
    return 10;
}
```

### Sanitización personalizada

```php
protected function sanitize(array $validated): array
{
    // Limpiar espacios en búsqueda
    if (isset($validated['q'])) {
        $validated['q'] = trim($validated['q']);
    }

    // Convertir emails a lowercase en filtros
    if (isset($validated['filters']['email'])) {
        $validated['filters']['email'] = strtolower($validated['filters']['email']);
    }

    return $validated;
}
```

## Integración con Inertia.js

### Partial Reloads optimizados

```php
// Controller
public function index(UserIndexRequest $request)
{
    $result = $this->userService->list($request->toListQuery());

    return inertia('Users/Index', [
        'users' => $result['rows'],
        'meta' => $result['meta'],
        'filters' => $request->input('filters', []),
    ]);
}
```

```tsx
// Frontend - Solo actualizar datos necesarios
const updateFilters = (newFilters) => {
    router.visit(route('users.index'), {
        method: 'get',
        data: { ...params, filters: newFilters, page: 1 }, // Reset página
        only: ['users', 'meta'], // Solo actualizar datos, no filtros UI
        preserveState: true,
        preserveScroll: true,
    });
};
```

## Patrones de URL

### Búsqueda simple

```
GET /users?q=john&page=2&perPage=25&sort=name&dir=asc
```

### Filtros complejos

```
GET /users?filters[status]=active&filters[role]=admin&filters[is_verified]=true
```

### Rangos de fechas

```
GET /users?filters[created_between][from]=2024-01-01&filters[created_between][to]=2024-12-31
```

### Filtros múltiples

```
GET /users?filters[ids][]=1&filters[ids][]=2&filters[skills][]=php&filters[skills][]=javascript
```

## Best Practices

### 1. Validación defensiva

```php
protected function filterRules(): array
{
    return [
        // Siempre usar 'nullable' para filtros
        'filters.status' => ['nullable', 'string', 'in:active,inactive'],

        // Validar existencia para foreign keys
        'filters.department_id' => ['nullable', 'integer', 'exists:departments,id'],

        // Limitar longitud de strings
        'filters.search' => ['nullable', 'string', 'max:255'],

        // Validar arrays con wildcard
        'filters.tags' => ['nullable', 'array'],
        'filters.tags.*' => ['string', 'max:50'],
    ];
}
```

### 2. Campos ordenables seguros

```php
protected function allowedSorts(): array
{
    return [
        'id',
        'name',
        'email',
        'created_at',
        'updated_at',
        // NO incluir campos sensibles como 'password', 'token', etc.
    ];
}
```

### 3. Límites razonables

```php
protected function maxPerPage(): int
{
    // Considerar el impacto en performance
    return match($this->getResourceType()) {
        'users' => 100,
        'orders' => 50,
        'reports' => 25,
        default => 50,
    };
}
```

### 4. Testing exhaustivo

```php
/** @test */
public function validates_complex_filters()
{
    $request = UserIndexRequest::createFromBase(request());

    $data = [
        'q' => 'search term',
        'filters' => [
            'status' => 'active',
            'created_between' => [
                'from' => '2024-01-01',
                'to' => '2024-12-31'
            ],
            'is_verified' => 'true',
            'roles' => ['admin', 'user']
        ]
    ];

    $request->replace($data);
    $request->validateResolved();

    $listQuery = $request->toListQuery();

    $this->assertEquals('search term', $listQuery->q);
    $this->assertTrue($listQuery->filters['is_verified']);
    $this->assertIsArray($listQuery->filters['roles']);
}
```

## Troubleshooting

### Error: "Method allowedSorts() not found"

```php
// ❌ Incorrecto: usar BaseIndexRequest directamente
class UserController
{
    public function index(BaseIndexRequest $request) // Error!
}

// ✅ Correcto: crear clase concreta
class UserIndexRequest extends BaseIndexRequest
{
    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email'];
    }
}
```

### Error: Validación falla con filtros booleanos

```php
// ❌ Problema: string 'true' no valida como boolean
'filters.is_active' => 'true' // Falla validación

// ✅ Solución: BaseIndexRequest normaliza automáticamente
// 'true', 'false', '1', '0' → boolean real en prepareForValidation()
```

### Error: Rangos between incorrectos

```php
// ❌ Problema: from > to causa resultados vacíos
?filters[date_range][from]=2024-12-31&filters[date_range][to]=2024-01-01

// ✅ Solución: BaseIndexRequest intercambia automáticamente
// Resultado: from=2024-01-01, to=2024-12-31
```

### Performance: perPage muy alto

```php
// ❌ Problema: usuario solicita perPage=1000
?perPage=1000

// ✅ Solución: maxPerPage() limita automáticamente
protected function maxPerPage(): int
{
    return 100; // Máximo permitido
}
```

## Evolución y extensiones futuras

### Filtros avanzados

- Soporte para operadores personalizados (`>=`, `<=`, `!=`)
- Filtros por distancia geográfica
- Filtros de texto con fuzzy matching

### Performance

- Cache de queries complejas
- Índices automáticos basados en filtros frecuentes
- Paginación cursor-based para datasets grandes

### Analytics

- Tracking de filtros más usados
- Métricas de performance por endpoint
- Sugerencias de optimización automáticas
