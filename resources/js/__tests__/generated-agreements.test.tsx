import Agreements from '@/pages/agreements/index';
import Preview from '@/pages/agreements/preview';
import Show from '@/pages/agreements/show';
import { act, cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { useState, type ReactNode } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

const requests = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useRemember: (initial: string) => useState(initial),
    router: { reload: requests.reload, get: requests.get },
    Link: ({ href, children }: { href: string; children: ReactNode }) => <a href={href}>{children}</a>,
    useForm: (keyOrData: string | Record<string, unknown>, initial?: Record<string, unknown>) => {
        const [data, update] = useState(typeof keyOrData === 'string' ? initial! : keyOrData);
        const [errors, setErrors] = useState<Record<string, string>>({});
        let transform = (value: Record<string, unknown>) => value;
        return {
            data,
            errors,
            clearErrors: () => setErrors({}),
            setError: (key: string, value: string) => setErrors((previous) => ({ ...previous, [key]: value })),
            processing: false,
            setData: (key: string | Record<string, unknown>, value?: unknown) =>
                update((previous) => (typeof key === 'string' ? { ...previous, [key]: value } : key)),
            transform: (callback: typeof transform) => {
                transform = callback;
            },
            get: (url: string, options?: { preserveState?: boolean; preserveScroll?: boolean }) => requests.get(url, transform(data), options),
            post: (url: string, options?: unknown) => requests.post(url, transform(data), options),
        };
    },
}));

vi.mock('@/layouts/app-layout', () => ({ default: ({ children }: { children: ReactNode }) => <>{children}</> }));
vi.mock('@/components/ui/combobox', () => ({
    Combobox: ({
        id,
        options,
        value,
        onChange,
        multiple,
        disabled,
    }: {
        id: string;
        options: { value: string; label: string }[];
        value: string | string[];
        onChange: (value: string | string[]) => void;
        multiple?: boolean;
        disabled?: boolean;
    }) => (
        <select
            id={id}
            value={value}
            multiple={multiple}
            disabled={disabled}
            onChange={(event) => onChange(multiple ? Array.from(event.target.selectedOptions).map((option) => option.value) : event.target.value)}
        >
            <option value="">Seleccionar</option>
            {options.map((option) => (
                <option key={option.value} value={option.value}>
                    {option.label}
                </option>
            ))}
        </select>
    ),
}));

const props = {
    issuanceToken: 'current-token',
    today: '2026-11-17',
    concessionaires: [{ id: 1, full_name: 'María Pérez', document_number: '12345678' }],
    locals: [{ id: 2, code: 'L-01', area_m2: '12.50' }],
    trades: [{ id: 3, name: 'Frutas' }],
    filters: {},
    agreements: { data: [], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null, total: 0 },
};

const snapshot = {
    concessionaire: {
        id: 1,
        name: 'María Pérez',
        document: 'V-12.345.678',
        sex: 'F',
        marital_status_label: 'soltera',
        email: 'maria@example.test',
        phone: '0414 1234567',
        address: 'Chacao',
    },
    local: { code: 'L-01', area_m2: '12,50', location: 'Planta baja' },
    trade: { name: 'Frutas' },
    start_date: '2026-12-01',
    end_date: '2046-11-20',
    signature_date: '2026-11-20',
};
const values = {
    concessionaire_id: '1',
    start_date: '2026-12-01',
    local_id: '2',
    trade_category_id: '3',
    signature_date: '2026-11-20',
    issuance_token: 'token',
    preview_hash: 'signed-preview',
};

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/routes/${name}`);
    requests.get.mockClear();
    requests.post.mockClear();
    requests.reload.mockClear();
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

it('prepares a refreshable preview with catalog selections and dates without issuing', () => {
    render(<Agreements {...props} />);
    fireEvent.change(screen.getByLabelText(/^Cesionario principal/), { target: { value: '1' } });
    fireEvent.change(screen.getByLabelText(/^Local/), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText(/^Rubro autorizado/), { target: { value: '3' } });
    fireEvent.change(screen.getByLabelText(/^Inicio de vigencia/), { target: { value: '2026-12-01' } });
    fireEvent.change(screen.getByLabelText(/^Fecha prevista de firma/), { target: { value: '2026-11-20' } });
    fireEvent.click(screen.getByRole('button', { name: 'Revisar documento' }));
    expect(requests.get).toHaveBeenCalledWith(
        '/routes/agreements.preview',
        {
            concessionaire_id: '1',
            additional_concessionaire_ids: [],
            source_contract_id: '',
            local_id: '2',
            trade_category_id: '3',
            signature_date: '2026-11-20',
            start_date: '2026-12-01',
            issuance_token: 'current-token',
        },
        { preserveState: true },
    );
    expect(requests.post).not.toHaveBeenCalled();
});

it('copies existing contract data and signers while allowing a deliberate switch to new data', () => {
    render(
        <Agreements
            {...props}
            concessionaires={[...props.concessionaires, { id: 4, full_name: 'Juan Firmante', document_number: '23456789' }]}
            locals={[...props.locals, { id: 5, code: 'L-02', area_m2: '20.00' }]}
            contracts={[
                {
                    id: 10,
                    number: 'C-10',
                    status: 'Vigente',
                    concessionaire_ids: [1, 4],
                    local_ids: [2],
                    local_codes: ['L-01'],
                    trade_category_id: 3,
                    trade_name: 'Frutas',
                },
            ]}
        />,
    );
    expect(screen.getByLabelText(/Usar datos de un contrato existente/)).toBeDisabled();
    fireEvent.change(screen.getByLabelText(/^Cesionario principal/), { target: { value: '1' } });
    fireEvent.click(screen.getByLabelText(/Usar datos de un contrato existente/));
    expect(screen.getByRole('option', { name: /C-10 · L-01 · Frutas/ })).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Contrato de referencia'), { target: { value: '10' } });
    expect(screen.getByLabelText(/^Local/)).toHaveValue('2');
    expect(screen.queryByRole('option', { name: /L-02/ })).not.toBeInTheDocument();
    expect(screen.getByLabelText(/^Rubro autorizado/)).toHaveValue('3');
    expect(screen.getByLabelText(/^Rubro autorizado/)).toBeDisabled();
    expect(screen.getByLabelText('Firmantes adicionales (opcional)')).toHaveValue(['4']);
    expect(screen.getByText(/Se recuperaron el rubro/)).toHaveTextContent('1 cesionario adicional');
    fireEvent.click(screen.getByLabelText(/Seleccionar datos nuevos/));
    expect(screen.getByLabelText(/^Local/)).toHaveValue('');
    expect(screen.getByRole('option', { name: /L-02/ })).toBeInTheDocument();
    expect(screen.getByLabelText(/^Rubro autorizado/)).toBeEnabled();
    expect(screen.getByLabelText('Firmantes adicionales (opcional)')).toHaveValue([]);
});

it('explains an empty history and filters by text and issuance dates', () => {
    render(<Agreements {...props} />);
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Historial de emisiones' }), { button: 0, ctrlKey: false });
    expect(screen.getByText(/No hay convenios para mostrar/)).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText('Número, cesionario o local'), { target: { value: 'María' } });
    fireEvent.change(screen.getByLabelText('Emitidos desde'), { target: { value: '2026-11-01' } });
    fireEvent.click(screen.getByRole('button', { name: 'Filtrar' }));
    expect(requests.get).toHaveBeenCalledWith(
        '/routes/agreements.index',
        { q: 'María', from: '2026-11-01', to: '', tab: 'history', per_page: 10 },
        { preserveState: true, preserveScroll: true },
    );
    fireEvent.click(screen.getByRole('button', { name: 'Limpiar' }));
    expect(screen.getByLabelText('Número, cesionario o local')).toHaveValue('');
    expect(screen.getByLabelText('Emitidos desde')).toHaveValue('');
});

it('uses the project confirmation dialog and waits for issuance to finish', async () => {
    const pdfUrl = '/agreements/preview/pdf?preview_hash=signed-preview';
    render(<Preview pdfUrl={pdfUrl} values={values} snapshot={snapshot} />);
    expect(screen.getByTitle('Vista previa del convenio')).toHaveAttribute('src', pdfUrl);
    expect(screen.getByRole('link', { name: 'Abrir PDF en otra pestaña' })).toHaveAttribute('target', '_blank');
    expect(screen.getByText('María Pérez · V-12.345.678')).toBeInTheDocument();
    expect(screen.getByText('20/11/2046')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Emitir convenio' }));
    const dialog = await screen.findByRole('alertdialog');
    expect(dialog).toHaveTextContent('siguiente correlativo disponible');
    expect(dialog).toHaveTextContent('no registra un contrato ni acredita su firma');
    expect(requests.post).not.toHaveBeenCalled();
    fireEvent.click(within(dialog).getByRole('button', { name: 'Emitir convenio' }));
    expect(requests.post).toHaveBeenCalledWith(
        '/routes/agreements.store',
        values,
        expect.objectContaining({ onSuccess: expect.any(Function), onError: expect.any(Function) }),
    );
    expect(within(dialog).getByRole('button', { name: 'Emitir convenio' })).toBeDisabled();
    await act(async () => requests.post.mock.calls[0][2].onSuccess());
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
});

it('keeps the confirmation open when issuance fails', async () => {
    render(<Preview pdfUrl="/preview/pdf" values={values} snapshot={snapshot} />);
    fireEvent.click(screen.getByRole('button', { name: 'Emitir convenio' }));
    const dialog = await screen.findByRole('alertdialog');
    fireEvent.click(within(dialog).getByRole('button', { name: 'Emitir convenio' }));
    await act(async () => requests.post.mock.calls[0][2].onError());
    expect(screen.getByRole('alertdialog')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Emitir convenio' })).toBeEnabled();
});

it('does not issue when the operator chooses to keep reviewing', async () => {
    render(<Preview pdfUrl="/preview/pdf" values={values} snapshot={snapshot} />);
    fireEvent.click(screen.getByRole('button', { name: 'Emitir convenio' }));
    const dialog = await screen.findByRole('alertdialog');
    fireEvent.click(within(dialog).getByRole('button', { name: 'Seguir revisando' }));
    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument());
    expect(requests.post).not.toHaveBeenCalled();
});

it('shows actionable missing fields without granting profile editing to contract creators', () => {
    const people = [{ ...props.concessionaires[0], missing_fields: ['sexo', 'estado civil'] }];
    const view = render(<Agreements {...props} concessionaires={people} />);
    fireEvent.change(screen.getByLabelText(/^Cesionario principal/), { target: { value: '1' } });
    expect(screen.getByRole('alert')).toHaveTextContent('Perfil incompleto: María Pérez');
    expect(screen.getByRole('alert')).toHaveTextContent('estado civil');
    expect(screen.getByRole('button', { name: 'Revisar documento' })).toBeDisabled();
    expect(screen.queryByRole('link', { name: 'Completar perfil en otra pestaña' })).not.toBeInTheDocument();
    view.rerender(<Agreements {...props} concessionaires={people} auth={{ can: { 'catalogs.concessionaire.update': true } }} />);
    expect(screen.getByRole('link', { name: 'Completar perfil en otra pestaña' })).toHaveAttribute('target', '_blank');
    fireEvent.click(screen.getByRole('button', { name: 'Volver a comprobar perfiles' }));
    expect(requests.reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['concessionaires'] }));
    view.rerender(<Agreements {...props} concessionaires={[{ ...people[0], missing_fields: [] }]} />);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.getByLabelText(/^Cesionario principal/)).toHaveValue('1');
    expect(screen.getByText(/Datos de perfil completos/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Revisar documento' })).toBeEnabled();
});

it('defaults signature to today while allowing independent editable start and signature dates', () => {
    render(<Agreements {...props} />);
    expect(screen.getByLabelText(/^Fecha prevista de firma/)).toHaveValue('2026-11-17');
    expect(screen.getByLabelText(/^Inicio de vigencia/)).toHaveValue('');
    fireEvent.change(screen.getByLabelText(/^Cesionario principal/), { target: { value: '1' } });
    fireEvent.change(screen.getByLabelText(/^Fecha prevista de firma/), { target: { value: '2028-01-01' } });
    fireEvent.change(screen.getByLabelText(/^Inicio de vigencia/), { target: { value: '2028-02-01' } });
    expect(screen.queryByLabelText(/^Fin de vigencia/)).not.toBeInTheDocument();
    expect(screen.getByText(/El vencimiento se calcula veinte años después/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Revisar documento' })).toBeEnabled();
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Historial de emisiones' }), { button: 0, ctrlKey: false });
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Nuevo convenio' }), { button: 0, ctrlKey: false });
    expect(screen.getByLabelText(/^Cesionario principal/)).toHaveValue('1');
    expect(screen.getByLabelText(/^Fecha prevista de firma/)).toHaveValue('2028-01-01');
    expect(screen.getByLabelText(/^Inicio de vigencia/)).toHaveValue('2028-02-01');
});

it('presents the original document first and keeps historical details in a separate tab', () => {
    render(<Show agreement={{ id: 1, number: '0001/2026', created_at: '12/11/2026 10:00', issuer: 'Operador', template_version: 'v1', snapshot }} />);
    expect(screen.getByTitle('PDF del convenio 0001/2026')).toBeVisible();
    expect(screen.queryByText('Chacao')).not.toBeInTheDocument();
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Datos de emisión' }), { button: 0, ctrlKey: false });
    expect(screen.getByText('Chacao')).toBeInTheDocument();
    expect(screen.getByText('Versión de plantilla')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Editar' })).not.toBeInTheDocument();
});

it('does not recalculate dates of historical documents without a stored expiry', () => {
    render(
        <Show
            agreement={{
                id: 1,
                number: '0001/2026',
                created_at: '12/11/2026 10:00',
                issuer: 'Operador',
                template_version: 'v2',
                snapshot: { ...snapshot, start_date: '2020-01-01', end_date: undefined },
            }}
        />,
    );
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Datos de emisión' }), { button: 0, ctrlKey: false });
    expect(screen.getByText('01/01/2020')).toBeInTheDocument();
    expect(screen.queryByText('Fin de vigencia')).not.toBeInTheDocument();
});
