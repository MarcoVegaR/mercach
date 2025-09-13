# Migas de pan (SPA) con Inertia + React

Este proyecto renderiza las migas de pan en el header de la app (SPA), dentro del layout principal, para que:

- No se recargue la página (Inertia Link)
- Mantengamos consistencia visual y de accesibilidad
- Centralicemos el armado de breadcrumbs y evitemos duplicación

## Componentes implicados

- `resources/js/components/breadcrumbs.tsx`: componente UI que pinta las migas.
    - Usa `<BreadcrumbLink asChild><Link href=... /></BreadcrumbLink>` para navegación SPA.
- `resources/js/layouts/app-layout.tsx` y `resources/js/layouts/app/app-sidebar-layout.tsx`:
    - El layout recibe `breadcrumbs` y los renderiza en el header por medio de `AppSidebarHeader`.
- `resources/js/lib/breadcrumbs.ts`: helper central para construir las migas por módulo/acción.

## Patrón recomendado

Cada página Inertia pasa los breadcrumbs al `AppLayout` desde su función `*.layout` usando el helper central.

Ejemplo para Users Create/Edit en `resources/js/pages/users/form.tsx`:

```tsx
import AppLayout from '@/layouts/app-layout';
import { resourceCrumbs } from '@/lib/breadcrumbs';

UserForm.layout = (page: any) => {
    const props = page?.props ?? {};
    const mode = (props?.mode as 'create' | 'edit') ?? 'create';
    const initial = props?.model ?? props?.initial ?? {};

    const crumbs = mode === 'edit' ? resourceCrumbs('users', 'edit', { id: initial?.id, name: initial?.name }) : resourceCrumbs('users', 'create');

    return <AppLayout breadcrumbs={crumbs}>{page}</AppLayout>;
};
```

Para Users Index:

```tsx
UsersIndex.layout = (page: any) => <AppLayout breadcrumbs={resourceCrumbs('users', 'index')}>{page}</AppLayout>;
```

Para Users Show:

```tsx
<AppLayout breadcrumbs={resourceCrumbs('users', 'show', { id: item.id, name: item.name })}>
  {...}
</AppLayout>
```

Roles usan el mismo patrón cambiando `'users'` por `'roles'`.

## Auditoría y Ajustes

- Auditoría (index):

```tsx
import { auditCrumbs } from '@/lib/breadcrumbs';
AuditoriaIndex.layout = (page: any) => <AppLayout breadcrumbs={auditCrumbs()}>{page}</AppLayout>;
```

- Ajustes (perfil, apariencia, contraseña):

```tsx
import { settingsCrumbs } from '@/lib/breadcrumbs';
<AppLayout breadcrumbs={settingsCrumbs('profile')}>
```

## Accesibilidad y SPA

- No usar `<a>` directos para navegar: el componente `Breadcrumbs` envuelve cada enlace con `Link` de Inertia usando `asChild`, por lo que no hay recarga completa.
- Mantener títulos consistentes en el helper para no repetir cadenas en las vistas.

## Ventajas

- Menos duplicación: solo declaras `resource`, `action` y opcionalmente `id`/`name`.
- Alegremente extensible: puedes añadir secciones en `breadcrumbs.ts` y todas las vistas se benefician.
- Coherencia visual: todos los módulos comparten la misma ubicación y estilo.
