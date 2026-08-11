<?php

declare(strict_types=1);

use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\DocumentType;
use App\Models\LifeProofSequence;
use App\Models\Local;
use App\Models\PhoneAreaCode;
use App\Models\User;
use App\Services\ConcessionaireLifeProofFormPdfGenerator;
use App\Services\ConcessionaireProfilePdfGenerator;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    $this->seed(PermissionsSeeder::class);
});

function lifeProofUser(bool $admin = true): User
{
    $user = User::factory()->create();
    if ($admin) {
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());
    }

    return $user;
}

function lifeProofConcessionaire(array $attributes = []): Concessionaire
{
    $type = ConcessionaireType::query()->firstOrCreate(
        ['code' => 'PNAT'],
        ['name' => 'Persona Natural', 'is_active' => true],
    );
    $documentType = DocumentType::query()->firstOrCreate(
        ['code' => 'V'],
        ['name' => 'Cédula', 'mask' => '########', 'is_active' => true],
    );
    $areaCode = PhoneAreaCode::query()->firstOrCreate(
        ['code' => '0414'],
        ['is_active' => true],
    );

    return Concessionaire::query()->create(array_merge([
        'concessionaire_type_id' => $type->getKey(),
        'full_name' => 'Cesionario de Prueba',
        'document_type_id' => $documentType->getKey(),
        'document_number' => (string) fake()->unique()->numberBetween(10000000, 29999999),
        'fiscal_address' => 'Dirección fiscal de prueba',
        'email' => fake()->unique()->safeEmail(),
        'phone_area_code_id' => $areaCode->getKey(),
        'phone_number' => '1234567',
        'is_active' => true,
    ], $attributes));
}

it('registers the latest life proof date', function () {
    $user = lifeProofUser();
    $concessionaire = lifeProofConcessionaire();

    $response = $this->actingAs($user)->post(route('catalogs.concessionaire.life-proof', $concessionaire), [
        'life_proof_at' => '2026-08-10',
    ]);

    $response->assertRedirect(route('catalogs.concessionaire.show', $concessionaire));
    expect($concessionaire->refresh()->last_life_proof_at?->toDateString())->toBe('2026-08-10');
});

it('rejects future life proof dates', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    $user = lifeProofUser();
    $concessionaire = lifeProofConcessionaire();

    $response = $this->actingAs($user)
        ->from(route('catalogs.concessionaire.show', $concessionaire))
        ->post(route('catalogs.concessionaire.life-proof', $concessionaire), [
            'life_proof_at' => '2026-08-11',
        ]);

    $response->assertRedirect(route('catalogs.concessionaire.show', $concessionaire));
    $response->assertSessionHasErrors('life_proof_at');
    expect($concessionaire->refresh()->last_life_proof_at)->toBeNull();
});

it('requires update permission to register life proof', function () {
    $user = lifeProofUser(false);
    $user->givePermissionTo(Permission::query()->where('name', 'catalogs.concessionaire.view')->firstOrFail());
    $concessionaire = lifeProofConcessionaire();

    $this->actingAs($user)
        ->post(route('catalogs.concessionaire.life-proof', $concessionaire), ['life_proof_at' => '2026-08-10'])
        ->assertForbidden();
});

it('starts the form correlative at 000100 and printing does not register life proof', function () {
    $concessionaire = lifeProofConcessionaire();
    $concessionaires = Concessionaire::query()
        ->with(['concessionaireType', 'documentType', 'phoneAreaCode', 'contracts.status', 'contracts.locals'])
        ->whereKey($concessionaire->getKey())
        ->get();

    $generated = app(ConcessionaireLifeProofFormPdfGenerator::class)->render($concessionaires);

    expect($generated['filename'])->toContain('000100_000100')
        ->and(substr($generated['raw'], 0, 4))->toBe('%PDF')
        ->and(LifeProofSequence::query()->where('key', 'concessionaire-form')->value('next_number'))->toBe(101)
        ->and($concessionaire->refresh()->last_life_proof_at)->toBeNull();
});

it('prints selected forms with view permission', function () {
    $user = lifeProofUser(false);
    $user->givePermissionTo(Permission::query()->where('name', 'catalogs.concessionaire.view')->firstOrFail());
    $concessionaire = lifeProofConcessionaire();

    $this->mock(ConcessionaireLifeProofFormPdfGenerator::class, function ($mock) use ($concessionaire): void {
        $mock->shouldReceive('render')
            ->once()
            ->withArgs(fn (Collection $items): bool => $items->pluck('id')->all() === [$concessionaire->getKey()])
            ->andReturn(['raw' => "%PDF-1.4\n%", 'filename' => 'planilla.pdf']);
    });

    $response = $this->actingAs($user)->post(route('catalogs.concessionaire.life-proof-forms'), [
        'ids' => [$concessionaire->getKey()],
    ]);

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('prints selected forms through the csrf-free browser print endpoint', function () {
    $user = lifeProofUser(false);
    $user->givePermissionTo(Permission::query()->where('name', 'catalogs.concessionaire.view')->firstOrFail());
    $concessionaire = lifeProofConcessionaire();

    $this->mock(ConcessionaireLifeProofFormPdfGenerator::class, function ($mock): void {
        $mock->shouldReceive('render')->once()->andReturn(['raw' => "%PDF-1.4\n%", 'filename' => 'planilla.pdf']);
    });

    $response = $this->actingAs($user)->get(route('catalogs.concessionaire.life-proof-forms.get', [
        'ids' => [$concessionaire->getKey()],
    ]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('serves the concessionaire profile pdf with view permission', function () {
    $user = lifeProofUser(false);
    $user->givePermissionTo(Permission::query()->where('name', 'catalogs.concessionaire.view')->firstOrFail());
    $concessionaire = lifeProofConcessionaire();

    $this->mock(ConcessionaireProfilePdfGenerator::class, function ($mock) use ($concessionaire): void {
        $mock->shouldReceive('render')
            ->once()
            ->withArgs(fn (Concessionaire $item): bool => $item->is($concessionaire))
            ->andReturn(['raw' => "%PDF-1.4\n%", 'filename' => 'ficha.pdf']);
    });

    $response = $this->actingAs($user)->get(route('catalogs.concessionaire.profile-pdf', $concessionaire));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');
});

it('filters concessionaires that require a life proof citation', function () {
    Carbon::setTestNow('2026-08-10 12:00:00');
    $user = lifeProofUser();
    lifeProofConcessionaire(['full_name' => 'Vigente', 'last_life_proof_at' => '2026-01-15']);
    lifeProofConcessionaire(['full_name' => 'Vencido', 'last_life_proof_at' => '2025-01-15']);
    lifeProofConcessionaire(['full_name' => 'Sin Registro', 'last_life_proof_at' => null]);

    $response = $this->actingAs($user)->get(route('catalogs.concessionaire.index', [
        'filters' => ['life_proof_status' => 'requires_citation'],
    ]));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('catalogs/concessionaire/index')
        ->has('rows', 2)
        ->where('rows.0.life_proof_requires_citation', true)
        ->where('rows.1.life_proof_requires_citation', true)
    );
});

it('renders the registered email and every operational local in the form', function () {
    $concessionaire = new Concessionaire([
        'full_name' => 'María Cesionaria',
        'document_number' => '12345678',
        'email' => 'correo.registrado@example.com',
        'phone_number' => '1234567',
    ]);
    $concessionaire->setRelation('concessionaireType', new ConcessionaireType(['name' => 'Persona Natural']));
    $concessionaire->setRelation('documentType', new DocumentType(['code' => 'V']));
    $concessionaire->setRelation('phoneAreaCode', new PhoneAreaCode(['code' => '0414']));

    $status = new ContractStatus(['code' => 'VIG', 'name' => 'Vigente']);
    $firstLocal = new Local(['code' => 'BM-01', 'name' => 'Local Uno']);
    $firstLocal->setAttribute('id', 1);
    $secondLocal = new Local(['code' => 'BM-99', 'name' => 'Local Noventa y Nueve']);
    $secondLocal->setAttribute('id', 99);
    $contract = new Contract(['number' => 'CTR-001']);
    $contract->setRelation('status', $status);
    $contract->setRelation('locals', new Collection([$firstLocal, $secondLocal]));
    $concessionaire->setRelation('contracts', new Collection([$contract]));

    $html = view('pdf.concessionaire_life_proof_form', [
        'forms' => [['concessionaire' => $concessionaire, 'number' => '000100', 'photo' => ['base64' => null, 'mime' => null]]],
        'printed_at' => Carbon::parse('2026-08-10 10:00:00'),
        'letterhead_base64' => null,
        'letterhead_mime' => null,
        'logo_base64' => null,
        'logo_mime' => null,
    ])->render();

    expect($html)
        ->toContain('Planilla Nro. 000100')
        ->toContain('correo.registrado@example.com')
        ->toContain('BM-01 - Local Uno')
        ->toContain('BM-99 - Local Noventa y Nueve')
        ->toContain('10</strong> días del mes de')
        ->toContain('class="filled-value">María Cesionaria</span>')
        ->not->toContain('id_document_path');
});
