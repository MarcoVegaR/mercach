import RevenueByConcept from '@/pages/reports/revenue-by-concept';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { useState, type ReactNode } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

interface Filters {
    from: string;
    to: string;
    concepts: string[];
}

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: (initial: Filters) => {
        const [data, update] = useState(initial);
        return {
            data,
            setData: (key: keyof Filters | Filters, value?: string | string[]) => {
                update((previous) => (typeof key === 'string' ? { ...previous, [key]: value } : key));
            },
        };
    },
}));

vi.mock('@/layouts/app-layout', () => ({
    default: ({ children }: { children: ReactNode }) => <>{children}</>,
}));

const props = {
    filters: { from: '2026-10-01', to: '2026-10-29', concepts: [] },
    conceptOptions: [
        { value: 'RENT_EUR_M2', label: 'Tasa de uso por convenio' },
        { value: 'CONDO_USD', label: 'Gastos Comunes' },
        { value: 'RENT_EUR_FIXED', label: 'Alquiler fijo' },
        { value: 'ADJ', label: 'Gasto Fijo de Mantenimiento' },
        { value: 'CESION_DERECHOS', label: 'Cesión de derechos' },
        { value: 'FINE', label: 'Multa' },
    ],
    datePresets: [
        { value: 'today', label: 'Hoy', from: '2026-10-29', to: '2026-10-29' },
        { value: 'yesterday', label: 'Ayer', from: '2026-10-28', to: '2026-10-28' },
        { value: 'week', label: 'Esta semana', from: '2026-10-26', to: '2026-10-29' },
        { value: 'month', label: 'Este mes', from: '2026-10-01', to: '2026-10-29' },
    ],
};

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/routes/${name}`);
    vi.spyOn(window, 'open').mockImplementation(() => null);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

it('shows only configuration with this month and no concepts selected', () => {
    render(<RevenueByConcept {...props} />);
    expect(screen.getByRole('button', { name: 'Este mes' })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByLabelText('Desde')).toBeDisabled();
    expect(screen.getByLabelText('Hasta')).toBeDisabled();
    screen.getAllByRole('checkbox').forEach((checkbox) => expect(checkbox).not.toBeChecked());
    expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeDisabled();
    expect(screen.queryByText('Resumen por concepto')).not.toBeInTheDocument();
    expect(screen.queryByText('Detalle por pago y concepto')).not.toBeInTheDocument();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    expect(screen.queryByLabelText('Filas por página')).not.toBeInTheDocument();
});

it('enables dates only for a custom period and applies presets without manual editing', () => {
    render(<RevenueByConcept {...props} />);
    fireEvent.click(screen.getByRole('button', { name: 'Personalizado' }));
    expect(screen.getByLabelText('Desde')).toBeEnabled();
    expect(screen.getByLabelText('Hasta')).toBeEnabled();
    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-09-01' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ayer' }));
    expect(screen.getByLabelText('Desde')).toHaveValue('2026-10-28');
    expect(screen.getByLabelText('Hasta')).toHaveValue('2026-10-28');
    expect(screen.getByLabelText('Desde')).toBeDisabled();
    expect(screen.getByLabelText('Hasta')).toBeDisabled();
});

it('generates the PDF with the chosen dates and only the chosen concepts', () => {
    render(<RevenueByConcept {...props} />);
    fireEvent.click(screen.getByLabelText('Gastos Comunes'));
    fireEvent.click(screen.getByRole('button', { name: 'Esta semana' }));
    fireEvent.click(screen.getByRole('button', { name: 'Generar PDF' }));
    expect(window.open).toHaveBeenCalledWith(
        '/routes/reports.revenue-by-concept.export?from=2026-10-26&to=2026-10-29&concepts%5B%5D=CONDO_USD',
        '_blank',
        'noopener,noreferrer',
    );
});

it('requires a concept and restores the month with no concepts selected without navigating', () => {
    render(<RevenueByConcept {...props} />);
    expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeDisabled();
    expect(screen.getByRole('alert')).toHaveTextContent('Seleccione al menos un concepto');
    fireEvent.click(screen.getByLabelText('Gastos Comunes'));
    expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeEnabled();
    fireEvent.click(screen.getByRole('button', { name: 'Personalizado' }));
    fireEvent.change(screen.getByLabelText('Desde'), { target: { value: '2026-09-01' } });
    fireEvent.click(screen.getByRole('button', { name: 'Restablecer' }));
    expect(screen.getByLabelText('Desde')).toHaveValue('2026-10-01');
    expect(screen.getByLabelText('Desde')).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeDisabled();
    screen.getAllByRole('checkbox').forEach((checkbox) => expect(checkbox).not.toBeChecked());
    expect(window.open).not.toHaveBeenCalled();
});

it('selects all concepts using the single shortcut', () => {
    render(<RevenueByConcept {...props} />);
    fireEvent.click(screen.getByRole('button', { name: 'Todos los conceptos' }));
    screen.getAllByRole('checkbox').forEach((checkbox) => expect(checkbox).toBeChecked());
});
