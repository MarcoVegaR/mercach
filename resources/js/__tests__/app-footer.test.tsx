import { AppFooter } from '@/components/app-footer';
import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';

// Mock Inertia usePage and Link
vi.mock('@inertiajs/react', async () => {
    return {
        usePage: () => ({
            props: {
                name: 'Test App',
                auth: {
                    can: {
                        'users.view': true,
                        'roles.view': true,
                        'auditoria.view': true,
                    },
                },
                requestId: 'req-123',
            },
        }),
        Link: ({ children, href, ...rest }: { children: React.ReactNode; href: string } & React.AnchorHTMLAttributes<HTMLAnchorElement>) => (
            <a href={href} {...rest}>
                {children}
            </a>
        ),
    } as unknown;
});

describe('AppFooter', () => {
    it('renders full variant with column headers, product links, and social icons', () => {
        render(<AppFooter variant="full" showLanguage />);

        // Role landmark
        const footer = screen.getByRole('contentinfo');
        expect(footer).toBeInTheDocument();

        // Column headings
        expect(screen.getByText('Producto')).toBeInTheDocument();
        expect(screen.getByText('Recursos')).toBeInTheDocument();
        expect(screen.getByText('Legal')).toBeInTheDocument();
        expect(screen.getByText('Social')).toBeInTheDocument();

        // Product links gated by permissions
        expect(screen.getByRole('link', { name: 'Usuarios' })).toHaveAttribute('href', '/users');
        expect(screen.getByRole('link', { name: 'Roles' })).toHaveAttribute('href', '/roles');
        expect(screen.getByRole('link', { name: 'Auditoría' })).toHaveAttribute('href', '/auditoria');

        // Social button (GitHub only)
        expect(screen.getByLabelText('GitHub')).toBeInTheDocument();

        // Copyright and request ID
        const year = new Date().getFullYear();
        expect(screen.getByText(new RegExp(`©\\s${year}\\sCaracoders Pro Services`))).toBeInTheDocument();
        expect(screen.getByText(/Request ID:/)).toBeInTheDocument();
    });

    it('applies dark mode class', () => {
        render(<AppFooter variant="full" />);
        const footer = screen.getByRole('contentinfo');
        expect(footer.className).toContain('dark:bg-background');
    });

    it('renders minimal variant condensed bar', () => {
        render(<AppFooter variant="minimal" />);

        const footer = screen.getByRole('contentinfo');
        expect(footer).toBeInTheDocument();
        // Minimal does not render section headers
        expect(screen.queryByText('Producto')).not.toBeInTheDocument();
        // It should still render legal links condensed
        expect(screen.getByRole('link', { name: 'Términos' })).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Privacidad' })).toBeInTheDocument();
        // Social icon (GitHub) is present
        expect(screen.getByLabelText('GitHub')).toBeInTheDocument();
    });
});
