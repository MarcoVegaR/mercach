<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planillas de Fe de Vida</title>
    <style>
        @page { margin: 20px 28px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 10.5px; line-height: 1.65; }
        .letterhead { position: fixed; left: -25px; top: -18px; right: -20px; bottom: -12px; z-index: -1; opacity: .16; }
        .letterhead img { width: calc(100% + 35px); height: calc(100% + 30px); object-fit: fill; }
        .form { position: relative; page-break-after: always; }
        .form:last-child { page-break-after: auto; }
        .header { position: relative; min-height: 82px; border-bottom: 2px solid #0f766e; margin-bottom: 18px; padding-bottom: 8px; }
        .header-copy { width: 70%; }
        .eyebrow { color: #0f766e; font-size: 8px; font-weight: 800; letter-spacing: .8px; text-transform: uppercase; }
        .title { font-size: 18px; font-weight: 800; margin-top: 4px; }
        .subtitle { color: #475569; font-size: 8px; margin-top: 2px; }
        .header-right { position: absolute; top: 0; right: 0; width: 28%; text-align: right; }
        .logo { height: 52px; width: auto; display: block; margin-left: auto; margin-bottom: 4px; }
        .number { display: inline-block; border: 1px solid #99f6e4; border-radius: 12px; background: #f0fdfa; color: #0f766e; padding: 2px 8px; font-size: 8px; font-weight: 800; }
        .identity { display: table; width: 100%; table-layout: fixed; margin-bottom: 12px; }
        .identity-data { display: table-cell; vertical-align: top; }
        .photo-cell { display: table-cell; width: 86px; padding-left: 12px; vertical-align: top; }
        .photo { width: 74px; height: 88px; border: 1px solid #cbd5e1; border-radius: 5px; object-fit: cover; }
        .photo-placeholder { width: 74px; height: 88px; border: 1px dashed #94a3b8; border-radius: 5px; color: #64748b; font-size: 8px; text-align: center; line-height: 88px; }
        .data-table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,.92); }
        .data-table th, .data-table td { border: 1px solid #e2e8f0; padding: 5px 7px; text-align: left; vertical-align: top; }
        .data-table td { overflow-wrap: anywhere; word-break: break-word; }
        .data-table th { width: 29%; background: #f8fafc; color: #475569; font-size: 8px; text-transform: uppercase; }
        .declaration { margin-top: 12px; text-align: justify; }
        .locals { font-weight: 700; }
        .signatures { margin-top: 18px; page-break-inside: avoid; }
        .section-label { color: #0f766e; font-weight: 800; text-decoration: underline; margin-bottom: 8px; }
        .signature-grid { display: table; width: 100%; table-layout: fixed; }
        .signature-cell { display: table-cell; width: 50%; vertical-align: top; padding-right: 18px; }
        .line-row { margin-top: 9px; }
        .line { display: inline-block; width: 72%; border-bottom: 1px solid #475569; height: 12px; vertical-align: bottom; }
        .filled-value { font-weight: 700; overflow-wrap: anywhere; word-break: break-word; }
        .signature-line { display: inline-block; width: 70%; border-bottom: 1px solid #475569; height: 30px; vertical-align: bottom; }
        .footer-note { margin-top: 14px; color: #64748b; font-size: 7px; text-align: center; }
    </style>
    @if (!empty($letterhead_base64))
        <style>
            @page {
                background-image: url('data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}');
                background-repeat: no-repeat;
                background-position: center center;
                background-size: 100% 100%;
            }
        </style>
    @endif
</head>
<body>
@if (!empty($letterhead_base64))
    <div class="letterhead">
        <img src="data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}" alt="">
    </div>
@endif

@foreach ($forms as $form)
    @php
        $concessionaire = $form['concessionaire'];
        $document = trim(((string) optional($concessionaire->documentType)->code).'-'.((string) $concessionaire->document_number), '-');
        $phone = trim(((string) optional($concessionaire->phoneAreaCode)->code).' '.((string) $concessionaire->phone_number));
        $operationalContracts = $concessionaire->contracts->filter(fn ($contract) => in_array(strtoupper((string) optional($contract->status)->code), ['VIG', 'VENC'], true));
        $locals = $operationalContracts->flatMap->locals->unique(fn ($local) => (string) $local->code)->sortBy('code')->values();
        $localLabels = $locals->map(fn ($local) => (string) $local->code !== (string) $local->name && (string) $local->name !== '' ? $local->code.' - '.$local->name : $local->code)->all();
    @endphp
    <section class="form">
        <div class="header">
            <div class="header-copy">
                <div class="eyebrow">Dirección de Administración</div>
                <div class="title">FE DE VIDA</div>
                <div class="subtitle">Planilla interna de comparecencia del cesionario</div>
            </div>
            <div class="header-right">
                @if (!empty($logo_base64))
                    <img class="logo" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo">
                @endif
                <div class="number">Planilla Nro. {{ $form['number'] }}</div>
            </div>
        </div>

        <div class="identity">
            <div class="identity-data">
                <table class="data-table">
                    <tr><th>Cesionario(a)</th><td>{{ $concessionaire->full_name }}</td></tr>
                    <tr><th>Documento</th><td>{{ $document !== '' ? $document : '—' }}</td></tr>
                    <tr><th>Tipo</th><td>{{ optional($concessionaire->concessionaireType)->name ?? '—' }}</td></tr>
                    <tr><th>Celular</th><td>{{ $phone !== '' ? $phone : '—' }}</td></tr>
                    <tr><th>Correo electrónico</th><td>{{ $concessionaire->email }}</td></tr>
                </table>
            </div>
            <div class="photo-cell">
                @if (!empty($form['photo']['base64']))
                    <img class="photo" src="data:{{ $form['photo']['mime'] }};base64,{{ $form['photo']['base64'] }}" alt="Foto">
                @else
                    <div class="photo-placeholder">SIN FOTO</div>
                @endif
            </div>
        </div>

        <div class="declaration">
            A los <strong>{{ $printed_at->format('d') }}</strong> días del mes de
            <strong>{{ $printed_at->copy()->locale('es')->translatedFormat('F') }}</strong>, del año
            <strong>{{ $printed_at->format('Y') }}</strong>, se hace constar que el/la cesionario(a)
            <strong>{{ $concessionaire->full_name }}</strong>, titular del documento de identificación
            <strong>{{ $document !== '' ? $document : '—' }}</strong>, fecha de nacimiento
            ____________________, de la edad de __________, en su carácter de Cesionario(a) de los locales:
            <span class="locals">{{ !empty($localLabels) ? implode(', ', $localLabels) : 'sin locales operativos asociados' }}</span>,
            compareció ante la Dirección de Administración del IAMMCH a los fines de firmar la presente
            “Fe de Vida”, para los fines legales que se puedan derivar de la misma.
        </div>

        <div class="declaration">
            Asimismo, el/la Cesionario(a) firmante expresa que el correo electrónico
            <strong>{{ $concessionaire->email }}</strong>, indicado en esta Fe de Vida, es el legalmente
            reconocido por el/la mismo(a) a los efectos de cualquier notificación que pueda realizarle el IAMMCH.
            En caso de no encontrarlo(a) en el local o los locales cedidos, se considerará legalmente realizada
            cualquier notificación enviada a dicho correo, siempre que existan pruebas de su envío a la dirección
            indicada y que provenga de un correo oficial del IAMMCH o de un funcionario que forme parte de su nómina activa.
            Se leyó y, conformes, firman:
        </div>

        <div class="signatures">
            <div class="signature-grid">
                <div class="signature-cell">
                    <div class="section-label">Cesionario(a):</div>
                    <div class="line-row">Nombre: <span class="filled-value">{{ $concessionaire->full_name }}</span></div>
                    <div class="line-row">Documento: <span class="filled-value">{{ $document }}</span></div>
                    <div class="line-row">Celular: <span class="filled-value">{{ $phone !== '' ? $phone : '—' }}</span></div>
                    <div class="line-row">Correo: <span class="filled-value">{{ $concessionaire->email }}</span></div>
                    <div class="line-row">Firma: <span class="signature-line"></span></div>
                </div>
                <div class="signature-cell">
                    <div class="section-label">Por la Dirección de Administración:</div>
                    <div class="line-row">Nombre: <span class="line"></span></div>
                    <div class="line-row">Cargo: <span class="line"></span></div>
                    <div class="line-row">Firma: <span class="signature-line"></span></div>
                    <div class="line-row">Sello: <span class="signature-line"></span></div>
                </div>
            </div>
        </div>

        <div class="footer-note">
            Generada por el sistema el {{ $printed_at->format('d/m/Y H:i') }}. La emisión de esta planilla no registra por sí sola la fe de vida.
        </div>
    </section>
@endforeach
</body>
</html>
