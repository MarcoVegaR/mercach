<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Concessionaire;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class PortalTestUserSeeder extends Seeder
{
    public function run(): void
    {
        // Locate concessionaire 'ADELINA EVA NUÑEZ' by document_number
        $concessionaire = Concessionaire::query()
            ->where('document_number', '9966862')
            ->first();

        if (! $concessionaire) {
            $this->command->warn('Concessionaire for Eva Núñez not found, skipping PortalTestUserSeeder.');

            return;
        }

        $email = 'eva.nunez.portal@mailinator.com';
        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $user = User::create([
                'name' => 'Eva Núñez',
                'email' => $email,
                'password' => Hash::make('12345678'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        // Prefer standard role for portal users
        try {
            $user->assignRole('concesionario');
        } catch (\Throwable $e) {
            // Fallback minimal permission if role not present
            try {
                $user->givePermissionTo('portal.access');
            } catch (\Throwable $e2) {
            }
        }

        // Attach pivot (active)
        $concessionaire->users()->syncWithoutDetaching([
            $user->id => [
                'is_primary' => true,
                'status' => 'active',
                'invited_at' => now(),
                'accepted_at' => now(),
                'created_by' => null,
            ],
        ]);

        $this->command->info('Portal test user linked to Concessionaire Eva Núñez. Email: '.$email.' / password: 12345678');
    }
}
