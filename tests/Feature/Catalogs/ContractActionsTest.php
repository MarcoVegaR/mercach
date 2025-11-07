<?php

declare(strict_types=1);

namespace Tests\Feature\Catalogs;

use App\Models\Concessionaire;
use App\Models\ConcessionaireType;
use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\DocumentType;
use App\Models\Local;
use App\Models\LocalLocation;
use App\Models\LocalStatus;
use App\Models\LocalType;
use App\Models\Market;
use App\Models\TradeCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inertia.testing.ensure_pages_exist' => false]);
        $this->seed(PermissionsSeeder::class);

        $this->user = User::factory()->create();
        $this->user->assignRole(Role::where('name', 'admin')->first());
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Minimal catalogs required for domain logic
        ContractStatus::insert([
            ['code' => 'BORR', 'name' => 'Borrador', 'is_active' => true],
            ['code' => 'VIG',  'name' => 'Vigente',  'is_active' => true],
            ['code' => 'EXT',  'name' => 'Extendido', 'is_active' => true],
            ['code' => 'TERM', 'name' => 'Terminado', 'is_active' => true],
            ['code' => 'VENC', 'name' => 'Vencido',  'is_active' => true],
        ]);
        $type = ContractType::create(['code' => 'ARR', 'name' => 'Arriendo', 'is_active' => true]);
        $mod = ContractModality::create(['code' => 'VAR', 'name' => 'Variable', 'is_active' => true]);
        $cat = TradeCategory::create(['code' => 'RC1', 'name' => 'Rubro 1', 'description' => 'D', 'is_active' => true]);

        $disp = LocalStatus::create(['code' => 'DISP', 'name' => 'Disponible', 'is_active' => true]);
        LocalStatus::create(['code' => 'OCUP', 'name' => 'Ocupado', 'is_active' => true]);

        $market = Market::create(['name' => 'Mercado 1', 'code' => 'M1', 'address' => 'Dirección 1', 'is_active' => true]);
        $locType = LocalType::create(['code' => 'LT1', 'name' => 'Tienda', 'is_active' => true]);
        $locLoc = LocalLocation::create(['code' => 'P1', 'name' => 'Pasillo 1', 'is_active' => true]);

        $docType = DocumentType::create(['code' => 'V', 'name' => 'V-', 'is_active' => true]);
        $ctype = ConcessionaireType::create(['code' => 'PER', 'name' => 'Persona', 'is_active' => true]);
        $con = Concessionaire::create([
            'concessionaire_type_id' => $ctype->id,
            'full_name' => 'Titular Uno',
            'document_type_id' => $docType->id,
            'document_number' => '123',
            'fiscal_address' => 'Av. Principal',
            'email' => 'titular1@example.com',
            'is_active' => true,
        ]);

        // At least one DISP local
        Local::create([
            'code' => 'L1',
            'name' => 'Local 1',
            'local_type_id' => $locType->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $locLoc->id,
            'market_id' => $market->id,
            'area_m2' => 10.0,
            'is_active' => true,
        ]);
    }

    private function createDraftContract(): Contract
    {
        $type = ContractType::first();
        $mod = ContractModality::first();
        $cat = TradeCategory::first();
        $con = Concessionaire::first();
        // Create a fresh DISP local to avoid overlaps between test contracts
        $disp = LocalStatus::whereRaw('UPPER(code) = ?', ['DISP'])->firstOrFail();
        $market = Market::firstOrFail();
        $lt = LocalType::firstOrFail();
        $ll = LocalLocation::firstOrFail();
        $local = Local::create([
            'code' => 'L'.Str::upper(Str::random(4)),
            'name' => 'Local '.Str::upper(Str::random(4)),
            'local_type_id' => $lt->id,
            'local_status_id' => $disp->id,
            'local_location_id' => $ll->id,
            'market_id' => $market->id,
            'area_m2' => 12.5,
            'is_active' => true,
        ]);

        $resp = $this->actingAs($this->user)->post(route('catalogs.contract.store'), [
            'number' => 'C-DRAFT-'.Str::random(6),
            'contract_type_id' => $type->id,
            'contract_modality_id' => $mod->id,
            'trade_category_id' => $cat->id,
            'start_date' => '2025-01-01',
            'end_date' => null,
            'primary_concessionaire_id' => $con->id,
            'local_ids' => [$local->id],
            'is_active' => true,
        ]);
        $resp->assertRedirect(route('catalogs.contract.index'));

        return Contract::orderByDesc('id')->firstOrFail();
    }

    public function test_confirm_moves_borr_to_vig_and_occupies_locals(): void
    {
        $c = $this->createDraftContract();

        $resp = $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c));
        $resp->assertRedirect();

        $cFresh = $c->fresh(['status', 'locals']);
        $this->assertSame('VIG', strtoupper((string) ($cFresh->status?->code ?? '')));
        $this->assertDatabaseHas('locals', [
            'id' => $cFresh->locals()->first()->id,
            'is_active' => true,
        ]);
    }

    public function test_terminate_sets_term_and_frees_locals(): void
    {
        $c = $this->createDraftContract();
        $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c))->assertRedirect();

        $resp = $this->actingAs($this->user)->patch(route('catalogs.contract.terminate', $c));
        $resp->assertRedirect();
        $cFresh = $c->fresh('status');
        $this->assertSame('TERM', strtoupper((string) ($cFresh->status?->code ?? '')));
        $this->assertNotNull($cFresh->end_date);
    }

    public function test_extend_creates_extension_and_sets_ext(): void
    {
        $c = $this->createDraftContract();
        $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c))->assertRedirect();

        // Sign the contract before extending (provisional cannot be extended)
        $this->actingAs($this->user)->patch(route('catalogs.contract.sign', $c))->assertRedirect();

        $c->update(['end_date' => '2025-12-31']);
        $resp = $this->actingAs($this->user)->post(route('catalogs.contract.extend', $c), [
            'new_end_date' => '2026-12-31',
            'extension_pdf' => UploadedFile::fake()->create('ext.pdf', 20, 'application/pdf'),
        ]);
        $resp->assertRedirect();

        $cFresh = $c->fresh('status');
        $this->assertSame('EXT', strtoupper((string) ($cFresh->status?->code ?? '')));
        $this->assertSame('2026-12-31', Carbon::parse((string) $cFresh->end_date)->toDateString());
        $this->assertDatabaseHas('contract_extensions', [
            'contract_id' => $cFresh->id,
            'from_end_date' => '2025-12-31',
            'to_end_date' => '2026-12-31',
        ]);
    }

    public function test_bulk_confirm_and_terminate_flow(): void
    {
        $c1 = $this->createDraftContract();
        $c2 = $this->createDraftContract();

        // Bulk confirm
        $resp = $this->actingAs($this->user)->post(route('catalogs.contract.bulk'), [
            'action' => 'confirm',
            'ids' => [$c1->id, $c2->id],
        ]);
        $resp->assertRedirect(route('catalogs.contract.index'));

        $this->assertSame('VIG', strtoupper((string) $c1->fresh('status')->status?->code));
        $this->assertSame('VIG', strtoupper((string) $c2->fresh('status')->status?->code));

        // Bulk terminate
        $resp = $this->actingAs($this->user)->post(route('catalogs.contract.bulk'), [
            'action' => 'terminate',
            'ids' => [$c1->id, $c2->id],
        ]);
        $resp->assertRedirect(route('catalogs.contract.index'));

        $this->assertSame('TERM', strtoupper((string) $c1->fresh('status')->status?->code));
        $this->assertSame('TERM', strtoupper((string) $c2->fresh('status')->status?->code));
    }

    public function test_extend_validates_new_date_after_current(): void
    {
        $c = $this->createDraftContract();
        $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c))->assertRedirect();
        // Sign before attempting to extend
        $this->actingAs($this->user)->patch(route('catalogs.contract.sign', $c))->assertRedirect();
        $c->update(['end_date' => '2025-12-31']);

        // Attempt invalid extension (earlier date)
        $resp = $this->actingAs($this->user)->post(route('catalogs.contract.extend', $c), [
            'new_end_date' => '2025-06-30',
            'extension_pdf' => UploadedFile::fake()->create('ext.pdf', 20, 'application/pdf'),
        ]);
        $resp->assertSessionHasErrors('new_end_date');
    }

    public function test_expire_overdue_sets_venc_and_does_not_free_locals(): void
    {
        $c = $this->createDraftContract();
        $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c))->assertRedirect();
        // Sign then set past end_date
        $this->actingAs($this->user)->patch(route('catalogs.contract.sign', $c))->assertRedirect();
        $yesterday = Carbon::today()->subDay()->toDateString();
        $c->update(['end_date' => $yesterday]);

        // Expire overdue
        app(\App\Contracts\Services\ContractServiceInterface::class)->expireOverdue();

        $cFresh = $c->fresh(['status', 'locals']);
        $this->assertSame('VENC', strtoupper((string) ($cFresh->status?->code ?? '')));
        // Local must remain OCUP
        $ocupId = (int) (\App\Models\LocalStatus::where('code', 'OCUP')->value('id') ?? 0);
        $this->assertGreaterThan(0, $ocupId);
        $localId = (int) $cFresh->locals()->first()->id;
        $this->assertDatabaseHas('locals', ['id' => $localId, 'local_status_id' => $ocupId]);
    }

    public function test_provisional_does_not_expire_and_cannot_extend(): void
    {
        $c = $this->createDraftContract();
        $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c))->assertRedirect();
        // Leave unsigned (provisional) and set past end_date
        $yesterday = Carbon::today()->subDay()->toDateString();
        $c->update(['end_date' => $yesterday]);

        // Expire should ignore provisional
        app(\App\Contracts\Services\ContractServiceInterface::class)->expireOverdue();
        $this->assertSame('VIG', strtoupper((string) ($c->fresh('status')->status?->code ?? '')));

        // Try to extend: should fail and redirect with error flash
        $resp = $this->actingAs($this->user)->post(route('catalogs.contract.extend', $c), [
            'new_end_date' => Carbon::today()->addMonth()->toDateString(),
            'extension_pdf' => UploadedFile::fake()->create('ext.pdf', 20, 'application/pdf'),
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('error');
    }

    public function test_terminate_from_venc_frees_locals(): void
    {
        $c = $this->createDraftContract();
        $this->actingAs($this->user)->patch(route('catalogs.contract.confirm', $c))->assertRedirect();
        $this->actingAs($this->user)->patch(route('catalogs.contract.sign', $c))->assertRedirect();
        $yesterday = Carbon::today()->subDay()->toDateString();
        $c->update(['end_date' => $yesterday]);
        app(\App\Contracts\Services\ContractServiceInterface::class)->expireOverdue();
        $this->assertSame('VENC', strtoupper((string) ($c->fresh('status')->status?->code ?? '')));

        // Terminate
        $this->actingAs($this->user)->patch(route('catalogs.contract.terminate', $c))->assertRedirect();
        $cFresh = $c->fresh(['status', 'locals']);
        $this->assertSame('TERM', strtoupper((string) ($cFresh->status?->code ?? '')));
        $dispId = (int) (\App\Models\LocalStatus::where('code', 'DISP')->value('id') ?? 0);
        $localId = (int) $cFresh->locals()->first()->id;
        $this->assertDatabaseHas('locals', ['id' => $localId, 'local_status_id' => $dispId]);
    }
}
