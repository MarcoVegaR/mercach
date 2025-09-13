<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class UserShowTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        // permissions
        foreach (['users.view', 'users.update', 'users.delete'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = SpatieRole::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(['users.view']);

        $this->viewer = User::factory()->create();
        $this->viewer->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_show_requires_view_permission(): void
    {
        $u = User::factory()->create();
        $resp = $this->actingAs(User::factory()->create())->get(route('users.show', $u));
        $resp->assertForbidden();
    }

    public function test_show_returns_item_and_meta_with_iso_dates(): void
    {
        $u = User::factory()->create();
        $resp = $this->actingAs($this->viewer)->get(route('users.show', $u));

        $resp->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('users/show')
            ->has('item', fn (Assert $item) => $item
                ->where('id', $u->id)
                ->where('name', $u->name)
                ->where('email', $u->email)
                ->where('is_active', true)
                ->has('created_at')
                ->has('updated_at')
            )
            ->has('meta')
        );
    }

    public function test_show_with_relations_and_counts_via_whitelist(): void
    {
        $role = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $u = User::factory()->create();
        $u->assignRole($role);

        $resp = $this->actingAs($this->viewer)
            ->get(route('users.show', [$u, 'with' => ['roles'], 'withCount' => ['roles']]));

        $resp->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('item.roles', 1)
            ->has('item', fn (Assert $item) => $item->where('roles_count', 1)->etc())
            ->where('meta.loaded_relations', fn ($relations) => in_array('roles', $relations->toArray()))
            ->where('meta.loaded_counts', fn ($counts) => in_array('roles_count', $counts->toArray()))
        );
    }

    public function test_show_404_when_not_found(): void
    {
        $resp = $this->actingAs($this->viewer)->get('/users/999999');
        $resp->assertNotFound();
    }
}
