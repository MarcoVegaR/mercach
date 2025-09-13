<?php

declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\DTO\ListQuery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepositoryInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = app(UserRepositoryInterface::class);
    }

    public function test_default_sort_is_id_desc(): void
    {
        $u1 = User::factory()->create(['name' => 'A']);
        $u2 = User::factory()->create(['name' => 'B']);

        $out = $this->repo->list(new ListQuery(perPage: 10));
        $ids = array_map(fn ($row) => $row['id'], $out->items());

        $this->assertEquals([$u2->id, $u1->id], $ids);
    }

    public function test_filter_by_name_like(): void
    {
        User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob']);

        $q = new ListQuery(filters: ['name' => 'ali']);
        $out = $this->repo->list($q);
        $this->assertCount(1, $out->items());
        $this->assertEquals('Alice', $out->items()[0]['name']);
    }

    public function test_filter_by_email_like(): void
    {
        User::factory()->create(['email' => 'john@example.com']);
        User::factory()->create(['email' => 'mary@domain.test']);

        $q = new ListQuery(filters: ['email' => 'example']);
        $out = $this->repo->list($q);
        $this->assertCount(1, $out->items());
        $this->assertEquals('john@example.com', $out->items()[0]['email']);
    }

    public function test_filter_by_role_id(): void
    {
        $role = SpatieRole::create(['name' => 'manager', 'guard_name' => 'web']);
        $uWith = User::factory()->create();
        $uWith->assignRole($role);
        $uWithout = User::factory()->create();

        $q = new ListQuery(filters: ['role_id' => $role->id]);
        $out = $this->repo->list($q);

        $ids = array_map(fn ($row) => $row['id'], $out->items());
        $this->assertContains($uWith->id, $ids);
        $this->assertNotContains($uWithout->id, $ids);
    }

    public function test_filter_by_is_active(): void
    {
        // Defaults to true
        $active = User::factory()->create();
        // Manually set as inactive
        $inactive = User::factory()->create();
        \DB::table('users')->where('id', $inactive->id)->update(['is_active' => false]);

        $q = new ListQuery(filters: ['is_active' => true]);
        $out = $this->repo->list($q);
        $ids = array_map(fn ($row) => $row['id'], $out->items());
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_filter_by_created_between(): void
    {
        $old = User::factory()->create(['created_at' => now()->subDays(10)]);
        $new = User::factory()->create(['created_at' => now()]);

        $q = new ListQuery(filters: ['created_between' => ['from' => now()->subDays(5)->toDateString()]]);
        $out = $this->repo->list($q);

        $ids = array_map(fn ($row) => $row['id'], $out->items());
        $this->assertContains($new->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }
}
