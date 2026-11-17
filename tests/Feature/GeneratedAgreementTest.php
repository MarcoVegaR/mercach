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
});

it('requires authentication', function (): void {
    $this->get(route('agreements.index'))->assertRedirect(route('login'));
});

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
        ->and($html)->toContain('LA CESIONARIA', '12,50 m²', 'cincuenta centésimas', 'VIGÉSIMA QUINTA:', 'COBRO PB ($)', 'G-20009256-4', '20/11/2026', '20/11/2046', 'del año 2026')
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
    expect($html)->toContain('DE EL CESIONARIO', 'intuitu personae', 'height: 42mm; width: auto;', 'min-height: 44mm;')
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
