import { type BreadcrumbItem } from '@/types';

const labels: Record<string, string> = {
    dashboard: 'Inicio',
    users: 'Usuarios',
    roles: 'Roles',
    auditoria: 'Auditoría',
    settings: 'Ajustes',
    profile: 'Ajustes de perfil',
    appearance: 'Ajustes de apariencia',
    password: 'Ajustes de contraseña',
};

const homeCrumb: BreadcrumbItem = { title: labels.dashboard, href: '/dashboard' };

function resourceBase(resource: 'users' | 'roles'): BreadcrumbItem[] {
    return [homeCrumb, { title: labels[resource], href: `/${resource}` }];
}

export function resourceCrumbs(
    resource: 'users' | 'roles',
    action: 'index' | 'show' | 'create' | 'edit',
    opts?: { id?: number | string; name?: string },
): BreadcrumbItem[] {
    const base = resourceBase(resource);

    switch (action) {
        case 'index':
            return base;
        case 'create':
            return [...base, { title: 'Crear', href: '' }];
        case 'show': {
            const id = opts?.id;
            const name = opts?.name ?? (resource === 'users' ? 'Usuario' : 'Rol');
            return [...base, { title: name, href: id ? `/${resource}/${id}` : '' }];
        }
        case 'edit': {
            const id = opts?.id;
            const name = opts?.name ?? (resource === 'users' ? 'Usuario' : 'Rol');
            const show = id ? [{ title: name, href: `/${resource}/${id}` } as BreadcrumbItem] : [];
            return [...base, ...show, { title: 'Editar', href: '' }];
        }
    }
}

export function auditCrumbs(): BreadcrumbItem[] {
    return [homeCrumb, { title: labels.auditoria, href: '/auditoria' }];
}

export function settingsCrumbs(section: 'profile' | 'appearance' | 'password'): BreadcrumbItem[] {
    const sectionTitle = labels[section];
    const sectionPath = `/settings/${section}`;
    return [homeCrumb, { title: labels.settings, href: '/settings' }, { title: sectionTitle, href: sectionPath }];
}
