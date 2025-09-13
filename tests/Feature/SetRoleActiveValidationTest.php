<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\SetRoleActiveRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SetRoleActiveValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PermissionsSeeder::class);
    }

    public function test_validation_blocks_deactivating_admin_role(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ], [
            'is_active' => true,
        ]);

        // Create request manually to test validation
        $request = new SetRoleActiveRequest;
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        // Mock route parameter
        $request->setRouteResolver(function () use ($adminRole) {
            $route = new \Illuminate\Routing\Route(['PATCH'], '/roles/{role}/active', []);
            $route->bind(new \Illuminate\Http\Request);
            $route->setParameter('role', $adminRole);

            return $route;
        });

        // Mock authenticated user
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Set input data
        $request->replace(['active' => false]);

        // Act & Assert
        $validator = Validator::make(
            ['active' => false],
            $request->rules()
        );

        $request->withValidator($validator);

        // Trigger the after callbacks by calling passes()
        $validator->passes();

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
        $this->assertStringContainsString('protegido', $validator->errors()->first('role'));
    }

    public function test_validation_blocks_deactivating_role_with_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');

        $role = Role::create([
            'name' => 'test-role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Assign user to role
        $targetUser = User::factory()->create();
        $targetUser->assignRole($role);

        // Create request manually to test validation
        $request = new SetRoleActiveRequest;
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        // Mock route parameter
        $request->setRouteResolver(function () use ($role) {
            $route = new \Illuminate\Routing\Route(['PATCH'], '/roles/{role}/active', []);
            $route->bind(new \Illuminate\Http\Request);
            $route->setParameter('role', $role);

            return $route;
        });

        // Mock authenticated user
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Set input data
        $request->replace(['active' => false]);

        // Act & Assert
        $validator = Validator::make(
            ['active' => false],
            $request->rules()
        );

        $request->withValidator($validator);

        // Trigger the after callbacks by calling passes()
        $validator->passes();

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('role', $validator->errors()->toArray());
        $this->assertStringContainsString('usuarios asignados', $validator->errors()->first('role'));
    }

    public function test_validation_allows_deactivating_normal_role_without_users(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');

        $role = Role::create([
            'name' => 'normal-role',
            'guard_name' => 'web',
            'is_active' => true,
        ]);

        // Create request manually to test validation
        $request = new SetRoleActiveRequest;
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        // Mock route parameter
        $request->setRouteResolver(function () use ($role) {
            $route = new \Illuminate\Routing\Route(['PATCH'], '/roles/{role}/active', []);
            $route->bind(new \Illuminate\Http\Request);
            $route->setParameter('role', $role);

            return $route;
        });

        // Mock authenticated user
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Set input data
        $request->replace(['active' => false]);

        // Act & Assert
        $validator = Validator::make(
            ['active' => false],
            $request->rules()
        );

        $request->withValidator($validator);

        $this->assertFalse($validator->fails());
        $this->assertEmpty($validator->errors()->toArray());
    }

    public function test_validation_allows_activating_any_role(): void
    {
        // Arrange
        $user = User::factory()->create();
        $user->givePermissionTo('roles.setActive');

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ], [
            'is_active' => false,
        ]);

        // Create request manually to test validation
        $request = new SetRoleActiveRequest;
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));

        // Mock route parameter
        $request->setRouteResolver(function () use ($adminRole) {
            $route = new \Illuminate\Routing\Route(['PATCH'], '/roles/{role}/active', []);
            $route->bind(new \Illuminate\Http\Request);
            $route->setParameter('role', $adminRole);

            return $route;
        });

        // Mock authenticated user
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Set input data (activating is always allowed)
        $request->replace(['active' => true]);

        // Act & Assert
        $validator = Validator::make(
            ['active' => true],
            $request->rules()
        );

        $request->withValidator($validator);

        $this->assertFalse($validator->fails());
        $this->assertEmpty($validator->errors()->toArray());
    }
}
