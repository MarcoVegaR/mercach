---
title: 'Crear módulo Index'
summary: 'Paso a paso para crear un módulo de listado completo siguiendo los patrones del proyecto Mercach (permisos, Request, Repository, Service, Controller, rutas y frontend).'
icon: material/view-list
tags:
    - how-to
    - backend
    - frontend
---

# Crear módulo Index

Esta guía explica cómo crear un módulo de índice completo siguiendo los patrones del proyecto Mercach, basado en la implementación del módulo de Auditoría.

## 1. Configuración de Permisos

### Crear Archivo de Permisos

Crear `config/permissions/{modulo}.php`:

```php
<?php

declare(strict_types=1);

return [
    'permissions' => [
        '{modulo}.view',
        '{modulo}.export',
        // Agregar create, update, delete si aplica
    ],
];
```

### Registrar en PermissionsSeeder

Los permisos se integran automáticamente al PermissionsSeeder.

## 2. Modelo y Migración

### Crear/Adaptar Modelo

Si usas un modelo existente (como Audit), créalo extendiendo la clase base:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use OwenIt\Auditing\Models\Audit as BaseAudit;

class Audit extends BaseAudit
{
    // Relaciones y métodos adicionales
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

## 3. Request de Validación

### Crear IndexRequest

Extiende `BaseIndexRequest`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\BaseIndexRequest;

class {Modulo}IndexRequest extends BaseIndexRequest
{
    protected function getAllowedSorts(): array
    {
        return [
            'id',
            'created_at',
            'campo1',
            'campo2',
        ];
    }

    protected function getFilterRules(): array
    {
        return [
            'campo1' => 'nullable|string|max:255',
            'campo2' => 'nullable|integer',
            'created_between' => 'nullable|array',
            'created_between.from' => 'nullable|date',
            'created_between.to' => 'nullable|date|after_or_equal:created_between.from',
        ];
    }
}
```

## 4. Policy de Autorización

### Crear Policy

Extiende `BaseResourcePolicy`:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Policies\BaseResourcePolicy;

class {Modulo}Policy extends BaseResourcePolicy
{
    protected string $abilityPrefix = '{modulo}';
}
```

### Registrar en AuthServiceProvider

```php
protected $policies = [
    \App\Models\{Modelo}::class => \App\Policies\{Modulo}Policy::class,
];
```

## 5. Repository

### Crear Interface

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Contracts\Repositories\RepositoryInterface;

interface {Modulo}RepositoryInterface extends RepositoryInterface
{
}
```

### Implementar Repository

```php
<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\{Modulo}RepositoryInterface;
use App\Models\{Modelo};
use App\Repositories\BaseRepository;

class {Modulo}Repository extends BaseRepository implements {Modulo}RepositoryInterface
{
    public function __construct({Modelo} $model)
    {
        parent::__construct($model);
    }

    protected function getSearchableFields(): array
    {
        return [
            'campo1',
            'campo2',
        ];
    }

    protected function getAllowedSorts(): array
    {
        return [
            'id',
            'created_at',
            'campo1',
        ];
    }

    protected function getDefaultSort(): array
    {
        return ['created_at', 'desc'];
    }

    protected function getFilterMap(): array
    {
        return [
            'campo1' => 'campo1',
            'campo2' => 'campo2',
            'created_between' => function ($query, $value) {
                if (isset($value['from'])) {
                    $query->whereDate('created_at', '>=', $value['from']);
                }
                if (isset($value['to'])) {
                    $query->whereDate('created_at', '<=', $value['to']);
                }
            },
        ];
    }

    protected function getWithRelations(): array
    {
        return ['relacion1', 'relacion2'];
    }
}
```

## 6. Service

### Crear Interface

```php
<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Contracts\Services\ServiceInterface;

interface {Modulo}ServiceInterface extends ServiceInterface
{
}
```

### Implementar Service

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\{Modulo}ServiceInterface;
use App\Services\BaseService;

class {Modulo}Service extends BaseService implements {Modulo}ServiceInterface
{
    public function toRow($item): array
    {
        return [
            'id' => $item->id,
            'campo1' => $item->campo1,
            'campo2' => $item->campo2,
            'created_at' => $item->created_at?->format('d/m/Y H:i:s'),
            // Relaciones
            'relacion_name' => $item->relacion?->name,
        ];
    }

    protected function getDefaultExportColumns(): array
    {
        return [
            'ID',
            'Campo 1',
            'Campo 2',
            'Fecha de Creación',
        ];
    }

    protected function getExportFilename(): string
    {
        return '{modulo}_export_' . now()->format('Y_m_d_H_i_s');
    }
}
```

## 7. Controller

### Crear Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\BaseIndexController;
use App\Http\Requests\{Modulo}IndexRequest;
use App\Models\{Modelo};

class {Modulo}Controller extends BaseIndexController
{
    protected function policyModel(): string
    {
        return {Modelo}::class;
    }

    protected function view(): string
    {
        return '{modulo}/index';
    }

    protected function indexRequestClass(): string
    {
        return {Modulo}IndexRequest::class;
    }

    protected function getWithRelations(): array
    {
        return ['relacion1'];
    }

    protected function getAllowedExportFormats(): array
    {
        return ['csv', 'xlsx', 'pdf', 'json'];
    }
}
```

## 8. Routes

### Crear Archivo de Rutas

Crear `routes/{modulo}.php`:

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\{Modulo}Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/{modulo}', [{Modulo}Controller::class, 'index'])->name('{modulo}.index');
    Route::get('/{modulo}/export', [{Modulo}Controller::class, 'export'])
        ->middleware(['throttle:export'])
        ->name('{modulo}.export');
});
```

### Registrar en web.php

```php
// {Modulo} routes
require __DIR__ . '/{modulo}.php';
```

## 9. Frontend

### Crear Página Principal

`resources/js/pages/{modulo}/index.tsx`:

```tsx
import { DataTable } from '@/components/data-table'
import { PageHeader } from '@/components/page-header'
import AppLayout from '@/layouts/app-layout'
import { Head, router } from '@inertiajs/react'
import { columns } from './{modulo}-columns'
import { {Modulo}Filters } from './{Modulo}Filters'

interface Props {
  rows: any[]
  meta: {
    total: number
    per_page: number
    current_page: number
    last_page: number
  }
  filters?: any
  sort?: string
  dir?: string
  q?: string
}

export default function {Modulo}Index({ rows, meta, filters, sort, dir, q }: Props) {
  const handleFiltersChange = (newFilters: any) => {
    router.get(
      '/{modulo}',
      { ...newFilters, page: 1 },
      {
        only: ['rows', 'meta'],
        preserveState: true,
        preserveScroll: true,
      }
    )
  }

  const handleExport = (format: string) => {
    const params = new URLSearchParams(window.location.search)
    params.set('format', format)
    window.open(`/{modulo}/export?${params}`, '_blank')
  }

  return (
    <>
      <Head title="{Módulo}" />
      <PageHeader title="{Módulo}" />

      <div className="space-y-6">
        <{Modulo}Filters
          filters={filters}
          onFiltersChange={handleFiltersChange}
        />

        <DataTable
          columns={columns}
          data={rows}
          pagination={meta}
          onExport={handleExport}
          exportFormats={['csv', 'xlsx', 'pdf', 'json']}
        />
      </div>
    </>
  )
}

{Modulo}Index.layout = (page: React.ReactElement) => <AppLayout>{page}</AppLayout>
```

### Crear Definición de Columnas

`resources/js/pages/{modulo}/{modulo}-columns.tsx`:

```tsx
import { ColumnDef } from '@tanstack/react-table';
import { Badge } from '@/components/ui/badge';
import { DataTableColumnHeader } from '@/components/data-table-column-header';

export const columns: ColumnDef<any>[] = [
    {
        accessorKey: 'id',
        header: ({ column }) => <DataTableColumnHeader column={column} title="ID" />,
        cell: ({ row }) => row.getValue('id'),
    },
    {
        accessorKey: 'campo1',
        header: ({ column }) => <DataTableColumnHeader column={column} title="Campo 1" />,
        cell: ({ row }) => row.getValue('campo1'),
    },
    // Más columnas...
];
```

### Crear Componente de Filtros

`resources/js/pages/{modulo}/{Modulo}Filters.tsx`:

```tsx
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'

interface Props {
  filters?: any
  onFiltersChange: (filters: any) => void
}

export function {Modulo}Filters({ filters, onFiltersChange }: Props) {
  const handleFilterChange = (key: string, value: any) => {
    const newFilters = {
      ...filters,
      [key]: value || undefined,
    }

    // Limpiar filtros vacíos
    Object.keys(newFilters).forEach(k => {
      if (!newFilters[k]) delete newFilters[k]
    })

    onFiltersChange(newFilters)
  }

  const clearFilters = () => {
    onFiltersChange({})
  }

  return (
    <div className="grid gap-4 p-4 border rounded-lg">
      <div className="flex items-center justify-between">
        <h3 className="font-medium">Filtros</h3>
        <Button variant="outline" size="sm" onClick={clearFilters}>
          Limpiar filtros
        </Button>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        {/* Filtros específicos del módulo */}
      </div>
    </div>
  )
}
```

## 10. Sidebar

### Agregar al Menu

En `resources/js/components/app-sidebar.tsx`:

```tsx
{
    user?.permissions?.includes('{modulo}.view') && (
        <SidebarMenuItem>
            <SidebarMenuButton asChild>
                <Link href="/{modulo}">
                    <Icon />
                    <span>{Módulo}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
```

## 11. Service Provider

### Registrar Bindings

En `app/Providers/DomainServiceProvider.php`:

```php
// Repositories
$this->app->bind({Modulo}RepositoryInterface::class, {Modulo}Repository::class);

// Services
$this->app->bind({Modulo}ServiceInterface::class, {Modulo}Service::class);
```

## 12. Tests

### Feature Tests

Crear tests para Repository, Service, Controller y Permissions siguiendo los patrones del módulo de Auditoría.

## 13. Documentación

### Crear Documentación del Módulo

Crear `docs/modules/{modulo}.md` con descripción completa de funcionalidades, permisos, campos, filtros, y arquitectura.

## Checklist de Implementación

- [ ] Configurar permisos
- [ ] Crear/adaptar modelo
- [ ] Implementar Request de validación
- [ ] Crear Policy
- [ ] Implementar Repository e Interface
- [ ] Implementar Service e Interface
- [ ] Crear Controller
- [ ] Configurar rutas
- [ ] Implementar frontend (página, columnas, filtros)
- [ ] Agregar al sidebar
- [ ] Registrar bindings
- [ ] Crear tests completos
- [ ] Documentar el módulo

Este patrón garantiza consistencia, reutilización de código, y adherencia a las mejores prácticas del proyecto Mercach.
