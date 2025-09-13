<?php

use App\Models\User;
use OwenIt\Auditing\Models\Audit;

it('records an audit entry on login', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(302);
    $this->assertAuthenticatedAs($user);

    $audit = Audit::query()
        ->where('event', 'login')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->latest('id')
        ->first();

    expect($audit)->not()->toBeNull();
    expect($audit->new_values)->toBeArray();
    expect(isset($audit->new_values['ip']))->toBeTrue();
});

it('records an audit entry on logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = $this->post('/logout');

    $response->assertStatus(302);
    $this->assertGuest();

    $audit = Audit::query()
        ->where('event', 'logout')
        ->where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->latest('id')
        ->first();

    expect($audit)->not()->toBeNull();
    expect($audit->new_values)->toBeArray();
    expect(isset($audit->new_values['ip']))->toBeTrue();
});
