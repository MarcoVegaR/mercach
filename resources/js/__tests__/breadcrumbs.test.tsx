import { Breadcrumbs } from '@/components/breadcrumbs';
import { auditCrumbs, resourceCrumbs, settingsCrumbs } from '@/lib/breadcrumbs';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

describe('breadcrumbs helpers', () => {
    it('resourceCrumbs users index', () => {
        const crumbs = resourceCrumbs('users', 'index');
        expect(crumbs).toEqual([
            { title: 'Inicio', href: '/dashboard' },
            { title: 'Usuarios', href: '/users' },
        ]);
    });

    it('resourceCrumbs users edit with id and name', () => {
        const crumbs = resourceCrumbs('users', 'edit', { id: 42, name: 'María' });
        expect(crumbs).toEqual([
            { title: 'Inicio', href: '/dashboard' },
            { title: 'Usuarios', href: '/users' },
            { title: 'María', href: '/users/42' },
            { title: 'Editar', href: '' },
        ]);
    });

    it('resourceCrumbs roles show', () => {
        const crumbs = resourceCrumbs('roles', 'show', { id: 7, name: 'Admin' });
        expect(crumbs).toEqual([
            { title: 'Inicio', href: '/dashboard' },
            { title: 'Roles', href: '/roles' },
            { title: 'Admin', href: '/roles/7' },
        ]);
    });

    it('auditCrumbs', () => {
        const crumbs = auditCrumbs();
        expect(crumbs).toEqual([
            { title: 'Inicio', href: '/dashboard' },
            { title: 'Auditoría', href: '/auditoria' },
        ]);
    });

    it('settingsCrumbs profile', () => {
        const crumbs = settingsCrumbs('profile');
        expect(crumbs).toEqual([
            { title: 'Inicio', href: '/dashboard' },
            { title: 'Ajustes', href: '/settings' },
            { title: 'Ajustes de perfil', href: '/settings/profile' },
        ]);
    });
});

describe('Breadcrumbs component rendering', () => {
    it('renders anchors for non-last items and a page span for the last', () => {
        const crumbs = resourceCrumbs('users', 'show', { id: 1, name: 'John Doe' });
        render(<Breadcrumbs breadcrumbs={crumbs} />);

        // First two are links (/dashboard and /users)
        const home = screen.getByRole('link', { name: 'Inicio' }) as HTMLAnchorElement;
        const users = screen.getByRole('link', { name: 'Usuarios' }) as HTMLAnchorElement;
        expect(home.tagName).toBe('A');
        expect(users.tagName).toBe('A');
        expect(home.getAttribute('href')).toBe('/dashboard');
        expect(users.getAttribute('href')).toBe('/users');

        // Last one is the current page (span)
        const current = screen.getByText('John Doe');
        expect(current.tagName).toBe('SPAN');
    });
});
