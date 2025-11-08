<?php

declare(strict_types=1);

use App\Contracts\Services\EconomicProfileServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
