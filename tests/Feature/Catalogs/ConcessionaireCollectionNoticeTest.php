<?php

declare(strict_types=1);

use App\Exceptions\DomainActionException;
use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\DocumentType;
use App\Models\PhoneAreaCode;
use App\Models\User;
use App\Services\ConcessionaireCollectionNoticePdfGenerator;
use App\Services\Reports\DelinquencyReportQuery;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Mockery\MockInterface;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    $this->seed(PermissionsSeeder::class);
});

function collectionNoticeUser(bool $admin = true): User
{
    $user = User::factory()->create();

    if ($admin) {
        $user->assignRole(Role::query()->where('name', 'admin')->firstOrFail());
    }

    return $user;
}

function collectionNoticeConcessionaire(array $attributes = []): Concessionaire
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
        'full_name' => 'María Ferro',
        'document_type_id' => $documentType->getKey(),
        'document_number' => (string) fake()->unique()->numberBetween(10000000, 29999999),
        'fiscal_address' => 'Dirección fiscal de prueba',
        'email' => fake()->unique()->safeEmail(),
        'phone_area_code_id' => $areaCode->getKey(),
        'phone_number' => '1234567',
        'is_active' => true,
    ], $attributes));
}

it('prints the manually selected collection notice type with view permission', function () {
    $user = collectionNoticeUser(false);
    $user->givePermissionTo(Permission::query()->where('name', 'catalogs.concessionaire.view')->firstOrFail());
    $concessionaire = collectionNoticeConcessionaire();

    $this->mock(ConcessionaireCollectionNoticePdfGenerator::class, function (MockInterface $mock) use ($concessionaire): void {
        $mock->shouldReceive('render')
            ->once()
            ->withArgs(fn (Collection $items, string $noticeType): bool => $items->pluck('id')->all() === [$concessionaire->getKey()]
                && $noticeType === 'payment_agreement')
            ->andReturn(['raw' => "%PDF-1.4\n%", 'filename' => 'avisos_cobro_payment_agreement.pdf']);
    });

    $response = $this->actingAs($user)->get(route('catalogs.concessionaire.collection-notices', [
        'ids' => [$concessionaire->getKey()],
        'notice_type' => 'payment_agreement',
    ]));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename="avisos_cobro_payment_agreement.pdf"');
});

it('rejects invalid collection notice types', function () {
    $user = collectionNoticeUser();
    $concessionaire = collectionNoticeConcessionaire();

    $this->actingAs($user)
        ->get(route('catalogs.concessionaire.collection-notices', [
            'ids' => [$concessionaire->getKey()],
            'notice_type' => 'automatic',
        ]))
        ->assertSessionHasErrors('notice_type');
});

it('requires view permission to print collection notices', function () {
    $user = collectionNoticeUser(false);
    $concessionaire = collectionNoticeConcessionaire();

    $this->actingAs($user)
        ->get(route('catalogs.concessionaire.collection-notices', [
            'ids' => [$concessionaire->getKey()],
            'notice_type' => 'ordinary',
        ]))
        ->assertForbidden();
});

it('does not generate notices for concessionaires without overdue indebted locals', function () {
    $user = collectionNoticeUser();
    $concessionaire = collectionNoticeConcessionaire();

    $this->mock(ConcessionaireCollectionNoticePdfGenerator::class, function (MockInterface $mock): void {
        $mock->shouldReceive('render')
            ->once()
            ->andThrow(new DomainActionException('No se generaron los avisos. Cesionario sin deuda.'));
    });

    $this->actingAs($user)
        ->get(route('catalogs.concessionaire.collection-notices', [
            'ids' => [$concessionaire->getKey()],
            'notice_type' => 'ordinary',
        ]))
        ->assertUnprocessable()
        ->assertSeeText('No se generaron los avisos. Cesionario sin deuda.');
});

it('renders only the payment agreement language in that manually selected variant', function () {
    $concessionaire = new Concessionaire([
        'full_name' => 'María Ferro',
        'document_number' => '12345678',
        'phone_number' => '1234567',
    ]);
    $concessionaire->setRelation('documentType', new DocumentType(['code' => 'V']));
    $concessionaire->setRelation('phoneAreaCode', new PhoneAreaCode(['code' => '0414']));
    $viewData = [
        'notices' => [['concessionaire' => $concessionaire, 'local_codes' => ['A-03', 'B-07']]],
        'printed_at' => Carbon::parse('2026-09-27 10:00:00'),
        'letterhead_base64' => null,
        'letterhead_mime' => null,
        'logo_base64' => null,
        'logo_mime' => null,
    ];

    $ordinary = view('pdf.concessionaire_collection_notice', [
        ...$viewData,
        'notice_type' => 'ordinary',
    ])->render();
    $paymentAgreement = view('pdf.concessionaire_collection_notice', [
        ...$viewData,
        'notice_type' => 'payment_agreement',
    ])->render();

    expect($ordinary)
        ->toContain('AVISO DE COBRO')
        ->toContain('Chacao, 27 de septiembre de 2026')
        ->toContain('Puestos: A-03, B-07')
        ->toContain('María Ferro')
        ->toContain('Generado por el sistema el 27/09/2026 10:00.')
        ->not->toContain('compromiso formal que igualmente incumplió')
        ->and($paymentAgreement)
        ->toContain('compromiso formal que igualmente incumplió')
        ->toContain('retomar lo pactado en su convenio de pago');
});

it('generates a real collection notice pdf', function () {
    Carbon::setTestNow('2026-09-27 10:00:00');
    $concessionaire = collectionNoticeConcessionaire();
    $concessionaires = Concessionaire::query()
        ->with(['documentType', 'phoneAreaCode'])
        ->whereKey($concessionaire->getKey())
        ->get();

    $this->mock(DelinquencyReportQuery::class, function (MockInterface $mock) use ($concessionaire): void {
        $mock->shouldReceive('overdueLocalCodesForConcessionaires')
            ->twice()
            ->with([(int) $concessionaire->getKey()])
            ->andReturn([(int) $concessionaire->getKey() => ['A-03']]);
    });

    $generator = app(ConcessionaireCollectionNoticePdfGenerator::class);
    $generated = $generator->render($concessionaires, 'ordinary');
    $paymentAgreement = $generator->render($concessionaires, 'payment_agreement');
    preg_match_all('/\/Type\s*\/Page\b/', $generated['raw'], $ordinaryPages);
    preg_match_all('/\/Type\s*\/Page\b/', $paymentAgreement['raw'], $paymentAgreementPages);

    expect($generated['filename'])->toBe('avisos_cobro_ordinary_20260927.pdf')
        ->and(substr($generated['raw'], 0, 4))->toBe('%PDF')
        ->and(substr($paymentAgreement['raw'], 0, 4))->toBe('%PDF')
        ->and($ordinaryPages[0])->toHaveCount(1)
        ->and($paymentAgreementPages[0])->toHaveCount(1);

    Carbon::setTestNow();
});
