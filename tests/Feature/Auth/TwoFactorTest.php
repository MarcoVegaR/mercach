<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function enableTwoFactorFor(User $user): array
    {
        // Confirmar contraseña si la feature confirmPassword está activa
        $this->actingAs($user)
            ->post('/confirm-password', ['password' => 'password'])
            ->assertRedirect();

        // Habilitar 2FA (ahora sí pasa el middleware password.confirm)
        $this->post('/user/two-factor-authentication')->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        // Obtener códigos de recuperación
        $codes = $this->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $this->assertIsArray($codes);
        $this->assertNotEmpty($codes);

        return $codes;
    }

    public function test_enable_and_confirm_two_factor_with_totp(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactorFor($user);

        // Confirmar con TOTP válido
        $secret = (string) Crypt::decrypt($user->two_factor_secret);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post('/user/confirmed-two-factor-authentication', ['code' => $code])
            ->assertRedirect();

        $this->assertNotNull($user->refresh()->two_factor_confirmed_at);
    }

    public function test_two_factor_challenge_with_totp_then_with_recovery_code(): void
    {
        $user = User::factory()->create();
        $codes = $this->enableTwoFactorFor($user);

        // Confirmar 2FA para exigir challenge en próximos logins
        $secret = (string) Crypt::decrypt($user->two_factor_secret);
        $totp = (new Google2FA)->getCurrentOtp($secret);
        $this->post('/user/confirmed-two-factor-authentication', ['code' => $totp])->assertRedirect();

        // Logout para forzar login + challenge
        $this->post('/logout')->assertRedirect('/');

        // Login password OK → debe redirigir a two-factor-challenge
        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor-challenge');

        // Challenge con recovery_code válido autentica y redirige al home Fortify
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])
            ->assertRedirect('/dashboard');

        // Regenerar recovery codes (confirmar password si aplica) y validar que cambian
        $this->post('/confirm-password', ['password' => 'password'])->assertRedirect();
        $this->post('/user/two-factor-recovery-codes')->assertRedirect();
        $newCodes = $this->getJson('/user/two-factor-recovery-codes')->assertOk()->json();
        $this->assertNotEmpty($newCodes);
        $this->assertNotEquals($codes, $newCodes, 'Recovery codes should change after regeneration');

        // Logout y desafío con un recovery_code
        $this->post('/logout');
        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/two-factor-challenge');

        $this->post('/two-factor-challenge', ['recovery_code' => $newCodes[0]])
            ->assertRedirect('/dashboard');
    }
}
