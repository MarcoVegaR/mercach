<?php

declare(strict_types=1);

use App\Models\AgreementSequence;
use App\Models\Charge;
use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\DocumentType;
use App\Models\GeneratedAgreement;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\PhoneAreaCode;
use App\Models\TradeCategory;
use App\Models\User;
use App\Services\GeneratedAgreementService;
use App\Services\GeneratedAgreementWordExporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-12 10:00:00');
    Storage::fake('local');
    $this->seed([
        Database\Seeders\PermissionsSeeder::class,
        Database\Seeders\TradeCategoriesSeeder::class,
        Database\Seeders\ConcessionaireTypesSeeder::class,
        Database\Seeders\DocumentTypesSeeder::class,
        Database\Seeders\MarketsSeeder::class,
        Database\Seeders\LocalTypesSeeder::class,
        Database\Seeders\LocalStatusesSeeder::class,
        Database\Seeders\LocalLocationSeeder::class,
    ]);
    $this->user = User::factory()->create();
    $this->user->givePermissionTo('catalogs.contract.create');
    $this->person = Concessionaire::query()->create([
        'concessionaire_type_id' => ConcessionaireType::query()->where('code', 'PNAT')->value('id'),
        'full_name' => 'María Pérez', 'sex' => 'F', 'marital_status' => 'single',
        'document_type_id' => DocumentType::query()->where('code', 'V')->value('id'),
        'document_number' => '12345678', 'fiscal_address' => 'Chacao, Miranda',
        'email' => 'maria@example.test',
        'phone_area_code_id' => PhoneAreaCode::query()->create(['code' => '0414', 'is_active' => true])->id,
        'phone_number' => '1234567', 'is_active' => true,
    ]);
    $this->local = Local::query()->create([
        'code' => 'CONV-01', 'name' => 'Local de prueba',
        'market_id' => Market::query()->value('id'),
        'local_type_id' => LocalType::query()->value('id'),
        'local_status_id' => LocalStatus::query()->value('id'),
        'local_location_id' => LocalLocation::query()->value('id'),
        'area_m2' => 12.50, 'is_active' => true,
    ]);
    $this->data = [
        'concessionaire_id' => $this->person->id, 'local_id' => $this->local->id,
        'trade_category_id' => TradeCategory::query()->where('is_active', true)->value('id'),
        'start_date' => '2026-12-01', 'signature_date' => '2026-11-20',
        'issuance_token' => (string) Str::uuid(),
    ];
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function reviewedAgreementData(): array
{
    $service = app(GeneratedAgreementService::class);
    $data = test()->data;
    $data['preview_hash'] = $service->previewHash($service->html($service->snapshot($data)), $data['issuance_token']);

    return $data;
}

function additionalAgreementSigner(): Concessionaire
{
    $person = test()->person->replicate();
    $person->fill(['full_name' => 'Juan Firmante', 'sex' => 'M', 'marital_status' => 'married', 'document_number' => '23456789', 'email' => 'juan@example.test']);
    $person->save();

    return $person;
}

function agreementSourceContract(): Contract
{
    test()->seed([
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
    ]);
    $contract = Contract::factory()->create([
        'contract_type_id' => App\Models\ContractType::query()->where('code', 'CONV')->value('id'),
        'contract_status_id' => App\Models\ContractStatus::query()->where('code', 'VIG')->value('id'),
        'contract_modality_id' => App\Models\ContractModality::query()->where('code', 'M2')->value('id'),
        'trade_category_id' => test()->data['trade_category_id'],
    ]);
    $contract->concessionaires()->attach(test()->person, ['is_primary' => true]);
    $contract->locals()->attach(test()->local);

    return $contract;
}

it('includes and freezes every additional signer in the document and history', function (): void {
    $additional = additionalAgreementSigner();
    $this->data['additional_concessionaire_ids'] = [$additional->id];
    $this->actingAs($this->user)->post(route('agreements.store'), reviewedAgreementData())->assertSessionHasNoErrors()->assertRedirect();
    $agreement = GeneratedAgreement::query()->sole();
    expect($agreement->snapshot['additional_concessionaires'][0])->toMatchArray([
        'id' => $additional->id, 'name' => 'Juan Firmante', 'marital_status_label' => 'casado', 'sex' => 'M',
    ])->and($agreement->rendered_html)->toContain('LOS CESIONARIOS', 'CADA CESIONARIO', '<strong>Juan Firmante<br>V-23.456.789</strong>', 'EL CESIONARIO ADICIONAL', 'declaramos que aceptamos', 'juan@example.test');
    $additional->update(['full_name' => 'Nombre cambiado']);
    $this->get(route('agreements.show', $agreement))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('agreement.snapshot.additional_concessionaires.0.name', 'Juan Firmante'));
    $this->get(route('agreements.index', ['q' => 'Juan']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements.data', 1));
});

it('uses feminine group concordances when all signers are women', function (): void {
    $additional = additionalAgreementSigner();
    $additional->update(['sex' => 'F', 'full_name' => 'Ana Firmante']);
    $this->data['additional_concessionaire_ids'] = [$additional->id];
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    expect($html)->toContain('LAS CESIONARIAS', 'CADA CESIONARIA', 'nosotras', 'suficientemente identificadas', 'LA CESIONARIA ADICIONAL');
});

it('validates additional signers and requires complete profiles', function (string $case, string $error): void {
    $additional = additionalAgreementSigner();
    $this->data['additional_concessionaire_ids'] = match ($case) {
        'principal' => [$this->person->id],
        'duplicate' => [$additional->id, $additional->id],
        'missing' => [999999],
        default => [$additional->id],
    };
    if ($case === 'incomplete') {
        $additional->update(['sex' => null]);
    }
    $this->actingAs($this->user)->from(route('agreements.index'))->get(route('agreements.preview', $this->data))->assertSessionHasErrors($error);
})->with([
    ['principal', 'additional_concessionaire_ids.0'],
    ['duplicate', 'additional_concessionaire_ids.0'],
    ['missing', 'additional_concessionaire_ids.0'],
    ['incomplete', 'additional_concessionaire_ids'],
]);

it('detects changes in an additional signer after preview', function (): void {
    $additional = additionalAgreementSigner();
    $this->data['additional_concessionaire_ids'] = [$additional->id];
    $data = reviewedAgreementData();
    $additional->update(['email' => 'actualizado@example.test']);
    $this->actingAs($this->user)->post(route('agreements.store'), $data)->assertSessionHasErrors('preview_hash');
    expect(GeneratedAgreement::query()->count())->toBe(0);
});

it('offers existing contract locals rubro and signers without requiring contract view permission', function (): void {
    $contract = agreementSourceContract();
    $additional = additionalAgreementSigner();
    $contract->concessionaires()->attach($additional, ['is_primary' => false]);
    $this->actingAs($this->user)->get(route('agreements.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('contracts', 1)
            ->where('contracts.0.id', $contract->id)
            ->where('contracts.0.local_ids.0', $this->local->id)
            ->where('contracts.0.trade_category_id', $this->data['trade_category_id'])
            ->has('contracts.0.concessionaire_ids', 2));
    $this->data['source_contract_id'] = $contract->id;
    $this->data['additional_concessionaire_ids'] = [$additional->id];
    $before = $contract->fresh()->getAttributes();
    $this->post(route('agreements.store'), reviewedAgreementData())->assertSessionHasNoErrors()->assertRedirect();
    expect(GeneratedAgreement::query()->sole()->snapshot['source_contract'])->toBe(['id' => $contract->id, 'number' => $contract->number])
        ->and($contract->fresh()->getAttributes())->toBe($before)
        ->and(Contract::query()->count())->toBe(1);
});

it('rejects inconsistent selections from an existing contract', function (string $case): void {
    $contract = agreementSourceContract();
    $this->data['source_contract_id'] = $contract->id;
    if ($case === 'person') {
        $this->data['concessionaire_id'] = additionalAgreementSigner()->id;
    } elseif ($case === 'local') {
        $local = $this->local->replicate();
        $local->code = 'OTRO-LOCAL';
        $local->save();
        $this->data['local_id'] = $local->id;
    } else {
        $this->data['trade_category_id'] = TradeCategory::query()->where('id', '!=', $contract->trade_category_id)->where('is_active', true)->value('id');
    }
    $this->actingAs($this->user)->from(route('agreements.index'))->get(route('agreements.preview', $this->data))->assertSessionHasErrors('source_contract_id');
})->with(['person', 'local', 'trade']);

it('renders the market logo and lawyer abbreviation and names downloads from frozen data', function (): void {
    $logo = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
    Storage::disk('local')->put('branding/logo.png', base64_decode($logo));
    $agreement = app(GeneratedAgreementService::class)->issue(reviewedAgreementData(), $this->user->id);
    expect($agreement->rendered_html)->toContain('Abg. Jesús A. Salmerón', 'Logo del mercado', 'data:image/png;base64,'.$logo);
    $this->person->update(['full_name' => 'Otro nombre']);
    $this->local->update(['code' => 'OTRO']);
    $this->actingAs($this->user)->get(route('agreements.download', $agreement))->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename=convenio_maria_perez_local_conv_01_2026-11-12_0001_2026.pdf');
});

it('allows the generator with only the existing contract create permission', function (): void {
    expect($this->user->can('catalogs.contract.view'))->toBeFalse();
    $this->actingAs($this->user)->get(route('agreements.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agreements/index')->has('concessionaires', 1)->has('locals', 1));
});

it('reports profile completeness from the same server checks without exposing contact data', function (): void {
    $this->person->update(['sex' => null, 'marital_status' => null]);
    $this->actingAs($this->user)->get(route('agreements.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('concessionaires.0.missing_fields', ['sexo', 'estado civil'])
            ->missing('concessionaires.0.email')->missing('concessionaires.0.phone_number'));
    $this->person->update(['sex' => 'F', 'marital_status' => 'single']);
    $this->get(route('agreements.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('concessionaires.0.missing_fields', []));
});

it('supports the shared history pagination without granting extra permissions', function (): void {
    $this->actingAs($this->user)->get(route('agreements.index', ['tab' => 'history', 'per_page' => 25]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('filters.tab', 'history')->where('agreements.per_page', 25));
    $this->from(route('agreements.index'))->get(route('agreements.index', ['per_page' => 1000]))->assertSessionHasErrors('per_page');
});

it('protects every endpoint with the same contract create permission', function (): void {
    $agreement = app(GeneratedAgreementService::class)->issue(reviewedAgreementData(), $this->user->id);
    $other = User::factory()->create();
    $other->givePermissionTo('catalogs.contract.view');
    $this->actingAs($other)->get(route('agreements.index'))->assertForbidden();
    $this->get(route('agreements.preview', $this->data))->assertForbidden();
    $this->get(route('agreements.preview-pdf', reviewedAgreementData()))->assertForbidden();
    $this->post(route('agreements.store'), reviewedAgreementData())->assertForbidden();
    $this->get(route('agreements.show', $agreement))->assertForbidden();
    $this->get(route('agreements.download', $agreement))->assertForbidden();
    $this->get(route('agreements.preview-word', reviewedAgreementData()))->assertForbidden();
    $this->get(route('agreements.download-word', $agreement))->assertForbidden();
});

it('requires authentication', function (): void {
    $this->get(route('agreements.index'))->assertRedirect(route('login'));
    $this->get(route('agreements.preview-word', $this->data))->assertRedirect(route('login'));
});

/** @return array<string, string> */
function agreementWordParts(string $bytes): array
{
    $path = tempnam(sys_get_temp_dir(), 'agreement-test-');
    try {
        file_put_contents($path, $bytes);
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();
        $parts = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $parts[$name] = $zip->getFromIndex($index);
            if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) {
                expect((new DOMDocument)->loadXML($parts[$name], LIBXML_NONET))->toBeTrue();
            }
        }
        $zip->close();

        return $parts;
    } finally {
        unlink($path);
    }
}

it('downloads an editable word preview without numbering issuing or saving a document', function (): void {
    $files = Storage::disk('local')->allFiles();
    $temporaryFiles = glob(sys_get_temp_dir().'/agreement-word-*');
    $data = reviewedAgreementData();
    $this->actingAs($this->user)->get(route('agreements.preview', $this->data))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('wordUrl', route('agreements.preview-word', $data)));
    $response = $this->get(route('agreements.preview-word', $data))->assertOk()
        ->assertHeader('Content-Type', GeneratedAgreementWordExporter::MIME_TYPE)
        ->assertHeader('Content-Disposition', 'attachment; filename="vista_previa_convenio.docx"')
        ->assertHeader('Cache-Control', 'no-store, private');
    $parts = agreementWordParts($response->getContent());
    $xml = $parts['word/document.xml'];

    expect($parts['word/styles.xml'])->toContain('DejaVu Sans')
        ->and($xml)->toContain('María Pérez', 'CONV-01', 'SEXTA:', '12,50 m²', 'VISTA PREVIA', '<w:b w:val="1"/>', 'w:leader="hyphen"', '<w:tab/>')
        ->and($xml)->toContain('w:w="12246"', 'w:h="18709"')
        ->and(GeneratedAgreement::query()->count())->toBe(0)
        ->and(AgreementSequence::query()->count())->toBe(0)
        ->and(Contract::query()->count())->toBe(0)
        ->and(Charge::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe($files)
        ->and(glob(sys_get_temp_dir().'/agreement-word-*'))->toBe($temporaryFiles);
});

it('requires a current untampered preview before exporting word', function (): void {
    $this->actingAs($this->user)->getJson(route('agreements.preview-word', $this->data))->assertUnprocessable()
        ->assertJsonValidationErrors('preview_hash');
    $data = reviewedAgreementData();
    $data['preview_hash'] = str_repeat('0', 64);
    $this->get(route('agreements.preview-word', $data))->assertConflict();
    $data = reviewedAgreementData();
    $this->person->update(['full_name' => 'Nuevo nombre']);
    $this->get(route('agreements.preview-word', $data))->assertConflict();
    expect(GeneratedAgreement::query()->count())->toBe(0)->and(AgreementSequence::query()->count())->toBe(0);
});

it('exports word from the frozen HTML including historical branding without changing the original PDF', function (): void {
    $additional = additionalAgreementSigner();
    $this->data['additional_concessionaire_ids'] = [$additional->id];
    $service = app(GeneratedAgreementService::class);
    $agreement = $service->issue(reviewedAgreementData(), $this->user->id);
    $original = $agreement->refresh()->getAttributes();
    $pdf = Storage::disk('local')->get($agreement->pdf_path);
    $files = Storage::disk('local')->allFiles();
    $this->person->update(['full_name' => 'Nombre nuevo']);
    $this->local->update(['code' => 'OTRO-LOCAL', 'area_m2' => '99']);
    Storage::disk('local')->put('branding/logo.png', 'invalid new branding');

    $response = $this->actingAs($this->user)->get(route('agreements.download-word', $agreement))->assertOk()
        ->assertHeader('Content-Type', GeneratedAgreementWordExporter::MIME_TYPE)
        ->assertHeader('Content-Disposition', 'attachment; filename="convenio_maria_perez_local_conv_01_2026-11-12_0001_2026.docx"');
    $parts = agreementWordParts($response->getContent());
    expect($parts['word/document.xml'])->toContain('María Pérez', 'Juan Firmante', 'CADA CESIONARIO', 'CONV-01', '0001/2026', '12,50 m²')
        ->not->toContain('Nombre nuevo', 'OTRO-LOCAL', 'invalid new branding')
        ->and($agreement->fresh()->getAttributes())->toBe($original)
        ->and(Storage::disk('local')->get($agreement->pdf_path))->toBe($pdf)
        ->and(AgreementSequence::query()->sole()->next_number)->toBe(2);
    $this->get(route('agreements.download', $agreement))->assertOk()->assertStreamedContent($pdf);
    expect(Storage::disk('local')->allFiles('generated-agreements'))->toBe(array_values(array_filter($files, fn (string $file): bool => str_starts_with($file, 'generated-agreements/'))));
});

it('escapes special characters and preserves literal historical clause endings in word', function (): void {
    $html = '<html><body><p><strong>José &amp; María &lt;Socios&gt;</strong> V-12.345.678<span class="clause-end">.--------</span></p>'
        .'<table class="signatures"><tr><td><strong>José</strong><br>XXXX</td><td>María</td></tr></table></body></html>';
    $parts = agreementWordParts(app(GeneratedAgreementWordExporter::class)->render($html));
    expect($parts['word/document.xml'])->toContain('José &amp; María &lt;Socios&gt;', '.--------', '<w:br/>', '<w:cantSplit');
});

it('preserves every paragraph title and table cell from the source HTML as editable Word text', function (): void {
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    $parts = agreementWordParts(app(GeneratedAgreementWordExporter::class)->render($html));
    $source = new DOMDocument;
    @$source->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
    $word = new DOMDocument;
    $word->loadXML($parts['word/document.xml'], LIBXML_NONET);
    $wordPath = new DOMXPath($word);
    $wordPath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $normalize = fn (string $text): string => trim(preg_replace('/\s+/u', ' ', $text));
    $paragraphs = array_map(
        fn (DOMNode $node): string => $normalize($node->textContent),
        iterator_to_array($wordPath->query('//w:body//w:p')),
    );
    foreach ((new DOMXPath($source))->query('//body/p | //body/h1 | //body/div[@class="number"] | //body/table//td | //body/table//th') as $node) {
        expect($paragraphs)->toContain($normalize($node->textContent));
    }
    expect($wordPath->query('//w:r/w:tab')->length)->toBe(25)
        ->and($wordPath->query('//w:tbl')->length)->toBe(3);
});

it('returns a readable error when preserved images cannot be exported without issuing anything', function (): void {
    $this->mock(GeneratedAgreementWordExporter::class)->shouldReceive('render')->once()
        ->andThrow(new \App\Exceptions\DomainActionException('Utiliza la descarga PDF.'));
    $this->actingAs($this->user)->get(route('agreements.preview-word', reviewedAgreementData()))
        ->assertUnprocessable()->assertSeeText('Utiliza la descarga PDF.');
    expect(GeneratedAgreement::query()->count())->toBe(0)->and(AgreementSequence::query()->count())->toBe(0);
});

it('rejects remote and filesystem image sources when exporting word', function (string $source): void {
    $html = '<html><body><div class="header"><img src="'.$source.'"></div></body></html>';
    expect(fn () => app(GeneratedAgreementWordExporter::class)->render($html))->toThrow(\App\Exceptions\DomainActionException::class);
})->with(['https://example.test/logo.png', 'file:///etc/passwd', '/etc/passwd']);

it('previews a real pdf without allocating a number or changing contracts', function (): void {
    $this->actingAs($this->user)->get(route('agreements.preview', $this->data))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('agreements/preview')
            ->where('pdfUrl', route('agreements.preview-pdf', reviewedAgreementData()))
            ->missing('pdf')
            ->where('snapshot.concessionaire.name', 'María Pérez')
            ->where('snapshot.local.code', 'CONV-01')
            ->where('snapshot.start_date', $this->data['start_date'])
            ->where('snapshot.end_date', '2046-11-20')
            ->has('values.preview_hash'));
    $response = $this->get(route('agreements.preview-pdf', reviewedAgreementData()));
    $response->assertOk()->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="vista_previa_convenio.pdf"');
    expect($response->getContent())->toStartWith('%PDF');
    expect(GeneratedAgreement::query()->count())->toBe(0)
        ->and(AgreementSequence::query()->count())->toBe(0)
        ->and(Contract::query()->count())->toBe(0)
        ->and(Charge::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('generated-agreements'))->toBe([]);
});

it('rejects a stale or tampered pdf preview without issuing a document', function (): void {
    $data = reviewedAgreementData();
    $data['preview_hash'] = str_repeat('0', 64);
    $this->actingAs($this->user)->get(route('agreements.preview-pdf', $data))->assertConflict();
    $data = reviewedAgreementData();
    $this->person->update(['full_name' => 'Datos actualizados']);
    $this->get(route('agreements.preview-pdf', $data))->assertConflict();
    expect(GeneratedAgreement::query()->count())->toBe(0)->and(AgreementSequence::query()->count())->toBe(0);
});

it('issues an immutable numbered document with the complete corrected template', function (): void {
    $status = $this->local->local_status_id;
    $this->actingAs($this->user)->post(route('agreements.store'), reviewedAgreementData())->assertSessionHasNoErrors()->assertRedirect();
    $agreement = GeneratedAgreement::query()->sole();
    $html = $agreement->rendered_html;
    expect($agreement->number)->toBe('0001/2026')
        ->and($agreement->snapshot['concessionaire']['name'])->toBe('María Pérez')
        ->and($agreement->snapshot['concessionaire']['marital_status_label'])->toBe('soltera')
        ->and($agreement->template_version)->toBe('2026-11-25-v11')
        ->and($html)->toContain('LA CESIONARIA', '<strong>12,50 m²</strong>', 'VIGÉSIMA QUINTA:', 'COBRO PB (USD $)', 'G-20009256-4', '20/11/2026', '20/11/2046', 'del año 2026')
        ->and($html)->not->toContain('XXXXX', 'Notaria Pública', 'detentaba anteriormente', 'Convenio #', 'presente contrato')
        ->and(Contract::query()->count())->toBe(0)
        ->and(Charge::query()->count())->toBe(0)
        ->and($this->local->fresh()->local_status_id)->toBe($status)
        ->and(AgreementSequence::query()->sole()->next_number)->toBe(2);
    $pdf = Storage::disk('local')->get($agreement->pdf_path);
    expect($pdf)->toStartWith('%PDF');
    preg_match('/\/MediaBox\s*\[([^]]+)\]/', $pdf, $matches);
    expect($matches[1])->toContain('612.28', '935.43');
    $this->get(route('agreements.show', $agreement))->assertOk()->assertInertia(fn (Assert $page) => $page->component('agreements/show')->where('agreement.number', '0001/2026'));
});

it('includes the institutional foundation authorization and amicable dispute resolution in PDF source and Word', function (): void {
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    $parts = agreementWordParts(app(GeneratedAgreementWordExporter::class)->render($html));
    $word = new DOMDocument;
    $word->loadXML($parts['word/document.xml'], LIBXML_NONET);
    foreach ([strip_tags($html), $word->textContent] as $text) {
        expect($text)->toContain(
            'ente descentralizado con personalidad jurídica y patrimonio propio e independiente del Fisco Municipal',
            'Ordenanza N.º 005-09 de Creación y Funcionamiento del Instituto Autónomo de Mercados del Municipio Chacao',
            'Gaceta Municipal Número Extraordinario 7.738 de fecha 28 de abril de 2009',
            'Registro Único de Información Fiscal: G-20009256-4',
            'debidamente autorizado para la celebración del presente acto por la Junta Directiva del Instituto',
            'Sesión Extraordinaria Nro.: 057, Pres.: 02, de fecha: 20 de noviembre de 2026',
            'Resolución del IAMMCH Nro.: 015-2026',
            'serán dirimidas en primer lugar de forma amistosa entre las partes y, en segunda instancia, por los órganos',
            'las partes acudirán a los Tribunales del Área Metropolitana de Caracas',
        );
        expect(substr_count($text, 'G-20009256-4'))->toBe(1);
    }
    expect($html)->not->toContain('background-color: cyan', '<mark>');
});

it('reduces only the upper left watermark and preserves the lower right artwork and logo size', function (): void {
    $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=';
    Storage::disk('local')->put('branding/letterhead.png', base64_decode($image));
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));

    expect($html)->toContain(
        'class="letterhead letterhead-top"',
        'class="letterhead letterhead-bottom"',
        '.letterhead-top img { width: 172.8mm; height: 264mm; }',
        '.letterhead-bottom { top: 135mm; }',
        '.letterhead-bottom img { position: absolute; top: -165mm; left: 0; width: 216mm; height: 330mm; }',
        'height: 165mm; overflow: hidden;',
        'height: 42mm; width: auto;',
        '.logo { position: absolute; top: -14mm; right: 0; height: 42mm; width: auto; }',
        '.header { position: relative; min-height: 44mm; }',
        '.legal { font-size: 9pt; text-align: center; width: 48mm; padding-top: 8mm; line-height: 1.3; }',
    );
});

it('expresses surface area only in figures and square meters', function (string $area, string $formatted): void {
    $this->local->update(['area_m2' => $area]);
    $service = app(GeneratedAgreementService::class);
    $snapshot = $service->snapshot($this->data);
    $html = $service->html($snapshot);

    expect($snapshot['local'])->not->toHaveKey('area_words')
        ->and($html)->toContain('superficie total aproximada de <strong>'.$formatted.' m²</strong>')
        ->not->toContain('centésima', 'centímetros', 'metros cuadrados con');
})->with([
    ['12.50', '12,50'],
    ['1.00', '1,00'],
    ['1234.01', '1.234,01'],
]);

it('removes blank spacing between clauses while retaining paragraphs and line height', function (): void {
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));

    expect($html)->toContain(
        'p { text-align: justify; margin: 0; }',
        'line-height: 1.65;',
        '<p><strong>PRIMERA:</strong>',
        '<p><strong>SEGUNDA:</strong>',
    )->not->toContain('margin: 0 0 3mm;');
});

it('bolds all institute party local identification and trade references', function (string $sex, ?string $additionalSex): void {
    $this->person->update(['sex' => $sex]);
    if ($additionalSex !== null) {
        $additional = additionalAgreementSigner();
        $additional->update(['sex' => $additionalSex]);
        $this->data['additional_concessionaire_ids'] = [$additional->id];
    }
    $service = app(GeneratedAgreementService::class);
    $snapshot = $service->snapshot($this->data);
    $html = $service->html($snapshot);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $unboldedReferences = [];
    foreach ($xpath->query('//body//text()[not(ancestor::strong)]') as $node) {
        $textWithoutEmails = preg_replace('/[\w.+-]+@[\w.-]+/u', '', $node->nodeValue);
        if (preg_match('/instituto|iammch|cesionari[oa]s?/iu', $textWithoutEmails)
            || str_contains($node->nodeValue, $snapshot['local']['code'])
            || str_contains($node->nodeValue, $snapshot['trade']['name'])) {
            $unboldedReferences[] = $node->nodeValue;
        }
    }

    expect($unboldedReferences)->toBe([])
        ->and($html)->toContain('<strong>Local CONV-01</strong>', '<strong>“'.$snapshot['trade']['name'].'”</strong>')
        ->not->toContain('<strong><strong>');
    $party = $additionalSex === null
        ? $snapshot['concessionaire']['party']
        : ($sex === 'F' && $additionalSex === 'F' ? 'CADA CESIONARIA' : 'CADA CESIONARIO');
    expect($html)->toContain('de <strong>'.$party.'</strong>', 'a <strong>'.$party.'</strong>', 'a <strong>EL INSTITUTO</strong>')
        ->not->toContain('<strong>DE ', '<strong>A ', 'AL CESIONARIO', 'DEL CESIONARIO', 'al <strong>EL INSTITUTO</strong>');
})->with([
    ['F', null],
    ['M', null],
    ['F', 'F'],
    ['M', 'M'],
    ['F', 'M'],
]);

it('marks the final period of each complete clause for dynamic PDF leaders', function (): void {
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    $clauses = $xpath->query('//p[strong[1][contains(., ":")]]');

    expect($clauses->length)->toBe(25)
        ->and($xpath->query('//span[@class="clause-end"]')->length)->toBe(25)
        ->and($html)->toContain('.clause-end { white-space: nowrap; }');
    foreach ($clauses as $paragraph) {
        if ($paragraph->firstChild->textContent === 'SEXTA:') {
            expect($xpath->query('.//span[@class="clause-end"]', $paragraph)->length)->toBe(0);
            $paragraph = $xpath->query('following-sibling::p[1]', $paragraph)->item(0);
            expect($paragraph->textContent)->toContain('entregará el correspondiente comprobante de pago');
        }
        expect(trim($paragraph->textContent))->toEndWith('.');
    }
    expect($html)->not->toContain('.--------');
});

it('fills only the available space on the final PDF line without introducing a new line', function (string $text, int $hyphens, int $width): void {
    $html = '<style>@page { margin: 30pt; } body { margin: 0; font: 10pt Courier; } p { margin: 0; width: '.$width.'pt; }</style>'
        .'<p>'.$text.'<span class="clause-end">.</span></p>';
    $pdf = app(GeneratedAgreementService::class)->pdf($html);
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);
    $content = implode("\n", array_map(fn (string $stream): string => @gzuncompress($stream) ?: $stream, $streams[1]));
    preg_match_all('/\((-+)\)\] TJ/', $content, $leaders);

    expect($leaders[1])->toBe($hyphens === 0 ? [] : [str_repeat('-', $hyphens)]);
    if ($hyphens > 0) {
        preg_match('/([\d.]+) ([\d.]+) Td[^\n]*\(\.\)\] TJ/', $content, $period);
        preg_match('/([\d.]+) ([\d.]+) Td[^\n]*\(-+\)\] TJ/', $content, $leader);
        expect($leader[2])->toBe($period[2])
            ->and((float) $leader[1] + $hyphens * 6)->toBeLessThanOrEqual(30 + $width + 0.01)
            ->toBeGreaterThan(30 + $width - 6);
    }
})->with([
    'short final line' => ['Short', 10, 100],
    'line almost full' => [str_repeat('x', 15), 0, 100],
    'line exactly full' => [str_repeat('x', 15), 0, 96],
    'bold text' => ['<strong>Short</strong>', 10, 100],
    'wrapped paragraph' => ['word word word Short', 10, 100],
]);

it('includes both institute notification emails only in clause eighteen and keeps the existing contact channels', function (): void {
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    $notifications = Str::between($html, '<strong>DÉCIMA OCTAVA:</strong>', '<strong>DÉCIMA NOVENA:</strong>');

    expect($notifications)->toContain(
        'a <strong>EL INSTITUTO</strong>',
        'Oficina de Administración',
        'consultoriajuridica.iammch@gmail.com',
        'contabilidad.iammch@gmail.com',
        $this->person->email,
        '<strong>Local CONV-01</strong>',
    )->and(substr_count($html, 'consultoriajuridica.iammch@gmail.com'))->toBe(1)
        ->and(substr_count($html, 'contabilidad.iammch@gmail.com'))->toBe(1);
});

it('corrects clause punctuation and common expenses without changing the euro usage rate or other obligations', function (): void {
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    $sixth = Str::between($html, '<strong>SEXTA:</strong>', '<strong>SÉPTIMA:</strong>');
    $fifth = Str::between($html, '<strong>QUINTA:</strong>', '<strong>SEXTA:</strong>');

    expect($html)->toContain(
        'realiza la cesión de derechos de uso, libre',
        'Ordenanza <strong>sobre</strong> la Organización',
        'pago de servicios públicos y de seguridad',
        'normas sanitarias, de seguridad integral',
        'instalaciones de agua',
    )->and($fifth)->toContain('cero coma diez (0,10)', 'Banco Central de Venezuela (euros)')
        ->not->toContain('USD', 'dólares')
        ->and($sixth)->toContain(
            'expresado en dólares estadounidenses (USD $)',
            'su pago se realizará en bolívares por el monto equivalente, calculado según el tipo de cambio del dólar estadounidense publicado en la página oficial del Banco Central de Venezuela para el día del pago efectivo',
            'COBRO PB (USD $)', 'COBRO MZZ (USD $)', 'COBRO COMERCIO (USD $)', 'PLAZA (USD $)',
            'energía eléctrica', 'publicidad', 'mantenimiento de áreas comunes',
        )->not->toContain('agua potable', 'vigilancia', 'gastos de administración');
});

it('reprints original bytes and searches historical data after profiles change', function (): void {
    $agreement = app(GeneratedAgreementService::class)->issue(reviewedAgreementData(), $this->user->id);
    $pdf = Storage::disk('local')->get($agreement->pdf_path);
    $this->person->update(['full_name' => 'Nombre actualizado', 'email' => 'nuevo@example.test']);
    $this->local->update(['code' => 'NUEVO', 'area_m2' => 99]);
    $this->actingAs($this->user)->get(route('agreements.download', $agreement))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertStreamedContent($pdf);
    $this->get(route('agreements.index', ['q' => 'María']))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('agreements.data', 1)->where('agreements.data.0.name', 'María Pérez')->where('agreements.data.0.local', 'CONV-01'));
    expect($agreement->fresh()->snapshot['concessionaire']['email'])->toBe('maria@example.test');
});

it('does not consume another number for a repeated submission', function (): void {
    $data = reviewedAgreementData();
    $this->actingAs($this->user)->post(route('agreements.store'), $data)->assertRedirect();
    $this->post(route('agreements.store'), $data)->assertRedirect();
    expect(GeneratedAgreement::query()->count())->toBe(1)->and(AgreementSequence::query()->sole()->next_number)->toBe(2);
});

it('numbers sequentially and restarts each issuance year', function (): void {
    $service = app(GeneratedAgreementService::class);
    expect($service->issue(reviewedAgreementData(), $this->user->id)->number)->toBe('0001/2026');
    $this->data['issuance_token'] = (string) Str::uuid();
    expect($service->issue(reviewedAgreementData(), $this->user->id)->number)->toBe('0002/2026');
    Carbon::setTestNow('2027-01-01 10:00:00');
    $this->data['issuance_token'] = (string) Str::uuid();
    expect($service->issue(reviewedAgreementData(), $this->user->id)->number)->toBe('0001/2027');
});

it('requires a reviewed preview and rejects changes since preview', function (): void {
    $this->actingAs($this->user)->post(route('agreements.store'), $this->data)->assertSessionHasErrors('preview_hash');
    $data = reviewedAgreementData();
    $this->person->update(['full_name' => 'Datos cambiados']);
    $this->post(route('agreements.store'), $data)->assertSessionHasErrors('preview_hash');
    expect(GeneratedAgreement::query()->count())->toBe(0)->and(AgreementSequence::query()->count())->toBe(0);
});

it('requires profile completion before preview', function (string $field, mixed $value): void {
    $this->person->update([$field => $value]);
    $this->actingAs($this->user)->from(route('agreements.index'))->get(route('agreements.preview', $this->data))->assertSessionHasErrors('concessionaire_id');
})->with([
    'sexo' => ['sex', null], 'estado civil' => ['marital_status', null],
    'teléfono' => ['phone_number', null], 'código de área' => ['phone_area_code_id', null],
    'correo' => ['email', ''], 'dirección' => ['fiscal_address', ''], 'inactivo' => ['is_active', false],
]);

it('identifies only the missing profile fields when rejecting a preview', function (): void {
    $this->person->update(['sex' => null, 'marital_status' => null]);
    $this->actingAs($this->user)->from(route('agreements.index'))->get(route('agreements.preview', $this->data))
        ->assertRedirect(route('agreements.index'))
        ->assertSessionHasErrors(['concessionaire_id' => 'María Pérez: completa sexo, estado civil en su perfil antes de generar el convenio.']);
    expect(GeneratedAgreement::query()->count())->toBe(0)->and(AgreementSequence::query()->count())->toBe(0);
});

it('validates selected catalogs and dates', function (string $field, mixed $value): void {
    $this->data[$field] = $value;
    $this->actingAs($this->user)->from(route('agreements.index'))->get(route('agreements.preview', $this->data))->assertSessionHasErrors($field);
})->with([
    ['concessionaire_id', 999999], ['local_id', 999999], ['trade_category_id', 999999],
    ['start_date', 'invalid'], ['signature_date', 'invalid'], ['signature_date', '2026-02-30'], ['issuance_token', 'invalid'],
]);

it('preserves the independent start date and derives expiry only from signature', function (string $signature, string $end): void {
    $this->data['signature_date'] = $signature;
    $start = Carbon::parse($signature)->subMonthNoOverflow()->toDateString();
    $this->data['start_date'] = $start;
    $this->data['end_date'] = '1900-01-01';
    $service = app(GeneratedAgreementService::class);
    $snapshot = $service->snapshot($this->data);
    expect($snapshot['start_date'])->toBe($start)->and($snapshot['end_date'])->toBe($end);
    $this->actingAs($this->user)->post(route('agreements.store'), reviewedAgreementData())->assertSessionHasNoErrors()->assertRedirect();
    $agreement = GeneratedAgreement::query()->sole();
    expect($agreement->start_date->toDateString())->toBe($start)
        ->and($agreement->snapshot['end_date'])->toBe($end)
        ->and($agreement->rendered_html)->toContain('contados a partir de la firma del presente convenio', Carbon::parse($start)->format('d/m/Y'));
})->with([
    ['2026-11-20', '2046-11-20'],
    ['2028-02-29', '2048-02-29'],
    ['2080-02-29', '2100-02-28'],
]);

it('provides today in the application timezone for the editable signature default', function (): void {
    config(['app.timezone' => 'America/Caracas']);
    Carbon::setTestNow('2026-11-18 01:00:00 UTC');
    $this->actingAs($this->user)->get(route('agreements.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('today', '2026-11-17'));
});

it('preserves the cesionario denomination and repeats the president identification under his signature', function (): void {
    $this->person->update(['sex' => 'M']);
    $service = app(GeneratedAgreementService::class);
    $html = $service->html($service->snapshot($this->data));
    $signatures = substr($html, strpos($html, '<table class="signatures">'));
    expect($html)->toContain('de <strong>EL CESIONARIO</strong>', 'intuitu personae', 'height: 42mm; width: auto;', 'min-height: 44mm;')
        ->not->toContain('DEL CESIONARIO', 'intuido');
    expect($signatures)->toContain('<strong>ALBERTO JOSÉ GONZÁLEZ<br>V-9.481.644</strong>', '<strong>María Pérez<br>V-12.345.678</strong>')
        ->not->toContain('Cédula N.º', 'RIF:', 'Presidente del Instituto');
});

it('rejects inactive deleted or incompatible selections', function (string $case): void {
    $field = match ($case) {
        'local', 'deleted_local' => 'local_id', 'trade' => 'trade_category_id', default => 'concessionaire_id',
    };
    match ($case) {
        'local' => $this->local->update(['is_active' => false]),
        'deleted_local' => $this->local->delete(),
        'trade' => TradeCategory::query()->findOrFail($this->data['trade_category_id'])->update(['is_active' => false]),
        'company' => $this->person->update(['concessionaire_type_id' => ConcessionaireType::query()->where('code', 'PJUR')->value('id')]),
        'passport' => $this->person->update(['document_type_id' => DocumentType::query()->where('code', 'P')->value('id')]),
    };
    $this->actingAs($this->user)->from(route('agreements.index'))->get(route('agreements.preview', $this->data))->assertSessionHasErrors($field);
})->with(['local', 'deleted_local', 'trade', 'company', 'passport']);

it('builds male and foreign concordances from the profile', function (): void {
    $this->person->update(['sex' => 'M', 'marital_status' => 'married', 'document_type_id' => DocumentType::query()->where('code', 'E')->value('id')]);
    $snapshot = app(GeneratedAgreementService::class)->snapshot($this->data);
    expect($snapshot['concessionaire'])->toMatchArray(['citizen' => 'el ciudadano', 'nationality' => 'extranjero', 'party' => 'EL CESIONARIO', 'identified' => 'identificado', 'marital_status_label' => 'casado']);
});

it('rolls back numbering and history when pdf rendering fails', function (): void {
    $data = reviewedAgreementData();
    $service = Mockery::mock(GeneratedAgreementService::class, [app(App\Support\PdfAssetLoader::class)])->makePartial();
    $service->shouldReceive('pdf')->once()->andThrow(new RuntimeException('PDF failed'));
    expect(fn () => $service->issue($data, $this->user->id))->toThrow(RuntimeException::class, 'PDF failed');
    expect(GeneratedAgreement::query()->count())->toBe(0)->and(AgreementSequence::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('generated-agreements'))->toBe([]);
});

it('does not allow updating or deleting issued agreements', function (): void {
    $agreement = app(GeneratedAgreementService::class)->issue(reviewedAgreementData(), $this->user->id);
    expect(fn () => $agreement->update(['number' => 'altered']))->toThrow(LogicException::class);
    expect(fn () => $agreement->fresh()->delete())->toThrow(LogicException::class);
});

it('persists and validates personal fields in the existing profile forms', function (): void {
    $this->user->givePermissionTo(['catalogs.concessionaire.update', 'catalogs.concessionaire.view']);
    $payload = $this->person->only(['concessionaire_type_id', 'full_name', 'document_type_id', 'document_number', 'fiscal_address', 'email', 'phone_area_code_id', 'phone_number', 'is_active']);
    $payload['sex'] = 'M';
    $payload['marital_status'] = 'widowed';
    $this->actingAs($this->user)->put(route('catalogs.concessionaire.update', $this->person), $payload)->assertSessionHasNoErrors();
    expect($this->person->fresh())->sex->toBe('M')->marital_status->toBe('widowed');
    $this->get(route('catalogs.concessionaire.edit', $this->person))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('model.sex', 'M')->where('model.marital_status', 'widowed'));
    $payload['sex'] = 'invalid';
    $payload['marital_status'] = 'invalid';
    $this->put(route('catalogs.concessionaire.update', $this->person), $payload)->assertSessionHasErrors(['sex', 'marital_status']);
});
