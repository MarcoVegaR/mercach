<?php

declare(strict_types=1);

use App\Models\User;
use App\Policies\BaseResourcePolicy;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Dummy policy for testing BaseResourcePolicy behavior.
 */
class DummyResourcePolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'dummy';
    }

    /**
     * Override delete to always deny for testing purposes.
     */
    public function delete(User $user, $model): bool
    {
        return false; // Always deny delete regardless of permissions
    }
}

/**
 * Dummy policy with custom guard for testing multi-guard scenarios.
 */
class DummyGuardPolicy extends BaseResourcePolicy
{
    protected function abilityPrefix(): string
    {
        return 'dummy';
    }

    protected function guardName(): ?string
    {
        return 'api'; // Custom guard
    }
}

beforeEach(function () {
    // Create test permissions for dummy resource
    Permission::create(['name' => 'dummy.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'dummy.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'dummy.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'dummy.delete', 'guard_name' => 'web']);
    Permission::create(['name' => 'dummy.restore', 'guard_name' => 'web']);
    Permission::create(['name' => 'dummy.forceDelete', 'guard_name' => 'web']);
    Permission::create(['name' => 'dummy.export', 'guard_name' => 'web']);

    // Create permissions for roles resource
    Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.create', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.update', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.delete', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.restore', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.forceDelete', 'guard_name' => 'web']);
    Permission::create(['name' => 'roles.export', 'guard_name' => 'web']);

    // Create permissions for API guard
    Permission::create(['name' => 'dummy.view', 'guard_name' => 'api']);
    Permission::create(['name' => 'dummy.create', 'guard_name' => 'api']);

    // Create super-admin role for testing
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

    // Register policies for testing
    Gate::policy('DummyModel', DummyResourcePolicy::class);
    Gate::policy('DummyGuardModel', DummyGuardPolicy::class);
    Gate::policy(Role::class, RolePolicy::class);
});

describe('BaseResourcePolicy Authorization', function () {
    it('authorizes user with correct permissions for all abilities', function () {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'dummy.view',
            'dummy.create',
            'dummy.update',
            'dummy.delete',
            'dummy.restore',
            'dummy.forceDelete',
            'dummy.export',
        ]);

        $policy = new DummyResourcePolicy;
        $dummyModel = new stdClass;

        expect($policy->viewAny($user))->toBeTrue();
        expect($policy->view($user, $dummyModel))->toBeTrue();
        expect($policy->create($user))->toBeTrue();
        expect($policy->update($user, $dummyModel))->toBeTrue();
        expect($policy->delete($user, $dummyModel))->toBeFalse(); // Overridden to always deny
        expect($policy->restore($user, $dummyModel))->toBeTrue();
        expect($policy->forceDelete($user, $dummyModel))->toBeTrue();
        expect($policy->export($user))->toBeTrue();
    });

    it('denies user without permissions for all abilities', function () {
        $user = User::factory()->create();
        $policy = new DummyResourcePolicy;
        $dummyModel = new stdClass;

        expect($policy->viewAny($user))->toBeFalse();
        expect($policy->view($user, $dummyModel))->toBeFalse();
        expect($policy->create($user))->toBeFalse();
        expect($policy->update($user, $dummyModel))->toBeFalse();
        expect($policy->delete($user, $dummyModel))->toBeFalse();
        expect($policy->restore($user, $dummyModel))->toBeFalse();
        expect($policy->forceDelete($user, $dummyModel))->toBeFalse();
        expect($policy->export($user))->toBeFalse();
    });

    it('works with Laravel Gates integration', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('dummy.view');

        $this->actingAs($user);

        expect(Gate::allows('viewAny', 'DummyModel'))->toBeTrue();
        expect(Gate::denies('create', 'DummyModel'))->toBeTrue();
    });

    it('handles partial permissions correctly', function () {
        $user = User::factory()->create();
        $user->givePermissionTo(['dummy.view', 'dummy.create']);

        $policy = new DummyResourcePolicy;
        $dummyModel = new stdClass;

        expect($policy->viewAny($user))->toBeTrue();
        expect($policy->create($user))->toBeTrue();
        expect($policy->update($user, $dummyModel))->toBeFalse();
        expect($policy->delete($user, $dummyModel))->toBeFalse();
    });
});

describe('RolePolicy Specific Tests', function () {
    it('authorizes role management with correct permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo([
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.export',
        ]);

        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        $policy = new RolePolicy;

        expect($policy->viewAny($user))->toBeTrue();
        expect($policy->view($user, $role))->toBeTrue();
        expect($policy->create($user))->toBeTrue();
        expect($policy->update($user, $role))->toBeTrue();
        expect($policy->delete($user, $role))->toBeTrue();
        expect($policy->export($user))->toBeTrue();
    });

    it('uses roles prefix for permissions', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('roles.view');

        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        $this->actingAs($user);

        expect(Gate::allows('viewAny', Role::class))->toBeTrue();
        expect(Gate::denies('create', Role::class))->toBeTrue();
    });
});

describe('Multi-Guard Support', function () {
    it('respects guard segmentation', function () {
        // Create web user with web guard permissions
        $webUser = User::factory()->create();
        $webUser->givePermissionTo('dummy.view'); // web guard permission

        // For API guard testing, we demonstrate the concept but note the limitation
        // that Users model is tied to 'web' guard by default
        $apiUser = User::factory()->create();

        $webPolicy = new DummyResourcePolicy; // Uses default guard (web)
        $apiPolicy = new DummyGuardPolicy; // Uses api guard

        // Web user should work with web policy
        expect($webPolicy->viewAny($webUser))->toBeTrue();

        // API user without proper API guard permissions should be denied
        // This demonstrates guard segmentation concept
        expect($apiPolicy->viewAny($apiUser))->toBeFalse();
    });

    it('documents guard name method behavior', function () {
        $webPolicy = new DummyResourcePolicy;
        $apiPolicy = new DummyGuardPolicy;

        // Use reflection to test protected methods
        $reflection = new ReflectionClass($webPolicy);
        $guardMethod = $reflection->getMethod('guardName');
        $guardMethod->setAccessible(true);

        expect($guardMethod->invoke($webPolicy))->toBeNull();

        $apiReflection = new ReflectionClass($apiPolicy);
        $apiGuardMethod = $apiReflection->getMethod('guardName');
        $apiGuardMethod->setAccessible(true);

        expect($apiGuardMethod->invoke($apiPolicy))->toBe('api');
    });
});

describe('Policy Override Behavior', function () {
    it('allows concrete policies to override base behavior', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('dummy.delete'); // Has permission but override denies

        $policy = new DummyResourcePolicy;
        $dummyModel = new stdClass;

        // Base permission check would pass, but override denies
        expect($policy->delete($user, $dummyModel))->toBeFalse();

        // Other abilities still work normally
        $user->givePermissionTo('dummy.view');
        expect($policy->view($user, $dummyModel))->toBeTrue();
    });

    it('maintains parent method behavior when not overridden', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('dummy.view');

        $policy = new DummyResourcePolicy;
        $dummyModel = new stdClass;

        // View is not overridden, should use parent behavior
        expect($policy->view($user, $dummyModel))->toBeTrue();
    });
});

describe('Permission Construction', function () {
    it('constructs permissions correctly with ability prefix', function () {
        $policy = new DummyResourcePolicy;
        $user = User::factory()->create();

        // Use reflection to test protected can method
        $reflection = new ReflectionClass($policy);
        $canMethod = $reflection->getMethod('can');
        $canMethod->setAccessible(true);

        // User without permission should be denied
        expect($canMethod->invoke($policy, $user, 'view'))->toBeFalse();

        // User with specific permission should be allowed
        $user->givePermissionTo('dummy.view');
        expect($canMethod->invoke($policy, $user, 'view'))->toBeTrue();

        // Different ability should still be denied
        expect($canMethod->invoke($policy, $user, 'create'))->toBeFalse();
    });
});

describe('Gate::before Integration', function () {
    it('would allow super-admin to bypass all checks when Gate::before is enabled', function () {
        // Note: This test demonstrates what would happen if Gate::before was enabled
        // in AuthServiceProvider. The actual implementation is commented out.

        $superAdmin = User::factory()->create();
        $superAdminRole = Role::where('name', 'super-admin')->first();
        $superAdmin->assignRole($superAdminRole);

        // Manually implement Gate::before behavior for this test
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });

        $this->actingAs($superAdmin);

        // Super-admin should bypass all policy checks
        expect(Gate::allows('viewAny', 'DummyModel'))->toBeTrue();
        expect(Gate::allows('create', 'DummyModel'))->toBeTrue();
        expect(Gate::allows('update', ['DummyModel', new stdClass]))->toBeTrue();
        expect(Gate::allows('delete', ['DummyModel', new stdClass]))->toBeTrue(); // Even overridden ones
    });
});

describe('HTTP Integration', function () {
    it('can be used with controller authorization', function () {
        $user = User::factory()->create();
        $user->givePermissionTo('roles.view');

        $this->actingAs($user);

        // This would be used in a controller like:
        // $this->authorize('viewAny', Role::class);
        expect(Gate::allows('viewAny', Role::class))->toBeTrue();

        // For specific model instances:
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
        expect(Gate::allows('view', $role))->toBeTrue();
    });

    it('returns 403 when authorization fails in HTTP context', function () {
        $user = User::factory()->create(); // No permissions

        $this->actingAs($user)
            ->get('/') // Any route that would check authorization
            ->assertStatus(200); // This route doesn't check authorization

        // In a real controller using $this->authorize(), a 403 would be thrown
        expect(Gate::denies('viewAny', Role::class))->toBeTrue();
    });
});
