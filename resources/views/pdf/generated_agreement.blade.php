<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Convenio {{ $number }}</title>
    <style>
        @page { size: 216mm 330mm; margin: 30mm 22mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.65; color: #111; }
        .letterhead { position: fixed; top: -30mm; left: -22mm; width: 216mm; height: 330mm; z-index: -1; }
        .letterhead img { width: 100%; height: 100%; }
        .legal { font-size: 9pt; text-align: center; width: 48mm; padding-top: 8mm; line-height: 1.3; }
        .header { position: relative; min-height: 44mm; }
        .logo { position: absolute; top: -4mm; right: 0; height: 42mm; width: auto; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 9mm; }
        p { text-align: justify; margin: 0; }
        .clause-end { white-space: nowrap; }
        table { border-collapse: collapse; width: 100%; font-size: 8pt; margin: 4mm 0; }
        th, td { border: 1px solid #666; padding: 3px; text-align: right; }
        th:first-child, td:first-child { text-align: left; }
        th { background: #eee; }
        tr { page-break-inside: avoid; }
        .signatures { width: 100%; margin-top: 4mm; border: 0; page-break-inside: auto; }
        .signatures td { width: 50%; border: 0; text-align: center; vertical-align: top; font-size: 10pt; padding-top: 18mm; }
        .number { text-align: center; font-size: 9pt; margin-bottom: 5mm; }
    </style>
</head>
<body>
@if ($letterhead['base64'])
    <div class="letterhead"><img src="data:{{ $letterhead['mime'] }};base64,{{ $letterhead['base64'] }}" alt=""></div>
@endif
@php
    $person = $snapshot['concessionaire'];
    $local = $snapshot['local'];
    $trade = $snapshot['trade']['name'];
    $signers = [$person, ...($snapshot['additional_concessionaires'] ?? [])];
    $plural = count($signers) > 1;
    $female = collect($signers)->every(fn ($signer) => $signer['sex'] === 'F');
    $parties = $plural ? ($female ? 'LAS CESIONARIAS' : 'LOS CESIONARIOS') : $person['party'];
    $party = $plural ? ($female ? 'CADA CESIONARIA' : 'CADA CESIONARIO') : $person['party'];
    $president = [
        'name' => 'ALBERTO JOSÉ GONZÁLEZ',
        'document' => 'V-9.481.644',
        'rif' => 'V-9481644-7',
        'title' => 'Presidente del Instituto Autónomo de Mercados del Municipio Chacao',
    ];
@endphp
<div class="header">
    <div class="legal"><strong>Abg. Jesús A. Salmerón<br>Consultor Jurídico<br>IAMMCH</strong></div>
    @if ($logo['base64'])
        <img class="logo" src="data:{{ $logo['mime'] }};base64,{{ $logo['base64'] }}" alt="Logo del mercado">
    @endif
</div>
<h1>CONVENIO {{ $snapshot['issuer_year'] }}</h1>
<div class="number">N.º {{ $number }}</div>

<p><strong>EL INSTITUTO AUTÓNOMO DE MERCADOS DEL MUNICIPIO CHACAO</strong>, Registro de Información Fiscal: G-20009256-4, representado en este acto por el ciudadano <strong>{{ $president['name'] }}</strong>, venezolano, mayor de edad, de este domicilio, titular de la cédula de identidad N.º {{ $president['document'] }}, Registro de Información Fiscal: {{ $president['rif'] }}, en su carácter de <strong>{{ $president['title'] }}</strong>, de conformidad con la designación otorgada por el ciudadano Alcalde, según Resolución N.º 060-2024, de fecha 02 de mayo de 2024, publicada en Gaceta Municipal Extraordinaria N.º 9614 de la misma fecha, quien en lo sucesivo y a los efectos del presente convenio se denominará <strong>EL INSTITUTO</strong>, y por la otra parte
@foreach ($signers as $signer)
    {{ $loop->first ? '' : '; y ' }}{{ $signer['citizen'] }} <strong>{{ $signer['name'] }}</strong>, {{ $signer['nationality'] }}, mayor de edad, civilmente hábil, de estado civil {{ $signer['marital_status_label'] }}, de este domicilio y titular de la cédula de identidad N.º <strong>{{ $signer['document'] }}</strong>
@endforeach
{{ $plural ? ', quienes en lo sucesivo y a los efectos del presente convenio se denominarán' : ', quien en lo sucesivo y a los efectos del presente convenio se denominará' }} <strong>{{ $parties }}</strong>@if ($plural)<span>, con las referencias individuales a <strong>cada cesionario</strong> según corresponda</span>@endif, por medio del presente documento declaramos:</p>

<p><strong>PRIMERA:</strong> De conformidad con el ordinal 1 del artículo 15 de la Ordenanza N.º 005-09 sobre la Creación y Funcionamiento del Mercado Municipal de Chacao, de fecha 28 de abril de 2009, en concordancia con el ordinal 17 del artículo 13 de la misma Ordenanza, <strong>EL INSTITUTO</strong> cede a <strong>{{ $party }}</strong>, los derechos de uso sobre un espacio del Mercado Municipal de Chacao, identificado como <strong>Local {{ $local['code'] }}</strong>, así como las obligaciones correspondientes a la explotación dentro del referido local del rubro <strong>“{{ $trade }}”</strong>. El <strong>Local {{ $local['code'] }}</strong> tiene una superficie total aproximada de <strong>{{ $local['area_m2'] }} m²</strong>, y se encuentra ubicado en el Nivel <strong>{{ $local['location'] }}</strong> del Mercado Municipal de Chacao, distinguido con el número de Catastro 15-07-01-U01-013-004-002-000-000-000, situado en el Edif. Sede del <strong>Instituto Autónomo de Mercados del Municipio Chacao</strong>, Avenida Mohedano, entre las calles Ávila y Urdaneta, Urbanización Población Chacao del Municipio Chacao del Estado Bolivariano de Miranda. <strong>EL INSTITUTO</strong> realiza la cesión de derechos de uso, libre de toda clase de cargas, gravámenes, pagos por concepto de gastos comunes de operación, mantenimiento, pago de servicios públicos y de seguridad. En el presente convenio se tendrán por enteramente reproducidos el texto de los convenios anteriormente suscritos entre <strong>{{ $party }}</strong> y <strong>EL INSTITUTO</strong> y sus cláusulas se considerarán vigentes siempre que no contraríen el presente convenio o las Ordenanzas y Reglamentos vigentes<span class="clause-end">.--------</span></p>

<p><strong>SEGUNDA:</strong> <strong>{{ $party }}</strong> se obliga a dotar el puesto o local asignado, con sus propios medios, y a sus solas y únicas expensas, con los productos y bienes correspondientes al rubro de <strong>“{{ $trade }}”</strong>, quedando entendido entre las partes que <strong>{{ $party }}</strong> no gozará de exclusividad en la comercialización de dicho rubro en el Mercado Municipal de Chacao<span class="clause-end">.--------</span></p>

<p><strong>TERCERA:</strong> <strong>{{ $party }}</strong> se obliga a no cambiar el referido rubro, salvo que dicho cambio sea autorizado de manera escrita y de conformidad con las normas que rigen la materia por el <strong>Instituto Autónomo de Mercados del Municipio Chacao (IAMMCH)</strong><span class="clause-end">.--------</span></p>

<p><strong>CUARTA:</strong> <strong>{{ $party }}</strong> se obliga a comercializar los productos y bienes del rubro <strong>“{{ $trade }}”</strong>, garantizando a los consumidores la higiene y calidad de los mismos, respetando las normas sanitarias, de seguridad integral, así como estableciendo unos precios de venta razonables en beneficio de los usuarios del Mercado Municipal de Chacao. En este sentido, <strong>{{ $party }}</strong> deberá cumplir con lo estipulado en el presente documento y el resto de leyes que le corresponda cumplir en su carácter de comerciante, con las Ordenanzas vigentes, destacando la Ordenanza <strong>sobre</strong> la Organización y Funcionamiento del Mercado Municipal de Chacao, el Reglamento Interno del <strong>Instituto Autónomo Mercado Municipal</strong>, y demás normativas que sean emitidas por la Alcaldía del Municipio Chacao o por <strong>EL INSTITUTO</strong> que guarden relación con el presente convenio, relativas al uso de espacios comunes, uso de montacargas, uso de estacionamientos, entre otras. Lo no contemplado en el presente convenio será evaluado por la Junta Directiva del <strong>Instituto</strong><span class="clause-end">.--------</span></p>

<p><strong>QUINTA:</strong> <strong>{{ $party }}</strong> deberá realizar un pago mensual dentro de los primeros cinco (05) días de cada mes, por concepto de Tasa Administrativa por Uso de Bien Público contemplado en el artículo 14 de la Ordenanza sobre la Organización y Funcionamiento del Mercado Chacao N.º 010-2023, que será acreditado a la cuenta bancaria que disponga el <strong>Instituto Autónomo de Mercados del Municipio Chacao</strong>, cuyo monto se determinará en bolívares y será el equivalente a cero coma diez (0,10) veces el tipo de cambio oficial de la moneda de mayor valor publicado por el Banco Central de Venezuela (euros), por los metros cuadrados de extensión del área ocupada, por día de uso. <strong>EL INSTITUTO</strong> entregará el correspondiente comprobante de pago<span class="clause-end">.--------</span></p>

<p><strong>SEXTA:</strong> <strong>{{ $party }}</strong> tendrá la obligación de efectuar el pago correspondiente por Gastos Comunes, dentro de los primeros cinco (05) días de cada mes, los cuales se calcularán a través de una cuota fija que se relaciona en este convenio, determinada con base en la ubicación y metraje de cada local, sobre la base de la alícuota parte que corresponda a dicho inmueble, del valor total del inmueble que le sirve de asiento (Edif. Sede del <strong>IAMMCH</strong>), a saber, la Sede principal del <strong>Instituto Autónomo de Mercados del Municipio Chacao</strong>, así como considerando la proyección de gastos comunes que requerirá <strong>EL INSTITUTO</strong> para su normal funcionamiento, quedando entendido que en caso de presentarse la necesidad de cubrir gastos extraordinarios por este concepto, los mismos serán distribuidos entre los <strong>Cesionarios</strong> y Arrendatarios actuales, conservando las mismas proporciones porcentuales de los pagos fijos establecidos y procurando informar a la brevedad posible sobre la necesidad de realizar dicha erogación extraordinaria. Se entenderá por gastos comunes las erogaciones que deban realizarse para atender el mantenimiento y conservación del inmueble que sirve de asiento al establecimiento comercial, tales como: los servicios de aseo y limpieza, recolección y disposición de desechos sólidos, energía eléctrica, estacionamiento e iluminación, ascensores, reparación y mantenimiento de maquinarias y equipos, publicidad, mantenimiento de áreas comunes de todos los <strong>Cesionarios</strong> y Arrendatarios y cualesquiera otros bienes y servicios necesarios para el buen funcionamiento del inmueble principal. Los gastos comunes se regirán por el presente baremo, expresado en dólares estadounidenses (USD $), y su pago se realizará en bolívares por el monto equivalente, calculado según el tipo de cambio del dólar estadounidense publicado en la página oficial del Banco Central de Venezuela para el día del pago efectivo:</p>

<table>
    <thead><tr><th>DESCRIPCIÓN</th><th>COBRO PB (USD $)</th><th>COBRO MZZ (USD $)</th><th>COBRO COMERCIO (USD $)</th><th>PLAZA (USD $)</th></tr></thead>
    <tbody>
    @foreach ([
        ['BATEA', '2,50', '1,50', '', ''],
        ['LOCAL', '20,00', '18,00', '', ''],
        ['LOCALES NEVERAS', '30,00', '15,00', '', ''],
        ['LOCAL COMIDA NEVERA', '35,00', '30,00', '', ''],
        ['OFICINA TERRAZA', '', '', '150,00', ''],
        ['LOCAL TERRAZA', '', '', '300,00', ''],
        ['LOCAL 6', '', '', '200,00', ''],
        ['LOCAL 7', '', '', '200,00', ''],
        ['LOCAL 8', '', '', '200,00', ''],
        ['KIOSKO FLORES', '', '', '', '50,00'],
        ['KIOSKO CHICHA', '', '', '', '30,00'],
        ['KIOSKO OBLEAS', '', '', '', '35,00'],
    ] as $row)
        <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
    @endforeach
    </tbody>
</table>
<p><strong>EL INSTITUTO</strong> entregará el correspondiente comprobante de pago<span class="clause-end">.--------</span></p>

<p><strong>SÉPTIMA:</strong> La falta de pago oportuno de tres (3) mensualidades consecutivas de tasas de uso o gastos comunes podrá dar lugar a la rescisión de pleno derecho del presente convenio, previo al cumplimiento del procedimiento previsto en la normativa vigente que rige la materia<span class="clause-end">.--------</span></p>

<p><strong>OCTAVA:</strong> Correrán por única y exclusiva cuenta de <strong>{{ $party }}</strong> todos los gastos ocasionados por las reparaciones menores realizadas en razón del normal funcionamiento del puesto asignado, así como todas aquellas que <strong>{{ $party }}</strong> o las personas bajo su dependencia ocasionen a los bienes inmuebles y a cualquier estructura o área del Mercado, por mal uso, negligencia, impericia, imprudencia o inobservancia de las normas. En caso de incumplimiento, <strong>EL INSTITUTO</strong> tendrá el derecho de exigir a <strong>{{ $party }}</strong> el restablecimiento del puesto asignado a su estado original, a su costo y riesgo. En cualquier caso, todas las mejoras o bienhechurías efectuadas con o sin el consentimiento de <strong>EL INSTITUTO</strong> quedarán en beneficio del puesto y no procederá reclamación alguna contra <strong>EL INSTITUTO</strong>. <strong>{{ $party }}</strong> se compromete a no deteriorar el inmueble del que se trata y a realizar las reparaciones menores que requiera relacionadas con cañerías, instalaciones eléctricas, pintura, instalaciones de agua, y también responderá por las reparaciones mayores producidas por inadecuada o inoportuna ejecución de las menores o por haberse generado estas por causas imputables a <strong>{{ $party }}</strong>, tales como no haber reportado fallas menores de forma oportuna o no haber ejecutado reparaciones menores que conlleven a un daño mayor<span class="clause-end">.--------</span></p>

<p><strong>NOVENA:</strong> <strong>{{ $party }}</strong> será {{ $female ? 'la única responsable' : 'el único responsable' }} de las obligaciones y deberes que adquiera con terceros, con motivo de los compromisos y/o contratos comerciales y de trabajo que contraiga con el personal bajo su dependencia. Asimismo, será {{ $female ? 'la única responsable' : 'el único responsable' }} ante la Alcaldía, SENIAT y otros organismos públicos, por pago de tasas, impuestos, presentación de documentos, licencias, permisos u otras obligaciones que deba honrar en función de la actividad económica que desarrolla<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA:</strong> Este convenio es celebrado intuitu personae, por lo que <strong>{{ $party }}</strong> solo podrá vender su fondo de comercio, ceder o traspasar a terceras personas sus derechos una vez verificado que dichas personas cumplen con los requisitos exigidos por <strong>EL INSTITUTO</strong> y de conformidad con las previsiones de la normativa que rige la materia. La cesión o traspaso realizado sin cumplir con las formalidades exigidas en la Ordenanza del Mercado o a título oneroso será nula; en caso de incumplimiento, <strong>{{ $party }}</strong> continuará siendo responsable ante <strong>EL INSTITUTO</strong> de todas las obligaciones que le impone el presente convenio, hasta la entrega definitiva del puesto asignado<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA PRIMERA:</strong> <strong>{{ $party }}</strong> no podrá darle al puesto asignado un uso distinto al establecido en la cláusula Segunda del presente convenio. <strong>{{ $party }}</strong> no podrá nombrar encargados de forma permanente, y la causa de su nombramiento debe estar debidamente justificada y aprobada por <strong>EL INSTITUTO</strong><span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA SEGUNDA:</strong> Serán causales de rescisión del convenio las contempladas en el artículo 22 de la Ordenanza N.º 010-2023 sobre la Organización y Funcionamiento del Mercado Municipal de Chacao. Igualmente se considerará como causal de rescisión del convenio la condena penal definitivamente firme de <strong>{{ $party }}</strong>. En el caso en que <strong>{{ $party }}</strong> haya sido {{ $female ? 'condenada' : 'condenado' }} por los tribunales del país en materia penal, <strong>EL INSTITUTO</strong> hará el llamado a un familiar directo (cónyuge, hijos) que cumpla con los requisitos y formalidades establecidas en la Ordenanza vigente y el Reglamento Interno del <strong>IAMMCH</strong>, para darle continuidad al Derecho de Uso, generando un nuevo convenio, con el objeto de garantizar la continuidad del Derecho al Trabajo y la Protección a la Familia<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA TERCERA:</strong> En caso de muerte de <strong>{{ $party }}</strong>, se transmitirá el Derecho de Uso sobre el área asignada a favor de su heredero o legatario, como derecho preferencial, realizando dicha solicitud dentro de los primeros treinta (30) días siguientes al fallecimiento de <strong>{{ $party }}</strong> y consignando constancia del inicio del trámite de Declaración de Únicos y Universales Herederos, emanada del Tribunal respectivo dentro de los noventa (90) días continuos siguientes al deceso de <strong>{{ $party }}</strong>. A su vez, la persona solicitante deberá reunir las condiciones necesarias para realizar actos de comercio dentro del Mercado Municipal de Chacao. En caso de que se ceda el derecho de uso a una sucesión conformada por varios herederos con la misma jerarquía, los mismos deberán autorizar a una persona para que sea la beneficiaria del derecho preferencial ante <strong>EL INSTITUTO</strong>. Se deja entendido finalmente que, por tratarse de un bien público, el mismo no es heredable bajo ningún concepto y lo que se establece en la presente cláusula es un derecho preferencial de selección de un <strong>nuevo cesionario</strong>. Igualmente se permitirá que <strong>{{ $party }}</strong> en vida pueda declarar por escrito de forma expresa la persona a la cual le otorgará el derecho preferencial para que el <strong>Instituto</strong> le transfiera la Cesión de Derecho de Uso de forma posterior a su fallecimiento. De realizarse esta designación en vida, la misma será considerada de forma inicial, exclusiva y previa a acudir al derecho preferencial del heredero más cercano<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA CUARTA:</strong> El incumplimiento de <strong>{{ $party }}</strong> de cualquiera de las obligaciones previstas en el presente convenio será objeto de las sanciones contempladas en la Ordenanza que rige la materia y en el Reglamento Interno de Funcionamiento del Mercado Municipal de Chacao. En caso de que la sanción impuesta implique la rescisión del convenio, los efectos de la rescisión quedarán suspendidos por un lapso de treinta (30) días continuos luego de notificada la decisión de rescindir su convenio, para proponer a un <strong>nuevo cesionario</strong>, sobre el cual, de cumplir con los requisitos establecidos en el Reglamento Interno del <strong>IAMMCH</strong> y los extremos legales exigidos, podrá realizar la Cesión de Derechos de Uso del inmueble del que se trata. En este caso, <strong>{{ $party }}</strong> deberá cancelar el doble de la cantidad prevista por concepto de Cesión de Derechos, de conformidad con lo expuesto en el párrafo 1 del artículo 22 de la Ordenanza N.º 010-2023 sobre la Organización y Funcionamiento del Mercado Municipal de Chacao<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA QUINTA:</strong> En ningún caso <strong>EL INSTITUTO</strong> será responsable por los daños que pueda sufrir <strong>{{ $party }}</strong>, las cosas de su propiedad o los servicios que disfrute o pueda disfrutar el puesto asignado, causados por ruina, deterioro, inundaciones, explosiones, derrumbes, deslaves, movimientos sísmicos, incendios, caso fortuito o fuerza mayor, por el uso culposo, negligencia, imprudencia, impericia o inobservancia de las leyes o del presente convenio, sea cual fuere su causa u origen, salvo que pueda demostrar la responsabilidad directa de <strong>EL INSTITUTO</strong><span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA SEXTA:</strong> Las partes convienen exonerarse mutuamente de toda responsabilidad en los casos en que <strong>{{ $party }}</strong> sea {{ $female ? 'privada' : 'privado' }} total o parcialmente del uso, goce y disfrute del puesto asignado, cuando se deba a causas no imputables a <strong>EL INSTITUTO</strong>, causadas por desastres naturales, movimientos sísmicos, derrumbes, incendios, casos fortuitos o de fuerza mayor, sin que <strong>{{ $party }}</strong> pueda reclamar en estos casos algún tipo de indemnización a <strong>EL INSTITUTO</strong><span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA SÉPTIMA:</strong> <strong>{{ $party }}</strong> acepta expresamente que <strong>EL INSTITUTO</strong> puede realizar, por sí o por medio de personas autorizadas, todas las inspecciones y revisiones en el puesto asignado que estime pertinentes durante la vigencia del presente convenio<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA OCTAVA:</strong> Cualquier notificación que, con ocasión del presente convenio, deban cursarse las partes deberá consignarse por escrito en las siguientes direcciones: a) Cuando sea dirigida a <strong>{{ $party }}</strong>, mediante citación personal en el <strong>Local {{ $local['code'] }}</strong>, pudiendo apoyarse en los siguientes medios de contacto:
@foreach ($signers as $signer)
    <strong>{{ $signer['name'] }}</strong>: correo electrónico {{ $signer['email'] }} y teléfono {{ $signer['phone'] }}{{ $loop->last ? '.' : ';' }}
@endforeach
Dejando constancia de la misma. b) Cuando sea dirigida a <strong>EL INSTITUTO</strong>, en la Oficina de Administración, en el Nivel Comercio de la sede del Mercado Municipal de Chacao, pudiendo apoyarse también en los correos electrónicos consultoriajuridica.iammch@gmail.com y contabilidad.iammch@gmail.com. Ambas partes convienen que toda comunicación se considerará válidamente realizada y surtirá efectos legales si es enviada a la dirección correspondiente indicada en esta cláusula<span class="clause-end">.--------</span></p>

<p><strong>DÉCIMA NOVENA:</strong> <strong>{{ $party }}</strong> se compromete a notificar por escrito y de forma oportuna a <strong>EL INSTITUTO</strong> toda novedad o incidencia que pueda influir en el normal desenvolvimiento del inmueble asignado, tales como nombramiento de ayudantes, cierre por causas de enfermedad, ausencias temporales, necesidad de remodelación, reparaciones, entre otras. No se considerará como causa justificada ningún tipo de cierre realizado al local que no haya sido comunicado y autorizado previamente por <strong>EL INSTITUTO</strong> o avalado de forma posterior. En los casos en que no se hubiere podido comunicar de forma anticipada el cierre o la situación acaecida, deberán consignarse posteriormente los soportes debidos que justifiquen dicha situación<span class="clause-end">.--------</span></p>

<p><strong>VIGÉSIMA:</strong> <strong>{{ $party }}</strong> deberá cumplir con los horarios de funcionamiento y atención al público que <strong>EL INSTITUTO</strong> establezca al respecto<span class="clause-end">.--------</span></p>

<p><strong>VIGÉSIMA PRIMERA:</strong> <strong>{{ $party }}</strong> se compromete a publicar en una cartelera visible al público los precios de toda la mercancía o productos que ofrezca en el local que le ha sido asignado, lo cual, en caso de no realizarse, podrá dar lugar al cierre temporal del local y al establecimiento de la sanción y multa correspondiente<span class="clause-end">.--------</span></p>

<p><strong>VIGÉSIMA SEGUNDA:</strong> Las controversias de cualquier naturaleza que puedan suscitarse en la interpretación o ejecución del presente convenio, sus derivados y consecuencias, serán dirimidas en primer lugar por los órganos para la resolución de conflictos presentes en el municipio Chacao, como Justicia Municipal y la Casa de Justicia y Paz, en la búsqueda de un acuerdo conciliatorio. De no llegar a ningún acuerdo con los entes competentes mencionados, las partes acudirán a los Tribunales del Área Metropolitana de Caracas, a cuya jurisdicción declaran someterse las partes<span class="clause-end">.--------</span></p>

<p><strong>VIGÉSIMA TERCERA:</strong> La falta de uso, abandono del espacio asignado o de la actividad económica por un periodo superior a seis (6) meses, sin causa justificada, dará lugar a la rescisión de pleno derecho del presente convenio, previo al cumplimiento del procedimiento previsto en la normativa que rige la materia<span class="clause-end">.--------</span></p>

<p><strong>VIGÉSIMA CUARTA:</strong> <strong>{{ $party }}</strong> podrá ceder el derecho de uso que le ha sido asignado mediante esta cesión, sujetándose a los requisitos que a tal efecto están establecidos en el Manual de Normas y Procedimientos del <strong>Instituto</strong>. La cesión realizada prescindiendo del cumplimiento de los requisitos se considerará nula<span class="clause-end">.--------</span></p>

<p><strong>VIGÉSIMA QUINTA:</strong> La fecha de inicio de la presente Cesión de Derecho es el <strong>{{ \Illuminate\Support\Carbon::parse($snapshot['start_date'])->format('d/m/Y') }}</strong>. La presente Cesión de Derecho tendrá una vigencia de veinte (20) años continuos contados a partir de la firma del presente convenio, de fecha <strong>{{ \Illuminate\Support\Carbon::parse($snapshot['signature_date'])->format('d/m/Y') }}</strong>, hasta el <strong>{{ \Illuminate\Support\Carbon::parse($snapshot['end_date'])->format('d/m/Y') }}</strong>, prorrogable por un lapso de veinte (20) años más, de forma consecutiva sin necesidad de realizar un nuevo convenio, siempre y cuando <strong>{{ $party }}</strong> haya mantenido una conducta acorde con las Ordenanzas Municipales, Reglamentos Internos, el presente convenio y demás leyes que le sean aplicables. En caso de realizarse una nueva Cesión a favor de un tercero, la vigencia de dicho convenio será por el plazo restante de la cesión del actual titular<span class="clause-end">.--------</span></p>

<p>Finalmente se declara que la presente cesión de derecho de uso es a título gratuito.
@if ($plural)
    Y {{ $female ? 'nosotras' : 'nosotros' }}, <strong>{{ implode(', ', array_column($signers, 'name')) }}</strong>, suficientemente {{ $female ? 'identificadas' : 'identificados' }}, declaramos que aceptamos la Cesión de Derecho de Uso que se nos hace a través del presente convenio, en los términos expuestos.
@else
    Y yo, <strong>{{ $person['name'] }}</strong>, suficientemente {{ $person['identified'] }}, declaro que acepto la Cesión de Derecho de Uso que se me hace a través del presente convenio, en los términos expuestos.
@endif
En Caracas, {{ $snapshot['signature_date_words'] }}. Firman en señal de conformidad:</p>

<table class="signatures">
    <tr><td>_________________________<br><strong>EL INSTITUTO</strong><br><strong>{{ $president['name'] }}<br>{{ $president['document'] }}</strong></td><td>_________________________<br><strong>{{ $person['party'] }} PRINCIPAL</strong><br><strong>{{ $person['name'] }}<br>{{ $person['document'] }}</strong></td></tr>
    @foreach (array_chunk($snapshot['additional_concessionaires'] ?? [], 2) as $row)
        <tr>
            @foreach ($row as $signer)
                <td>_________________________<br><strong>{{ $signer['party'] }} ADICIONAL</strong><br><strong>{{ $signer['name'] }}<br>{{ $signer['document'] }}</strong></td>
            @endforeach
            @if (count($row) === 1)<td></td>@endif
        </tr>
    @endforeach
</table>
</body>
</html>
