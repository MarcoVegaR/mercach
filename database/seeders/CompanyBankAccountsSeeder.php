<?php

namespace Database\Seeders;

use App\Models\Bank;
use App\Models\CompanyBankAccount;
use Illuminate\Database\Seeder;

class CompanyBankAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure 100% Banco exists
        $bank = Bank::withTrashed()->updateOrCreate(
            ['code' => '0156'],
            [
                'name' => '100% BANCO, BANCO UNIVERSAL',
                'swift_bic' => null,
                'is_active' => true,
                'bank_code' => '156', // 3-digit per manual
            ]
        );
        if ($bank->trashed()) {
            $bank->restore();
        }

        // Receiver account (Instituto Autónomo Mercado de Chacao)
        // Normalized: account number without spaces, phone in 58XXXXXXXXXX (12 chars)
        $accountNumber = '01560030680000776369';
        $phone = '584242424564';

        CompanyBankAccount::withTrashed()->updateOrCreate(
            ['account_number' => $accountNumber],
            [
                'bank_id' => $bank->id,
                'phone_number' => $phone,
                'account_holder_name' => 'Instituto Autónomo Mercado de Chacao',
                'document_type' => 'G',
                'document_number' => '200092564',
                'is_active' => true,
            ]
        )->restore();
    }
}
