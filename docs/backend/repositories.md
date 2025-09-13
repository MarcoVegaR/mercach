---
title: 'Repositorios e Interfaces — Guía de Buenas Prácticas'
summary: 'Patrones y hooks del BaseRepository: búsqueda, filtros, ordenamiento, paginación, withRelations, filterMap y recomendaciones.'
icon: material/source-branch
tags:
    - explicación
    - backend
    - repositorios
---

# Repositorios e Interfaces — Guía de Buenas Prácticas

Esta guía describe el sistema de repositorios base implementado en el proyecto, diseñado para proveer funcionalidad completa de Index (búsqueda, filtros, ordenamiento, paginación), operaciones masivas y utilidades de concurrencia.

## Principios Arquitectónicos

### Contrato Primero

Los repositorios implementan interfaces específicas que definen el contrato de operaciones disponibles. Esto facilita testing, inyección de dependencias y intercambio de implementaciones.

```php
interface UserRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email): ?User;
    public function findActiveUsers(): Collection;
}
```

### Repositorios Sin Reglas de Dominio

Los repositorios se enfocan únicamente en persistencia y recuperación de datos. Las reglas de negocio deben implementarse en servicios de dominio separados.

**✅ Correcto**

```php
// En el repositorio
public function findActiveUsers(): Collection
{
    return $this->builder()->where('active', true)->get();
}

// En el servicio de dominio
public function promoteUserToAdmin(User $user): User
{
    if (!$user->canBePromoted()) {
        throw new InvalidOperationException('User cannot be promoted');
    }

    return $this->userRepository->update($user, ['role' => 'admin']);
}
```

**❌ Incorrecto**

```php
// Lógica de negocio en el repositorio
public function promoteToAdmin(User $user): User
{
    if (!$user->canBePromoted()) {
        throw new InvalidOperationException('User cannot be promoted');
    }

    return $this->update($user, ['role' => 'admin']);
}
```

## Implementación de Repositorios Concretos

### Estructura Básica

```php
<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected string $modelClass = User::class;

    protected function searchable(): array
    {
        return ['name', 'email', 'username'];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'email', 'created_at', 'updated_at', 'last_login_at'];
    }

    protected function defaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function filterMap(): array
    {
        return [
            'role' => fn(Builder $q, $value) => $q->where('role', $value),
            'verified' => fn(Builder $q, $value) => $q->whereNotNull('email_verified_at'),
            'recent_login' => fn(Builder $q, $value) => $q->where('last_login_at', '>=', now()->subDays($value)),
        ];
    }

    public function findByEmail(string $email): ?User
    {
        return $this->builder()->where('email', $email)->first();
    }

    public function findActiveUsers(): Collection
    {
        return $this->builder()->where('active', true)->get();
    }
}
```

### Hooks Disponibles

#### `searchable(): array`

Define las columnas que pueden ser buscadas con el parámetro `q` del ListQuery.

```php
protected function searchable(): array
{
    return ['name', 'email', 'username', 'bio'];
}
```

#### `allowedSorts(): array`

Whitelist de columnas permitidas para ordenamiento, previene inyección SQL.

```php
protected function allowedSorts(): array
{
    return ['id', 'name', 'email', 'created_at', 'updated_at', 'score'];
}
```

#### `defaultSort(): array`

Ordenamiento por defecto cuando no se especifica o es inválido.

```php
protected function defaultSort(): array
{
    return ['updated_at', 'desc'];
}
```

#### `filterMap(): array`

Define filtros personalizados más allá de los estándar.

```php
protected function filterMap(): array
{
    return [
        'role' => fn(Builder $q, $value) => $q->where('role', $value),
        'has_posts' => fn(Builder $q, $value) => $q->has('posts', $value ? '>=' : '=', $value ? 1 : 0),
        'joined_after' => fn(Builder $q, $value) => $q->where('created_at', '>=', $value),
    ];
}
```

#### `withRelations(Builder $builder): Builder`

Aplica eager loading por defecto.

```php
protected function withRelations(Builder $builder): Builder
{
    return $builder->with(['profile', 'roles'])->withCount(['posts', 'comments']);
}
```

## Filtros para Index

### Filtros Estándar

El sistema reconoce automáticamente varios tipos de filtros basados en convenciones de naming:

#### Filtro Equals

```php
// URL: ?filters[active]=true&filters[role]=admin
$query = new ListQuery(filters: ['active' => true, 'role' => 'admin']);
```

#### Filtro LIKE (case-insensitive)

```php
// URL: ?filters[name_like]=john
$query = new ListQuery(filters: ['name_like' => 'john']);
// Genera: WHERE LOWER(name) LIKE '%john%'
```

#### Filtro BETWEEN (rangos)

```php
// URL: ?filters[created_between][from]=2023-01-01&filters[created_between][to]=2023-12-31
$query = new ListQuery(filters: [
    'created_between' => ['from' => '2023-01-01', 'to' => '2023-12-31']
]);
```

#### Filtro IN (arrays)

```php
// URL: ?filters[status_in][]=active&filters[status_in][]=pending
$query = new ListQuery(filters: ['status_in' => ['active', 'pending']]);
```

#### Filtro IS NULL/NOT NULL

```php
// URL: ?filters[deleted_at_is]=null
$query = new ListQuery(filters: ['deleted_at_is' => 'null']);

// URL: ?filters[email_verified_at_is]=notnull
$query = new ListQuery(filters: ['email_verified_at_is' => 'notnull']);
```

#### Filtro de Conteo de Relaciones

```php
// URL: ?filters[posts_count]=5
$query = new ListQuery(filters: ['posts_count' => 5]);
// Genera: ->has('posts', '>=', 5)
```

### Filtros Personalizados

Usa `filterMap()` para lógica compleja:

```php
protected function filterMap(): array
{
    return [
        'subscription_status' => function (Builder $q, $value) {
            switch ($value) {
                case 'active':
                    $q->whereHas('subscription', fn($sq) => $sq->where('status', 'active'));
                    break;
                case 'expired':
                    $q->whereHas('subscription', fn($sq) => $sq->where('expires_at', '<', now()));
                    break;
                case 'none':
                    $q->whereDoesntHave('subscription');
                    break;
            }
        },

        'activity_level' => function (Builder $q, $value) {
            $days = match($value) {
                'high' => 7,
                'medium' => 30,
                'low' => 90,
                default => 30
            };

            $q->where('last_activity_at', '>=', now()->subDays($days));
        },
    ];
}
```

## Validación de Query

### FormRequest para Validación

La validación de parámetros de consulta debe realizarse en FormRequests, no en el DTO:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserIndexRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort' => 'nullable|string|in:id,name,email,created_at,updated_at',
            'dir' => 'nullable|string|in:asc,desc',
            'filters.active' => 'nullable|boolean',
            'filters.role' => 'nullable|string|in:user,admin,moderator',
            'filters.created_between.from' => 'nullable|date',
            'filters.created_between.to' => 'nullable|date|after_or_equal:filters.created_between.from',
        ];
    }
}
```

### Uso en Controladores

```php
<?php

namespace App\Http\Controllers;

use App\DTO\ListQuery;
use App\Http\Requests\UserIndexRequest;
use App\Contracts\Repositories\UserRepositoryInterface;

class UserController extends Controller
{
    public function index(UserIndexRequest $request, UserRepositoryInterface $repository)
    {
        $query = ListQuery::fromRequest($request);

        $users = $repository->paginate(
            query: $query,
            with: ['profile', 'roles'],
            withCount: ['posts', 'comments']
        );

        return response()->json($users);
    }
}
```

## Índices Recomendados (PostgreSQL)

### Índices Funcionales para Búsquedas Case-Insensitive

Para mejorar rendimiento en búsquedas de texto:

```sql
-- Índices funcionales LOWER() para columnas searchable
CREATE INDEX idx_users_name_lower ON users (LOWER(name));
CREATE INDEX idx_users_email_lower ON users (LOWER(email));

-- Índices compuestos para filtros comunes
CREATE INDEX idx_users_active_created_at ON users (active, created_at DESC);
CREATE INDEX idx_users_role_active ON users (role, active);

-- Índices para filtros de fecha con rangos
CREATE INDEX idx_users_created_at_btree ON users USING btree (created_at);
CREATE INDEX idx_users_updated_at_btree ON users USING btree (updated_at);
```

### Índices para Soft Deletes

```sql
-- Índice parcial para registros activos (no eliminados)
CREATE INDEX idx_users_active_not_deleted ON users (id) WHERE deleted_at IS NULL;

-- Índice compuesto incluyendo soft deletes
CREATE INDEX idx_users_active_deleted_at ON users (active, deleted_at);
```

## Scopes y Global Scopes

### Uso con Repositorios

Los scopes de Eloquent son compatibles con el sistema de repositorios:

```php
// En el modelo User
public function scopeActive(Builder $query): Builder
{
    return $query->where('active', true);
}

public function scopeVerified(Builder $query): Builder
{
    return $query->whereNotNull('email_verified_at');
}

// En el repositorio
protected function builder(): Builder
{
    return $this->modelClass::query()->active(); // Aplica scope por defecto
}

// O en métodos específicos
public function findVerifiedUsers(): Collection
{
    return $this->builder()->verified()->get();
}
```

### Global Scopes

Para filtros automáticos en todas las consultas:

```php
// Scope global
class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('active', true);
    }
}

// En el modelo
protected static function booted(): void
{
    static::addGlobalScope(new ActiveScope);
}

// En el repositorio, override para incluir inactivos cuando necesario
public function findAllIncludingInactive(): Collection
{
    return $this->modelClass::withoutGlobalScope(ActiveScope::class)->get();
}
```

## Integración con Frontend

### Inertia.js Partial Reloads

Para actualizaciones eficientes del Index:

```typescript
// resources/js/pages/Users/Index.tsx
import { router } from '@inertiajs/react';

const updateFilters = (newFilters: Record<string, any>) => {
    router.get(
        route('users.index'),
        { ...filters, ...newFilters },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['users'], // Partial reload
        },
    );
};
```

### TanStack Query v8 Server-Side

Para integración con TanStack Query:

```typescript
// resources/js/hooks/useUsers.ts
import { useQuery } from '@tanstack/react-query';

export const useUsers = (filters: UserFilters) => {
    return useQuery({
        queryKey: ['users', filters],
        queryFn: async () => {
            const response = await fetch(`/api/users?${new URLSearchParams(filters)}`);
            return response.json();
        },
        staleTime: 5 * 60 * 1000, // 5 minutos
    });
};
```

## Registro de Repositorios

### Service Provider

Registra los bindings en `app/Providers/DomainServiceProvider.php`:

```php
private function registerRepositories(): void
{
    $this->app->bind(
        \App\Contracts\Repositories\UserRepositoryInterface::class,
        \App\Repositories\UserRepository::class
    );

    $this->app->bind(
        \App\Contracts\Repositories\PostRepositoryInterface::class,
        \App\Repositories\PostRepository::class
    );
}
```

### Inyección en Servicios

```php
<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;

class UserService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function promoteUser(int $userId): User
    {
        return $this->userRepository->withPessimisticLockById($userId, function (User $user) {
            // Lógica de negocio aquí
            return $this->userRepository->update($user, ['role' => 'admin']);
        });
    }
}
```

## Alternativas

### Spatie Query Builder

Para casos simples, considera [Spatie Query Builder](https://spatie.be/docs/laravel-query-builder/v5/introduction):

```php
use Spatie\QueryBuilder\QueryBuilder;

public function index(Request $request)
{
    $users = QueryBuilder::for(User::class)
        ->allowedFilters(['name', 'email', Filter::exact('role')])
        ->allowedSorts(['name', 'email', 'created_at'])
        ->paginate($request->input('per_page', 15));

    return response()->json($users);
}
```

**Pros**: Menos código, funcionalidad inmediata
**Cons**: Menos control, más difícil de testear, mixing de responsabilidades

El sistema de repositorios base ofrece mayor flexibilidad y control para aplicaciones complejas.
