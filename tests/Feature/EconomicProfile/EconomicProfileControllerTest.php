<?php

declare(strict_types=1);

use App\Contracts\Services\EconomicProfileServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

function mkAdminUserForEco(bool $withView = true, bool $withExport = false): \App\Models\User
{
    test()->seed([
        Database\Seeders\PermissionsSeeder::class,
    ]);

    $u = \App\Models\User::create([
        'name' => 'Admin Eco',
        'email' => 'admin-eco+'.uniqid().'@mailinator.com',
        'password' => bcrypt('secret1234'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    if ($withView) {
        try {
            $u->givePermissionTo('admin.economic_profile.view');
        } catch (Throwable $e) {
        }
    }
    if ($withExport) {
        try {
            $u->givePermissionTo('admin.economic_profile.export');
        } catch (Throwable $e) {
        }
    }

    return $u;
}

it('index requires view permission', function () {
    $u = mkAdminUserForEco(withView: false);
    $this->actingAs($u);
    $this->get(route('economic_profile.index'))->assertStatus(403);

    $u2 = mkAdminUserForEco(withView: true);
    $this->actingAs($u2);
    $this->followingRedirects()->get(route('economic_profile.index'))->assertOk();
});

it('search returns JSON items for concessionaires and locals', function () {
    $u = mkAdminUserForEco(withView: true);
    $this->actingAs($u);

    $this->mock(EconomicProfileServiceInterface::class, function ($m) {
        $m->shouldReceive('searchConcessionaires')->andReturn([
            ['id' => 1, 'name' => 'Concesionario X'],
        ]);
        $m->shouldReceive('searchLocals')->andReturn([
            ['id' => 2, 'code' => 'L-01'],
        ]);
    });

    $r1 = $this->getJson(route('economic_profile.search', ['type' => 'concessionaire', 'q' => 'x', 'limit' => 5]));
    $r1->assertOk();
    expect($r1->json('items.0.id'))->toBe(1);

    $r2 = $this->getJson(route('economic_profile.search', ['type' => 'local', 'q' => 'L', 'limit' => 5]));
    $r2->assertOk();
    expect($r2->json('items.0.id'))->toBe(2);
});

it('showConcessionaire renders page with data from service', function () {
    $u = mkAdminUserForEco(withView: true);
    $this->actingAs($u);

    $this->mock(EconomicProfileServiceInterface::class, function ($m) {
        $m->shouldReceive('forConcessionaire')->andReturn([
            'summary' => ['total_debt_bs_minor' => 100],
            'items' => [],
        ]);
    });

    $res = $this->get(route('economic_profile.concessionaire', ['id' => 10, 'at' => now()->toDateString()]));
    $res->assertOk();
});

it('showLocal renders page with data from service', function () {
    $u = mkAdminUserForEco(withView: true);
    $this->actingAs($u);

    $this->mock(EconomicProfileServiceInterface::class, function ($m) {
        $m->shouldReceive('forLocal')->andReturn([
            'summary' => ['total_debt_bs_minor' => 50],
            'items' => [],
        ]);
    });

    $res = $this->get(route('economic_profile.local', ['id' => 20, 'at' => now()->toDateString()]));
    $res->assertOk();
});

it('showLocal exposes collectibility permission and open charge ids for profile actions', function () {
    $u = mkAdminUserForEco(withView: true);
    $u->givePermissionTo('charges.collectibility.mark');
    $this->actingAs($u);

    $this->mock(EconomicProfileServiceInterface::class, function ($m) {
        $m->shouldReceive('forLocal')->once()->andReturn([
            'header' => ['id' => 20, 'code' => 'L-20', 'name' => 'Local 20'],
            'summary_bs' => [
                'open_bs_minor' => 10000,
                'overdue_bs_minor' => 10000,
                'payments_available_bs_minor' => 0,
                'credits_open_bs_minor' => 0,
                'net_due_after_credit_bs_minor' => 10000,
            ],
            'summary_fx' => [],
            'by_local' => [],
            'tables' => [
                'charges_open' => [[
                    'charge_id' => 123,
                    'period' => '2026-07-01',
                    'due_on' => '2026-07-10',
                    'currency' => 'VES',
                    'amount_bs_minor' => 10000,
                    'allocated_bs_minor' => 0,
                    'credited_bs_minor' => 0,
                    'outstanding_bs_minor' => 10000,
                    'outstanding_minor' => 10000,
                    'kind' => 'RENT_EUR_FIXED',
                ]],
                'payments_partial' => [],
                'credits_open' => [],
            ],
        ]);
        $m->shouldReceive('getReconciliation')->once()->andReturn([
            'summary_bs' => [
                'gross_debt_bs_minor' => 10000,
                'credits_open_bs_minor' => 0,
                'payments_available_bs_minor' => 0,
                'eligible_payments_available_bs_minor' => 0,
                'final_due_bs_minor' => 10000,
            ],
        ]);
    });

    $this->get(route('economic_profile.local', ['id' => 20, 'at' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/economic-profile/local-ultra')
            ->where('auth.can', fn ($can): bool => (bool) $can->get('charges.collectibility.mark') === true)
            ->where('tables.charges_open.0.charge_id', 123)
        );
});

it('export streams file when authorized', function () {
    $u = mkAdminUserForEco(withView: true, withExport: true);
    $this->actingAs($u);

    $resp = new StreamedResponse(function () {
        echo "id,name\n1,Test\n";
    }, 200, [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="eco.csv"',
    ]);

    $this->mock(EconomicProfileServiceInterface::class, function ($m) use ($resp) {
        $m->shouldReceive('export')->andReturn($resp);
    });

    $r = $this->get(route('economic_profile.export', [
        'scope' => 'concessionaire', 'id' => 1, 'format' => 'csv', 'at' => now()->toDateString(),
    ]));
    $r->assertOk();
    expect((string) $r->headers->get('Content-Type'))->toStartWith('text/csv');
});

it('statement returns application/pdf and forwards selected local_ids for concessionaire scope', function () {
    $u = mkAdminUserForEco(withView: true, withExport: true);
    $this->actingAs($u);

    $selected = [11, 22];

    $this->mock(EconomicProfileServiceInterface::class, function ($m) use ($selected) {
        $m->shouldReceive('getReconciliation')
            ->once()
            ->withArgs(function ($scope, $id, $at, $filters) use ($selected) {
                expect($scope)->toBe('CONCESSIONAIRE');
                expect($id)->toBe(10);
                expect(is_array($filters))->toBeTrue();
                $ids = array_map('intval', is_array($filters['local_ids'] ?? null) ? $filters['local_ids'] : []);
                expect($ids)->toBe($selected);

                return true;
            })
            ->andReturn([
                'summary_bs' => ['open_bs_minor' => 100, 'overdue_bs_minor' => 0, 'credits_open_bs_minor' => 0, 'net_due_after_credit_bs_minor' => 100],
                'profile' => [
                    'header' => ['id' => 10, 'full_name' => 'X'],
                    'summary_bs' => ['open_bs_minor' => 100, 'overdue_bs_minor' => 0, 'credits_open_bs_minor' => 0, 'net_due_after_credit_bs_minor' => 100],
                    'by_local' => [],
                    'tables' => ['charges_open' => []],
                ],
            ]);
    });

    $this->mock(\App\Services\EconomicProfileStatementPdfGenerator::class, function ($m) use ($selected) {
        $m->shouldReceive('render')
            ->once()
            ->withArgs(function ($eco, $scope, $id, $at, $localIds) use ($selected) {
                expect($scope)->toBe('concessionaire');
                expect($id)->toBe(10);
                $ids = array_map('intval', is_array($localIds) ? $localIds : []);
                expect($ids)->toBe($selected);

                return true;
            })
            ->andReturn([
                'raw' => "%PDF-1.4\n%",
                'filename' => 'estado_cuenta_CONCESSIONAIRE_10_20260126.pdf',
            ]);
    });

    $res = $this->get(route('economic_profile.statement', [
        'scope' => 'concessionaire',
        'id' => 10,
        'at' => now()->toDateString(),
        'local_ids' => $selected,
    ]));

    $res->assertOk();
    $res->assertHeader('Content-Type', 'application/pdf');
    expect(substr((string) $res->getContent(), 0, 4))->toBe('%PDF');
});
