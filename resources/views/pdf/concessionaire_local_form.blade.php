<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $form_type === 'solvency' ? 'Constancia de solvencia administrativa' : 'Declaración de renuncia a derechos de uso' }}</title>
    <style>
        @page { margin: 20px 28px 24px; }
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.8; }
        .letterhead { position: fixed; left: -25px; top: -18px; right: -20px; bottom: -12px; z-index: -1; opacity: .16; }
        .letterhead img { width: calc(100% + 35px); height: calc(100% + 30px); object-fit: fill; }
        .header { position: relative; min-height: 75px; border-bottom: 2px solid #0f766e; margin-bottom: 28px; padding-bottom: 8px; }
        .header-copy { width: 74%; padding-top: 12px; }
        .eyebrow { color: #0f766e; font-size: 9px; font-weight: 800; text-transform: uppercase; }
        .institution { color: #475569; font-size: 9px; }
        .logo { position: absolute; top: 0; right: 0; height: 60px; width: auto; }
        h1 { font-size: 16px; line-height: 1.6; text-align: center; margin: 0 0 28px; }
        p { text-align: justify; margin: 0 0 18px; overflow-wrap: anywhere; }
        .signature-grid { display: table; width: 100%; table-layout: fixed; margin-top: 42px; page-break-inside: avoid; }
        .signature-cell { display: table-cell; width: 50%; text-align: center; vertical-align: top; font-size: 10px; }
        .signature-line { border-top: 1px solid #475569; margin: 28px 18px 6px; }
        .signature-center { text-align: center; margin-top: 36px; font-size: 10px; page-break-inside: avoid; }
        .signature-center .signature-line { margin-left: 30%; margin-right: 30%; }
        .field { margin-top: 14px; }
        .line { display: inline-block; width: 58%; height: 20px; border-bottom: 1px solid #475569; vertical-align: bottom; }
        .note { font-size: 9px; margin-top: 14px; }
        .observations { margin-top: 22px; font-size: 10px; page-break-inside: avoid; }
        .observation-line { height: 26px; border-bottom: 1px solid #64748b; }
        .footer { margin-top: 24px; color: #64748b; font-size: 8px; text-align: center; }
        .waiver { font-size: 11px; line-height: 1.7; }
        .waiver h1 { font-size: 13px; margin-bottom: 24px; text-decoration: underline; }
        .waiver .signature-grid { margin-top: 24px; }
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
<body class="{{ $form_type === 'waiver' ? 'waiver' : 'solvency' }}">
@if (!empty($letterhead_base64))
    <div class="letterhead"><img src="data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}" alt=""></div>
@endif
@php
    $document = trim(((string) optional($concessionaire->documentType)->code).'-'.$concessionaire->document_number, '-');
    $phone = trim(((string) optional($concessionaire->phoneAreaCode)->code).' '.$concessionaire->phone_number);
@endphp
<div class="header">
    <div class="header-copy">
        <div class="eyebrow">Dirección de Administración</div>
        <div class="institution">Instituto Autónomo Mercado Municipal de Chacao</div>
    </div>
    @if (!empty($logo_base64))
        <img class="logo" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo">
    @endif
</div>

@if ($form_type === 'solvency')
    <h1>CONSTANCIA DE SOLVENCIA ADMINISTRATIVA</h1>
    <p>
        Quienes suscriben, <strong>YREANA LUCKERT</strong>, portadora de la cédula de identidad
        N.º <strong>V-13.864.150</strong>, en calidad de <strong>DIRECTORA DE ADMINISTRACIÓN</strong>,
        y <strong>ARELIS CASTRO</strong>, portadora de la cédula de identidad
        N.º <strong>V-11.199.235</strong>, en calidad de <strong>GERENTE DE FINANZAS Y CONTABILIDAD</strong>,
        del Instituto Autónomo Mercado Municipal de Chacao, hacen constar que
        <strong>{{ $concessionaire->full_name }}</strong>, titular del documento de identidad
        <strong>{{ $document }}</strong>, en su carácter de cesionario(a) del local
        <strong>“{{ $local->code }}”</strong>, destinado para uso comercial,
        se encuentra <strong>SOLVENTE</strong> respecto de las obligaciones vencidas cobrables
        correspondientes a dicho local hasta la presente fecha.
    </p>
    <p>
        Constancia que se expide a solicitud de la parte interesada, en Caracas,
        a los <strong>{{ $printed_at->format('d') }}</strong> días del mes de
        <strong>{{ $printed_at->copy()->locale('es')->translatedFormat('F') }}</strong>
        del año <strong>{{ $printed_at->format('Y') }}</strong>.
    </p>
    <div style="text-align: center; margin-top: 32px;">Atentamente,</div>
    <div class="signature-grid">
        <div class="signature-cell">
            <div class="signature-line"></div>
            <strong>YREANA LUCKERT</strong><br>
            Directora de Administración
        </div>
        <div class="signature-cell">
            <div class="signature-line"></div>
            <strong>ARELIS CASTRO</strong><br>
            Gerente de Finanzas y Contabilidad
        </div>
    </div>
@else
    <h1>DECLARACIÓN DE RENUNCIA A DERECHOS DE USO SOBRE: LOCAL, BATEAS, DEPÓSITOS, OFICINAS O LOCALES DE USO COMERCIAL BAJO LA FIGURA DE ARRENDAMIENTO, DENTRO DEL MERCADO MUNICIPAL DE CHACAO.</h1>
    <p>
        Yo: <strong>{{ $concessionaire->full_name }}</strong>, en mi carácter de cesionario(a)
        del local o batea: <strong>{{ $local->code }}</strong>.
    </p>
    <p>
        Mediante el presente formato declaro: que a la fecha de la firma del presente documento,
        <strong>____ / ____ / ________</strong>, renuncio a mis derechos sobre el siguiente bien:
        <strong>{{ $local->code }}{{ $local->name && $local->name !== $local->code ? ' - '.$local->name : '' }}</strong>,
        ubicado dentro del Mercado Municipal de Chacao, es todo.-
    </p>
    <div class="field"><strong>Firma:</strong> <span class="line"></span></div>
    <div class="field"><strong>Cédula:</strong> {{ $document }}</div>
    <div class="field"><strong>CELULAR:</strong> {{ $phone !== '' ? $phone : '________________________' }}</div>
    <div class="note"><strong>Nota 1:</strong> La presente declaración será archivada en el expediente administrativo del Cesionario.</div>
    <div class="observations">
        <strong><u>Observación administrativa (Solo para ser llenado por la Administración):</u></strong>
        @for ($i = 0; $i < 5; $i++)
            <div class="observation-line"></div>
        @endfor
    </div>
    <div class="note"><strong><u>Firma por la Gerencia del Instituto:</u></strong></div>
    <div class="signature-grid">
        <div class="signature-cell"><div class="signature-line"></div>Directora de Administración</div>
        <div class="signature-cell"><div class="signature-line"></div>Consultor Jurídico</div>
    </div>
    <div class="signature-center"><div class="signature-line"></div>Dirección de Relaciones Comerciales</div>
    <div class="note"><strong>Anexo:</strong> Reporte de Fiscal / Convenio de pago según corresponda.-</div>
@endif
<div class="footer">Generada por el sistema el {{ $printed_at->format('d/m/Y H:i') }}.</div>
</body>
</html>
