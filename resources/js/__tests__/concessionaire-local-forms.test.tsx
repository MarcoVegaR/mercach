import { columns, type Row } from '@/pages/catalogs/concessionaire/columns';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ComponentType } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { can: {} } } }),
    useForm: () => ({ data: { name: '', email: '' } }),
    Link: 'a',
    router: {},
}));

const Actions = columns.find((column) => column.id === 'actions')!.cell as ComponentType<{ row: { original: Row } }>;

beforeEach(() => {
    vi.stubGlobal(
        'route',
        vi.fn(() => '/local-form.pdf'),
    );
    vi.spyOn(window, 'open').mockImplementation(() => null);
    mockEligibility([{ code: 'L-01', can_issue_solvency: true }]);
});

function mockEligibility(locals: { code: string; can_issue_solvency: boolean }[]) {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ locals }) }));
}

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

function openActions(locals: string[]) {
    render(<Actions row={{ original: { id: 144, full_name: 'María Cesionaria', active_locals: locals } }} />);
    fireEvent.keyDown(screen.getByRole('button', { name: 'Abrir menú' }), { key: 'ArrowDown' });
}

describe('planillas por local', () => {
    it.each([
        ['Constancia de solvencia', 'solvency'],
        ['Declaración de renuncia', 'waiver'],
    ])('genera %s para el único local del cesionario', async (label, formType) => {
        openActions(['L-01']);
        fireEvent.click(await screen.findByRole('menuitem', { name: label }));
        const generate = await screen.findByRole('button', { name: 'Generar PDF' });
        await waitFor(() => expect(generate).toBeEnabled());
        fireEvent.click(generate);

        expect(route).toHaveBeenCalledWith('catalogs.concessionaire.local-form', {
            concessionaire: 144,
            local_code: 'L-01',
            form_type: formType,
        });
        expect(window.open).toHaveBeenCalledWith('/local-form.pdf', '_blank', 'noopener,noreferrer');
    });

    it('exige seleccionar un local cuando hay varios', async () => {
        mockEligibility([
            { code: 'L-01', can_issue_solvency: true },
            { code: 'L-02', can_issue_solvency: true },
        ]);
        openActions(['L-01', 'L-02']);
        fireEvent.click(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' }));
        await waitFor(() => expect(screen.getByRole('checkbox', { name: 'L-01' })).toBeEnabled());
        expect(await screen.findByRole('button', { name: 'Generar PDF' })).toBeDisabled();
    });

    it('bloquea la generación mientras se consultan los saldos', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => {})),
        );
        openActions(['L-01']);
        fireEvent.click(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' }));
        expect(await screen.findByRole('button', { name: 'Generar PDF' })).toBeDisabled();
        expect(screen.getByRole('checkbox', { name: 'L-01' })).toBeDisabled();
    });

    it('no preselecciona un local con deuda y no permite generar su solvencia', async () => {
        mockEligibility([{ code: 'L-01', can_issue_solvency: false }]);
        openActions(['L-01']);
        fireEvent.click(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' }));
        expect(await screen.findByRole('status')).toHaveTextContent('Ningún local está habilitado');
        expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeDisabled();
        expect(window.open).not.toHaveBeenCalled();
    });

    it('deshabilita la opción del local con deuda y conserva los otros locales disponibles', async () => {
        mockEligibility([
            { code: 'L-01', can_issue_solvency: false },
            { code: 'L-02', can_issue_solvency: true },
        ]);
        openActions(['L-01', 'L-02']);
        fireEvent.click(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' }));
        await waitFor(() => expect(screen.getByRole('checkbox', { name: 'L-02' })).toBeEnabled());
        expect(screen.getByRole('checkbox', { name: 'L-01 — Deuda vencida cobrable' })).toBeDisabled();
        expect(screen.getByRole('checkbox', { name: 'L-02' })).toBeChecked();
        fireEvent.click(screen.getByRole('button', { name: 'Generar PDF' }));
        expect(route).toHaveBeenCalledWith('catalogs.concessionaire.local-form', {
            concessionaire: 144,
            local_code: 'L-02',
            form_type: 'solvency',
        });
    });

    it('no habilita la solvencia si falla la consulta', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')));
        openActions(['L-01']);
        fireEvent.click(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' }));
        expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo verificar la solvencia');
        expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeDisabled();
        expect(window.open).not.toHaveBeenCalled();
    });

    it('selecciona todos los solventes, permite excluir locales y desmarcar todos', async () => {
        mockEligibility([
            { code: 'L-01', can_issue_solvency: true },
            { code: 'L-02', can_issue_solvency: true },
            { code: 'L-03', can_issue_solvency: false },
        ]);
        openActions(['L-01', 'L-02', 'L-03']);
        fireEvent.click(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' }));
        const all = await screen.findByRole('button', { name: 'Seleccionar todos los solventes' });
        await waitFor(() => expect(all).toBeEnabled());
        fireEvent.click(all);
        expect(screen.getByText('2 locales seleccionados')).toBeInTheDocument();
        expect(screen.getByRole('checkbox', { name: 'L-03 — Deuda vencida cobrable' })).not.toBeChecked();
        fireEvent.click(screen.getByRole('button', { name: 'Generar PDF' }));
        expect(route).toHaveBeenCalledWith('catalogs.concessionaire.local-form', {
            concessionaire: 144,
            local_codes: ['L-01', 'L-02'],
            form_type: 'solvency',
        });
        fireEvent.click(screen.getByRole('checkbox', { name: 'L-01' }));
        expect(screen.getByText('1 local seleccionado')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Generar PDF' }));
        expect(route).toHaveBeenLastCalledWith('catalogs.concessionaire.local-form', {
            concessionaire: 144,
            local_code: 'L-02',
            form_type: 'solvency',
        });
        fireEvent.click(screen.getByRole('button', { name: 'Seleccionar todos los solventes' }));
        fireEvent.click(screen.getByRole('button', { name: 'Desmarcar todos' }));
        expect(screen.getByRole('button', { name: 'Generar PDF' })).toBeDisabled();
    });

    it('deshabilita las planillas cuando no hay locales operativos', async () => {
        openActions([]);
        expect(await screen.findByRole('menuitem', { name: 'Constancia de solvencia' })).toHaveAttribute('aria-disabled', 'true');
        expect(screen.getByRole('menuitem', { name: 'Declaración de renuncia' })).toHaveAttribute('aria-disabled', 'true');
    });
});
