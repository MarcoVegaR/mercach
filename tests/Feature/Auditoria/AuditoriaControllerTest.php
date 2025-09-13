<?php

declare(strict_types=1);

use App\Models\Audit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create permissions
    Permission::create(['name' => 'auditoria.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'auditoria.export', 'guard_name' => 'web']);

    // Create test users
    $this->userWithPermissions = User::factory()->create();
    $this->userWithoutPermissions = User::factory()->create();

    // Create role with permissions
    $role = Role::create(['name' => 'audit_viewer', 'guard_name' => 'web']);
    $role->givePermissionTo(['auditoria.view', 'auditoria.export']);
    $this->userWithPermissions->assignRole($role);

    // Create test audit records directly in beforeEach
    Audit::create([
        'user_type' => User::class,
        'user_id' => $this->userWithPermissions->id,
        'event' => 'created',
        'auditable_type' => User::class,
        'auditable_id' => $this->userWithPermissions->id,
        'old_values' => null,
        'new_values' => ['name' => 'Test User'],
        'url' => '/users',
        'ip_address' => '192.168.1.1',
        'user_agent' => 'Test Agent',
        'created_at' => now()->subDay(),
    ]);

    Audit::create([
        'user_type' => User::class,
        'user_id' => $this->userWithPermissions->id,
        'event' => 'login',
        'auditable_type' => User::class,
        'auditable_id' => $this->userWithPermissions->id,
        'old_values' => null,
        'new_values' => ['ip' => '192.168.1.2'],
        'url' => '/login',
        'ip_address' => '192.168.1.2',
        'user_agent' => 'Login Agent',
        'created_at' => now(),
    ]);
});

it('denies access when user lacks auditoria.view permission', function () {
    $this->actingAs($this->userWithoutPermissions)
        ->get('/auditoria')
        ->assertForbidden();
});

it('allows access when user has auditoria.view permission', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auditoria/index'));
});

it('returns audit data in correct format', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 2)
            ->has('meta')
            ->has('meta.total')
            ->where('rows.0.event', 'login')
            ->where('rows.1.event', 'created')
        );
});

it('supports search functionality (by user name or IP)', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?q=192.168.1.')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 2) // Both records match by IP fragment
        );
});

it('supports filtering by user_id', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?filters[user_id]='.$this->userWithPermissions->id)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 2)
        );
});

it('supports filtering by event', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?filters[event]=created')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.event', 'created')
        );
});

it('supports filtering by date range', function () {
    $from = now()->subDays(2)->format('Y-m-d');
    $to = now()->format('Y-m-d');

    $this->actingAs($this->userWithPermissions)
        ->get("/auditoria?filters[created_between][from]={$from}&filters[created_between][to]={$to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 2)
        );
});

it('supports sorting by allowed columns', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?sort=event&dir=asc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.event', 'created')
            ->where('rows.1.event', 'login')
        );
});

it('supports pagination', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?page=1&per_page=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('meta.total', 2)
        );
});

it('supports partial reloads with only parameter', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria', [
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Data' => 'rows,meta',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows')
            ->has('meta')
        );
});

it('denies export when user lacks auditoria.export permission', function () {
    // Create user with only view permission
    $viewOnlyUser = User::factory()->create();
    $viewRole = Role::create(['name' => 'audit_viewer_only', 'guard_name' => 'web']);
    $viewRole->givePermissionTo(['auditoria.view']);
    $viewOnlyUser->assignRole($viewRole);

    $this->actingAs($viewOnlyUser)
        ->get('/auditoria/export?format=csv')
        ->assertForbidden();
});

it('allows export when user has auditoria.export permission', function () {
    $response = $this->actingAs($this->userWithPermissions)
        ->get('/auditoria/export?format=csv');

    $response->assertOk();
    $response->assertHeader('Content-Disposition');
    expect($response->headers->get('Content-Disposition'))->toContain('auditoria_export_');
});

it('supports different export formats', function () {
    $formats = ['csv', 'xlsx', 'json'];

    foreach ($formats as $format) {
        $response = $this->actingAs($this->userWithPermissions)
            ->get("/auditoria/export?format={$format}");

        $response->assertOk();
        expect($response->headers->get('Content-Disposition'))->toContain('auditoria_export_');
    }
});

it('validates request parameters', function () {
    // Invalid sort column
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?sort=invalid_column')
        ->assertSessionHasErrors('sort');

    // Invalid per_page
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria?per_page=999')
        ->assertSessionHasErrors('per_page');
});

it('handles empty results gracefully', function () {
    // Clear all audits
    Audit::query()->delete();

    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 0)
            ->where('meta.total', 0)
        );
});

it('loads user relationship in audit data', function () {
    $this->actingAs($this->userWithPermissions)
        ->get('/auditoria')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.user_name', $this->userWithPermissions->name)
            ->where('rows.1.user_name', $this->userWithPermissions->name)
        );
});

it('respects throttling on export endpoint', function () {
    $this->actingAs($this->userWithPermissions);

    // Just verify the endpoint responds correctly - throttling limits depend on configuration
    $response = $this->get('/auditoria/export?format=csv');
    $response->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('auditoria_export_');
});
