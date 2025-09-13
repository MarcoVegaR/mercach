<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UsersSeeder extends Seeder
{
    /**
     * Seed the application's default admin user.
     */
    public function run(): void
    {
        $email = 'test@mailinator.com';

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Test Admin',
                'email' => $email,
                'password' => Hash::make('12345678'),
            ]);
        }

        // Ensure admin role exists
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();

        if ($adminRole) {
            // Clear any existing roles and assign admin role
            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $user->id)
                ->delete();

            DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $user->id,
            ]);

            // Clear permission cache
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        }

        // Generate 50 additional random test users (only in local/testing environments)
        if (app()->environment(['local', 'testing'])) {
            \App\Models\User::factory()->count(50)->create();
            $this->command->info('Created 50 additional random test users');
        }
    }
}
