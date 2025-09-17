<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Bank;
use App\Models\ConcessionaireType;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\ExpenseType;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\PaymentStatus;
use App\Models\PaymentType;
use App\Models\PhoneAreaCode;
use App\Models\TradeCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExportXlsxSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        // Ensure an admin user with all permissions
        $user = User::factory()->create();
        $user->assignRole(Role::where('name', 'admin')->first());
        $this->be($user);
    }

    public function test_all_catalogs_support_xlsx_export(): void
    {
        // Seed at least one record per catalog
        LocalType::create(['code' => 'A', 'name' => 'A', 'description' => null, 'is_active' => true]);
        LocalStatus::create(['code' => 'A', 'name' => 'A', 'description' => null, 'is_active' => true]);
        TradeCategory::create(['code' => 'A', 'name' => 'A', 'description' => null, 'is_active' => true]);
        ConcessionaireType::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        DocumentType::create(['code' => 'A', 'name' => 'A', 'mask' => null, 'is_active' => true]);
        ContractType::create(['code' => 'A', 'name' => 'A', 'is_active' => true]);
        ContractStatus::create(['code' => 'ACTIVE', 'name' => 'Activo', 'is_active' => true]);
        ContractModality::create(['code' => 'FIXED', 'name' => 'Fijo', 'is_active' => true]);
        ExpenseType::create(['code' => 'A', 'name' => 'A', 'description' => null, 'is_active' => true]);
        PaymentStatus::create(['code' => 'REGISTERED', 'name' => 'Registrado', 'is_active' => true]);
        Bank::create(['code' => 'BOD', 'name' => 'Banco', 'is_active' => true]);
        PhoneAreaCode::create(['code' => '0412', 'is_active' => true]);
        PaymentType::create(['code' => 'CASH', 'name' => 'Efectivo', 'is_active' => true]);

        // Endpoints to test
        $endpoints = [
            '/catalogs/local-type/export?format=xlsx',
            '/catalogs/local-status/export?format=xlsx',
            '/catalogs/trade-category/export?format=xlsx',
            '/catalogs/concessionaire-type/export?format=xlsx',
            '/catalogs/document-type/export?format=xlsx',
            '/catalogs/contract-type/export?format=xlsx',
            '/catalogs/contract-status/export?format=xlsx',
            '/catalogs/contract-modality/export?format=xlsx',
            '/catalogs/expense-type/export?format=xlsx',
            '/catalogs/payment-status/export?format=xlsx',
            '/catalogs/bank/export?format=xlsx',
            '/catalogs/phone-area-code/export?format=xlsx',
            '/catalogs/payment-type/export?format=xlsx',
        ];

        foreach ($endpoints as $url) {
            $resp = $this->get($url);
            $resp->assertOk();
            // Our XlsxExporter streams CSV with UTF-8 BOM for Excel compatibility
            $resp->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }
    }
}
