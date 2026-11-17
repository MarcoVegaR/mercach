import ShowPage from '@/pages/catalogs/contract/show';
import { cleanup, fireEvent, render, screen, within } from '@testing-library/react';
import { useState, type ReactNode } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const state = vi.hoisted(() => ({
    allowed: true,
    patch: vi.fn(),
    item: { id: 12, number: 'C-12', trade_category_id: 1, trade_category: 'Frutas', updated_at: '2026-11-17T10:00:00Z' },
}));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    router: {},
    Link: ({ children }: { children: ReactNode }) => <span>{children}</span>,
    usePage: () => ({
        props: {
            item: state.item,
            allowedActions: { canChangeTradeCategory: state.allowed },
            options: {
                trade_categories: [
                    { id: 1, name: 'Frutas' },
                    { id: 2, name: 'Hortalizas' },
                ],
            },
        },
    }),
    useForm: (initial: Record<string, unknown>) => {
        const [data, setData] = useState(initial);
        return {
            data,
            errors: {},
            processing: false,
            clearErrors: vi.fn(),
            setData: (key: string | Record<string, unknown>, value?: unknown) =>
                setData((previous) => (typeof key === 'string' ? { ...previous, [key]: value } : key)),
            reset: () => setData(initial),
            patch: (url: string, options: unknown) => state.patch(url, data, options),
        };
    },
}));
vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('@/components/show-base/ShowLayout', () => ({
    ShowLayout: ({ actions }: { actions: ReactNode }) => <>{actions}</>,
}));

beforeEach(() => {
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        },
    );
    Element.prototype.scrollIntoView = vi.fn();
    state.allowed = true;
    state.patch.mockClear();
    vi.stubGlobal('route', (name: string, id: number) => `/routes/${name}/${id}`);
});
afterEach(() => {
    cleanup();
    vi.unstubAllGlobals();
});

it('opens the dropdown inside the modal and submits only the trade category, reason and concurrency fields', async () => {
    render(<ShowPage />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar rubro' }));
    const modal = within(screen.getByRole('dialog'));
    expect(modal.getByLabelText('Rubro actual')).toHaveValue('Frutas');
    expect(modal.getByRole('button', { name: 'Guardar cambio' })).toBeDisabled();
    fireEvent.click(modal.getByRole('combobox', { name: 'Nuevo rubro' }));
    const option = await modal.findByRole('option', { name: 'Hortalizas' });
    expect(modal.queryByRole('option', { name: 'Frutas' })).not.toBeInTheDocument();
    expect(modal.getByPlaceholderText('Buscar rubro...')).toHaveFocus();
    fireEvent.click(option);
    expect(modal.getByRole('combobox', { name: 'Nuevo rubro' })).toHaveTextContent('Hortalizas');
    fireEvent.change(modal.getByLabelText('Motivo del cambio'), { target: { value: 'Según oficio' } });
    fireEvent.click(modal.getByRole('button', { name: 'Guardar cambio' }));
    expect(state.patch).toHaveBeenCalledWith(
        '/routes/catalogs.contract.trade-category/12',
        {
            trade_category_id: '2',
            previous_trade_category_id: 1,
            _version: '2026-11-17T10:00:00Z',
            reason: 'Según oficio',
        },
        expect.objectContaining({ preserveScroll: true }),
    );
});

it('cancels without sending changes', () => {
    render(<ShowPage />);
    fireEvent.click(screen.getByRole('button', { name: 'Editar rubro' }));
    fireEvent.click(within(screen.getByRole('dialog')).getByRole('button', { name: 'Cancelar' }));
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(state.patch).not.toHaveBeenCalled();
});

it('does not expose the action without authorization', () => {
    state.allowed = false;
    render(<ShowPage />);
    expect(screen.queryByRole('button', { name: 'Editar rubro' })).not.toBeInTheDocument();
});
