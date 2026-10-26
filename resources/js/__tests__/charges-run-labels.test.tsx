import ChargesRunPage from '@/pages/charges/run';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, expect, it, vi } from 'vitest';

const { setData } = vi.hoisted(() => ({ setData: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    usePage: () => ({ props: {} }),
    useForm: (data: Record<string, unknown>) => ({ data, setData, processing: false }),
}));

vi.mock('@/layouts/app-layout', () => ({
    default: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

afterEach(() => {
    cleanup();
    vi.clearAllMocks();
});

it('displays common expenses labels while retaining the backend charge codes', () => {
    render(
        <ChargesRunPage
            options={{
                types: [
                    { value: 'ALL', label: 'Todos (M2, Fijo, Condominio)' },
                    { value: 'RENT_EUR_M2', label: 'Alquiler por m² (EUR)' },
                    { value: 'CONDO_USD', label: 'Condominio (USD)' },
                ],
                markets: [],
            }}
        />,
    );

    expect(screen.getByRole('option', { name: 'Todos (M2, Fijo, Gastos comunes)' })).toHaveValue('ALL');
    expect(screen.getByRole('option', { name: 'Gastos comunes (USD)' })).toHaveValue('CONDO_USD');
    expect(screen.getByRole('option', { name: 'Alquiler por m² (EUR)' })).toHaveValue('RENT_EUR_M2');
    expect(screen.queryByText(/Condominio/)).not.toBeInTheDocument();

    fireEvent.change(screen.getAllByRole('combobox')[0], { target: { value: 'CONDO_USD' } });

    expect(setData).toHaveBeenCalledWith('type', 'CONDO_USD');
});
