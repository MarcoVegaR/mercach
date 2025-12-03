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
        // Admin user (full permissions via role)
        $email = 'test@mailinator.com';

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => 'Test Admin',
                'email' => $email,
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]);
        } elseif ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
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

        // Deterministic viewer user (no special permissions)
        $viewerEmail = 'viewer@mailinator.com';
        $viewer = User::query()->where('email', $viewerEmail)->first();
        if (! $viewer) {
            $viewer = User::create([
                'name' => 'Test Viewer',
                'email' => $viewerEmail,
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]);
        } elseif ($viewer->email_verified_at === null) {
            $viewer->forceFill(['email_verified_at' => now()])->save();
        }

        $gcRole = Role::where('name', 'gestor-cobranza')->where('guard_name', 'web')->first();
        $cjRole = Role::where('name', 'consultoria-juridica')->where('guard_name', 'web')->first();

        $u1 = User::query()->where('email', 'arelis@mailinator.com')->first();
        if (! $u1) {
            $u1 = User::create([
                'name' => 'Arelis yamilet castro ruiz',
                'email' => 'arelis@mailinator.com',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]);
        } elseif ($u1->email_verified_at === null) {
            $u1->forceFill(['email_verified_at' => now()])->save();
        }
        if ($gcRole) {
            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $u1->id)
                ->delete();
            DB::table('model_has_roles')->insert([
                'role_id' => $gcRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $u1->id,
            ]);
        }

        $u2 = User::query()->where('email', 'camila@mailinator.com')->first();
        if (! $u2) {
            $u2 = User::create([
                'name' => 'camila del carmen hidalgo gomez',
                'email' => 'camila@mailinator.com',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]);
        } elseif ($u2->email_verified_at === null) {
            $u2->forceFill(['email_verified_at' => now()])->save();
        }
        if ($gcRole) {
            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $u2->id)
                ->delete();
            DB::table('model_has_roles')->insert([
                'role_id' => $gcRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $u2->id,
            ]);
        }

        // Consultoría Jurídica users
        $cj1 = User::query()->where('email', 'lauravalecillos@mailinator.com')->first();
        if (! $cj1) {
            $cj1 = User::create([
                'name' => 'Laura Valecillos',
                'email' => 'lauravalecillos@mailinator.com',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]);
        } elseif ($cj1->email_verified_at === null) {
            $cj1->forceFill(['email_verified_at' => now()])->save();
        }
        if ($cjRole) {
            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $cj1->id)
                ->delete();
            DB::table('model_has_roles')->insert([
                'role_id' => $cjRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $cj1->id,
            ]);
        }

        $cj2 = User::query()->where('email', 'jesussalmeron@mailinator.com')->first();
        if (! $cj2) {
            $cj2 = User::create([
                'name' => 'Jesús Salmerón',
                'email' => 'jesussalmeron@mailinator.com',
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
            ]);
        } elseif ($cj2->email_verified_at === null) {
            $cj2->forceFill(['email_verified_at' => now()])->save();
        }
        if ($cjRole) {
            DB::table('model_has_roles')
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $cj2->id)
                ->delete();
            DB::table('model_has_roles')->insert([
                'role_id' => $cjRole->id,
                'model_type' => 'App\\Models\\User',
                'model_id' => $cj2->id,
            ]);
        }

        // Generate 50 additional random test users (only in local/testing environments)
        if (app()->environment(['local', 'testing'])) {
            \App\Models\User::factory()->count(1)->create();
            $this->command->info('Created 1 additional random test users');
        }
    }
}
