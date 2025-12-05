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
                'allow_transfer' => true,
                'allow_pmov' => true,
                'allow_debit' => false,
            ]
        )->restore();

        // Bancaribe
        $bancaribe = Bank::withTrashed()->updateOrCreate(
            ['code' => '0114'],
            [
                'name' => 'BANCARIBE',
                'swift_bic' => null,
                'is_active' => true,
                'bank_code' => '114',
            ]
        );
        if ($bancaribe->trashed()) {
            $bancaribe->restore();
        }

        CompanyBankAccount::withTrashed()->updateOrCreate(
            ['account_number' => '01140159761590158476'],
            [
                'bank_id' => $bancaribe->id,
                'phone_number' => $phone,
                'account_holder_name' => 'Instituto Autónomo Mercado de Chacao',
                'document_type' => 'G',
                'document_number' => '200092564',
                'is_active' => true,
                'allow_transfer' => false,
                'allow_pmov' => false,
                'allow_debit' => true,
            ]
        )->restore();

        // Bancamiga
        $bancamiga = Bank::withTrashed()->updateOrCreate(
            ['code' => '0172'],
            [
                'name' => 'BANCAMIGA',
                'swift_bic' => null,
                'is_active' => true,
                'bank_code' => '172',
            ]
        );
        if ($bancamiga->trashed()) {
            $bancamiga->restore();
        }

        CompanyBankAccount::withTrashed()->updateOrCreate(
            ['account_number' => '01720111581115939249'],
            [
                'bank_id' => $bancamiga->id,
                'phone_number' => $phone,
                'account_holder_name' => 'Instituto Autónomo Mercado de Chacao',
                'document_type' => 'G',
                'document_number' => '200092564',
                'is_active' => true,
                'allow_transfer' => false,
                'allow_pmov' => false,
                'allow_debit' => true,
            ]
        )->restore();
    }
}
