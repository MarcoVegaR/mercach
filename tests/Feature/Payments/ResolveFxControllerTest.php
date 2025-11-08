<?php

declare(strict_types=1);

use App\Contracts\Services\FxRateServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns fx_rate_id and rate_to_ves for given currency and date', function () {
    // Seed and login
    $this->seed([Database\Seeders\PermissionsSeeder::class, Database\Seeders\UsersSeeder::class]);
    $admin = \App\Models\User::where('email', 'test@mailinator.com')->first();
    $this->actingAs($admin);

    // Create a fake FxRate row satisfying NOT NULL constraints
    $today = now();
    $rate = \App\Models\FxRate::create([
        'currency_code' => 'USD',
        'rate_date' => $today->toDateString(),
        'value_date' => $today->toDateString(),
        'published_at' => $today,
        'rate_to_ves' => 36.5,
        'operational_from' => $today,
        'operational_to' => $today->copy()->addDay(),
        'source' => 'TEST',
        'is_official' => true,
        'is_active' => true,
    ]);

    // Mock only the method used by the controller
    $this->mock(FxRateServiceInterface::class, function ($m) use ($rate) {
        $m->shouldReceive('resolveAt')->andReturn($rate);
    });

    $res = $this->getJson(route('payments.resolve-fx', ['currency' => 'USD', 'paid_on' => now()->toDateString()]));
    $res->assertOk();
    $json = $res->json();
    expect($json['fx_rate_id'] ?? null)->toBe($rate->getKey());
    expect((float) ($json['rate_to_ves'] ?? 0))->toBe(36.5);
});
