<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DashboardPaymentRevenueBreakdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/dashboard/payment/revenue-breakdown')->assertForbidden();
    }

    public function test_returns_expected_structure_with_permission(): void
    {
        $user = User::factory()->create();
        Permission::firstOrCreate(['name' => 'dashboard.view.finance', 'guard_name' => 'web']);
        $user->givePermissionTo('dashboard.view.finance');

        $bank = Bank::create(['code' => 'BOD', 'bank_code' => '0116', 'name' => 'Banco Test', 'is_active' => true]);
        $acc = CompanyBankAccount::create([
            'bank_id' => $bank->id,
            'account_number' => '01560011223344556677',
            'phone_number' => '584241112233',
            'account_holder_name' => 'Cuenta Receptora',
            'document_type' => 'J',
            'document_number' => '123456789012',
            'is_active' => true,
        ]);

        Payment::create([
            'debtor_type' => 'LOCAL',
            'debtor_id' => 1,
            'company_bank_account_id' => $acc->id,
            'origin_bank_id' => $bank->id,
            'payer_document_type' => 'V',
            'payer_document_number' => '11111111',
            'reference' => 'DASH-001',
            'amount_bs_minor' => 12345,
            'paid_on' => '2025-10-10',
            'status' => 'APPLIED',
            'method' => 'PMOV',
        ]);

        Payment::create([
            'debtor_type' => 'LOCAL',
            'debtor_id' => 1,
            'company_bank_account_id' => $acc->id,
            'origin_bank_id' => $bank->id,
            'payer_document_type' => 'V',
            'payer_document_number' => '11111111',
            'reference' => '',
            'amount_bs_minor' => 99999,
            'paid_on' => '2025-10-10',
            'status' => 'APPLIED',
            'method' => 'EXO',
            'exoneration_reason' => 'Compensación no recaudatoria',
        ]);

        $res = $this->actingAs($user)->getJson('/api/dashboard/payment/revenue-breakdown?from=2025-10-01&to=2025-10-31');
        $res->assertOk();
        $res->assertJsonStructure([
            'from',
            'to',
            'by_destination_bank' => [
                '*' => ['bank_id', 'bank_name', 'amount_bs_minor', 'count'],
            ],
            'by_method' => [
                '*' => ['code', 'name', 'amount_bs_minor', 'count'],
            ],
            'generated_at',
        ]);

        $this->assertSame(12345, (int) collect($res->json('by_method'))->sum('amount_bs_minor'));
        $this->assertFalse(collect($res->json('by_method'))->contains('code', 'EXO'));
    }
}
