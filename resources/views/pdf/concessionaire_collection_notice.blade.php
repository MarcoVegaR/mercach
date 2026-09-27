<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Avisos de cobro</title>
    <style>
        @page { margin: 22px 46px 28px; }
        body { color: #111827; font-family: DejaVu Serif, serif; font-size: 10.5px; line-height: 1.38; }
        .letterhead { position: fixed; inset: -20px -38px -20px -38px; z-index: -1; opacity: .16; }
        .letterhead img { width: calc(100% + 76px); height: calc(100% + 40px); object-fit: fill; }
        .notice { min-height: 1040px; page-break-after: always; position: relative; }
        .notice:last-child { page-break-after: auto; }
        .top { min-height: 68px; position: relative; }
        .logo { display: block; height: 48px; margin-left: auto; width: auto; }
        .title { font-size: 18px; font-weight: 700; letter-spacing: .3px; text-align: center; }
        .date { margin-top: 2px; text-align: right; }
        .recipient { margin-top: 8px; }
        .recipient strong { display: block; }
        .body-copy { margin-top: 12px; text-align: justify; }
        .legal-heading { font-weight: 700; margin-top: 12px; text-align: center; text-decoration: underline; }
        .legal-quote { font-size: 9.5px; font-style: italic; font-weight: 700; margin: 10px 28px 0; text-align: justify; }
        .signatures { display: table; margin-top: 22px; page-break-inside: avoid; table-layout: fixed; width: 100%; }
        .signature { display: table-cell; padding-right: 18px; vertical-align: top; width: 50%; }
        .signature-title { font-weight: 700; margin-bottom: 6px; text-decoration: underline; }
        .signature-row { margin-top: 5px; }
        .line { border-bottom: 1px solid #374151; display: inline-block; height: 12px; vertical-align: bottom; width: 68%; }
        .signature-line { border-bottom: 1px solid #374151; display: inline-block; height: 23px; vertical-align: bottom; width: 68%; }
        .filled { font-weight: 700; overflow-wrap: anywhere; }
        .footer { bottom: 0; color: #6b7280; font-family: DejaVu Sans, sans-serif; font-size: 7px; left: 0; position: absolute; right: 0; text-align: center; }
    </style>
    @if (!empty($letterhead_base64))
        <style>
            @page {
                background-image: url('data:{{ $letterhead_mime ?? 'image/png' }};base64,{{ $letterhead_base64 }}');
                background-position: center center;
                background-repeat: no-repeat;
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

@foreach ($notices as $notice)
    @php
        $concessionaire = $notice['concessionaire'];
        $document = trim(((string) optional($concessionaire->documentType)->code).'-'.((string) $concessionaire->document_number), '-');
        $phone = trim(((string) optional($concessionaire->phoneAreaCode)->code).' '.((string) $concessionaire->phone_number));
        $localCodes = $notice['local_codes'];
        $isPaymentAgreement = $notice_type === 'payment_agreement';
    @endphp
    <section class="notice">
        <div class="top">
            @if (!empty($logo_base64))
                <img class="logo" src="data:{{ $logo_mime ?? 'image/png' }};base64,{{ $logo_base64 }}" alt="Logo">
            @endif
        </div>

        <div class="title">AVISO DE COBRO</div>
        <div class="date">Chacao, {{ $printed_at->copy()->locale('es')->translatedFormat('d \d\e F \d\e Y') }}</div>

        <div class="recipient">
            Señor(a)
            <strong>{{ $concessionaire->full_name }}</strong>
            <strong>C.I.: {{ $document !== '' ? $document : '—' }}</strong>
            <strong>{{ count($localCodes) === 1 ? 'Puesto' : 'Puestos' }}: {{ implode(', ', $localCodes) }}</strong>
            Presente.-
        </div>

        <div class="body-copy">
            Con un cordial saludo, me dirijo a usted en la oportunidad de notificarle la <strong>deuda pendiente</strong>
            correspondiente a los derechos de explotación para el expendio de mercancías en el Mercado Municipal de
            Chacao, de los cuales es titular conforme al contrato suscrito entre usted y el Municipio Chacao. Se observa
            que, a la fecha, se encuentra en situación de <strong>MOROSIDAD</strong>; por tal motivo, se le exhorta a
            regularizar su situación mediante el pago inmediato del monto adeudado.
        </div>

        <div class="body-copy">
            Es importante recordarle que, de conformidad con el contenido del numeral 1 del artículo 22 de la Ordenanza
            Nro. 010-2023 sobre la Organización y Funcionamiento del Mercado Municipal de Chacao, de fecha 08/11/2023,
            la sanción establecida por el atraso de tres mensualidades en adelante conlleva la rescisión del convenio y,
            por consiguiente, la pérdida del derecho de uso o explotación del puesto o puestos asignados, así como su
            desocupación inmediata.
        </div>

        <div class="legal-heading">Extracto de la Reforma de la Ordenanza sobre Funcionamiento del Mercado Municipal de Chacao:</div>
        <div class="legal-quote">
            “Artículo 22: A los efectos de la presente Ordenanza, son causales de Rescisión del Convenio, las siguientes conductas:<br>
            &nbsp;&nbsp;&nbsp;&nbsp;1.- La falta de pago de tres (3) o más mensualidades en el término de un (1) año”.
        </div>

        @if ($isPaymentAgreement)
            <div class="body-copy">
                Asimismo, le indicamos que, a los efectos de evitar la aplicación de una sanción de esta magnitud, se le
                permitió suscribir con el Instituto, en su oportunidad, un <strong>convenio de pago</strong> sobre la deuda
                que mantenía, compromiso formal que igualmente incumplió.
            </div>
        @endif

        <div class="body-copy">
            En consecuencia, a fin de evitar la aplicación de la sanción descrita, se le exhorta a regularizar su situación
            mediante el pago inmediato de los montos atrasados, para lo cual esta Administración le concede un plazo de
            <strong>cinco (05) días hábiles</strong> para la cancelación total de su deuda@if ($isPaymentAgreement) o, en su
            defecto, para retomar lo pactado en su convenio de pago, poniéndose al día con los lapsos de pago establecidos
            en este@endif. De lo contrario, se procederá a la apertura del procedimiento administrativo antes referido. En
            caso de cualquier duda o inquietud, quedamos a su disposición en la oficina de Administración, ubicada en el
            Nivel Comercio.
        </div>

        <div class="signatures">
            <div class="signature">
                <div class="signature-title">Cesionario(a):</div>
                <div class="signature-row">Nombre: <span class="filled">{{ $concessionaire->full_name }}</span></div>
                <div class="signature-row">Documento: <span class="filled">{{ $document !== '' ? $document : '—' }}</span></div>
                <div class="signature-row">Celular: <span class="filled">{{ $phone !== '' ? $phone : '—' }}</span></div>
                <div class="signature-row">Firma: <span class="signature-line"></span></div>
                <div class="signature-row">Sello: <span class="line"></span></div>
            </div>
            <div class="signature">
                <div class="signature-title">Por la Dirección de Administración:</div>
                <div class="signature-row">Nombre: <span class="line"></span></div>
                <div class="signature-row">Cargo: <span class="line"></span></div>
                <div class="signature-row">Firma: <span class="signature-line"></span></div>
            </div>
        </div>

        <div class="footer">Generado por el sistema el {{ $printed_at->format('d/m/Y H:i') }}.</div>
    </section>
@endforeach
</body>
</html>
