import { CollectionNoticeDialog } from '@/pages/catalogs/concessionaire/collection-notice-dialog';
import { columns, type Row } from '@/pages/catalogs/concessionaire/columns';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import type { ComponentType } from 'react';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: { auth: { can: {} } } }),
    useForm: () => ({ data: { name: '', email: '' } }),
    Link: 'a',
    router: {},
}));

const Actions = columns.find((column) => column.id === 'actions')!.cell as ComponentType<{ row: { original: Row } }>;
const onClose = vi.fn();

beforeEach(() => {
    vi.stubGlobal(
        'route',
        vi.fn(() => '/collection-notices'),
    );
    vi.spyOn(window, 'open').mockImplementation(() => null);
    vi.stubGlobal(
        'URL',
        class extends URL {
            static createObjectURL = vi.fn(() => 'blob:collection-notice');
            static revokeObjectURL = vi.fn();
        },
    );
    respond(422, 'application/json', { code: 'collection_notice_not_applicable' });
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
    vi.clearAllMocks();
});

function respond(status: number, contentType: string, body = {}) {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue({
            ok: status === 200,
            status,
            headers: new Headers({ 'Content-Type': contentType }),
            json: async () => body,
            blob: async () => new Blob(['%PDF-1.4'], { type: contentType }),
        }),
    );
}

it.each(['ordinary', 'payment_agreement'] as const)('shows a neutral informational dialog for %s without opening a tab', async (noticeType) => {
    render(<CollectionNoticeDialog concessionaireId={50} name="Alba Mesino" noticeType={noticeType} onClose={onClose} />);

    expect(await screen.findByRole('heading', { name: 'No corresponde emitir el aviso de cobro' })).toBeInTheDocument();
    expect(screen.getByText('Alba Mesino no tiene puestos con deuda vencida cobrable. No se generará ningún documento.')).toBeInTheDocument();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Abrir PDF' })).not.toBeInTheDocument();
    expect(window.open).not.toHaveBeenCalled();
    expect(URL.createObjectURL).not.toHaveBeenCalled();
    expect(fetch).toHaveBeenCalledWith(
        `/collection-notices?notice_type=${noticeType}&ids%5B%5D=50`,
        expect.objectContaining({ headers: { Accept: 'application/json' }, cache: 'no-store' }),
    );
    fireEvent.click(screen.getByRole('button', { name: 'Entendido' }));
    expect(onClose).toHaveBeenCalledOnce();
});

it.each(['ordinary', 'payment_agreement'] as const)('opens only the already generated PDF for %s', async (noticeType) => {
    respond(200, 'application/pdf');
    const { unmount } = render(<CollectionNoticeDialog concessionaireId={50} name="Alba Mesino" noticeType={noticeType} onClose={onClose} />);
    const open = await screen.findByRole('button', { name: 'Abrir PDF' });
    expect(window.open).not.toHaveBeenCalled();
    fireEvent.click(open);
    expect(window.open).toHaveBeenCalledWith('blob:collection-notice', '_blank', 'noopener,noreferrer');
    expect(fetch).toHaveBeenCalledOnce();
    unmount();
    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:collection-notice');
});

it.each([
    [500, 'application/json'],
    [403, 'application/json'],
    [422, 'application/json'],
    [200, 'text/html'],
] as const)('does not confuse an unexpected %s response with a debt-free concessionaire', async (status, contentType) => {
    respond(status, contentType);
    render(<CollectionNoticeDialog concessionaireId={50} name="Alba Mesino" noticeType="ordinary" onClose={onClose} />);
    expect(await screen.findByRole('alert')).toHaveTextContent('No se pudo generar el aviso de cobro');
    expect(screen.queryByText('No corresponde emitir el aviso de cobro')).not.toBeInTheDocument();
    expect(window.open).not.toHaveBeenCalled();
    expect(URL.createObjectURL).not.toHaveBeenCalled();
});

it('allows retrying after a connection failure', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('Network error')));
    render(<CollectionNoticeDialog concessionaireId={50} name="Alba Mesino" noticeType="ordinary" onClose={onClose} />);
    expect(await screen.findByRole('alert')).toBeInTheDocument();
    respond(200, 'application/pdf');
    fireEvent.click(screen.getByRole('button', { name: 'Reintentar' }));
    expect(await screen.findByRole('button', { name: 'Abrir PDF' })).toBeEnabled();
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
});

it('cancels pending work when closing and does not open a document from a late response', async () => {
    let finish!: (response: unknown) => void;
    vi.stubGlobal(
        'fetch',
        vi.fn(
            () =>
                new Promise((resolve) => {
                    finish = resolve;
                }),
        ),
    );
    const { unmount } = render(<CollectionNoticeDialog concessionaireId={50} name="Alba Mesino" noticeType="ordinary" onClose={onClose} />);
    expect(screen.getByRole('status')).toHaveTextContent('Preparando el aviso');
    expect(screen.queryByRole('button', { name: 'Abrir PDF' })).not.toBeInTheDocument();
    const signal = vi.mocked(fetch).mock.calls[0][1]?.signal;
    unmount();
    expect(signal?.aborted).toBe(true);
    finish({ ok: true, status: 200, headers: new Headers({ 'Content-Type': 'application/pdf' }) });
    await waitFor(() => expect(window.open).not.toHaveBeenCalled());
    expect(URL.createObjectURL).not.toHaveBeenCalled();
});

it.each([
    ['Deuda ordinaria', 'ordinary'],
    ['Convenio de pago', 'payment_agreement'],
])('uses the informational dialog from the individual %s menu action', async (label, noticeType) => {
    render(<Actions row={{ original: { id: 50, full_name: 'Alba Mesino' } }} />);
    fireEvent.keyDown(screen.getByRole('button', { name: 'Abrir menú' }), { key: 'ArrowDown' });
    fireEvent.keyDown(await screen.findByRole('menuitem', { name: 'Aviso de cobro' }), { key: 'ArrowRight' });
    fireEvent.click(await screen.findByRole('menuitem', { name: label }));
    expect(await screen.findByRole('heading', { name: 'No corresponde emitir el aviso de cobro' })).toBeInTheDocument();
    expect(fetch).toHaveBeenCalledWith(`/collection-notices?notice_type=${noticeType}&ids%5B%5D=50`, expect.any(Object));
    expect(window.open).not.toHaveBeenCalled();
});
