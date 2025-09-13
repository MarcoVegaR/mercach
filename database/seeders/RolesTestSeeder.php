<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesTestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only seed test roles in local/testing environments
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        // Create 50 test roles with random permissions
        \Database\Factories\RoleFactory::new()->count(50)->create();

        // Assign random users to the created test roles
        $this->assignRandomUsersToRoles();

        $this->command->info('Created 50 test roles with random permissions and assigned users');
    }

    /**
     * Assign random users to test roles.
     */
    private function assignRandomUsersToRoles(): void
    {
        // Get all users (excluding the admin user)
        $users = User::where('email', '!=', 'test@mailinator.com')->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found to assign to roles. Make sure UsersSeeder runs first.');

            return;
        }

        // Get test roles (exclude protected roles like 'admin', 'user')
        $testRoles = Role::where('guard_name', 'web')
            ->whereNotIn('name', ['admin', 'user', 'guest'])
            ->get();

        foreach ($testRoles as $role) {
            // Randomly decide if this role should have users (70% chance)
            if (fake()->boolean(70)) {
                // Assign 1-5 random users to each role
                $userCount = fake()->numberBetween(1, min(5, $users->count()));
                $randomUsers = $users->random($userCount);

                foreach ($randomUsers as $user) {
                    // Avoid duplicate assignments
                    if (! $user->hasRole($role->name)) {
                        $user->assignRole($role);
                    }
                }
            }
        }

        $assignmentCount = \DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereNotIn('roles.name', ['admin', 'user', 'guest'])
            ->count();

        $this->command->info("Assigned {$assignmentCount} role-user relationships");
    }
}
