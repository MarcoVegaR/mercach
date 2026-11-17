<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractModality;
use App\Models\ContractStatus;
use App\Models\ContractType;
use App\Models\TradeCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    config(['audit.console' => true]);
    $this->seed([
        Database\Seeders\ContractStatusesSeeder::class,
        Database\Seeders\ContractTypesSeeder::class,
        Database\Seeders\ContractModalitiesSeeder::class,
    ]);
    $this->user = User::factory()->create();
    Permission::findOrCreate('catalogs.contract.delete', 'web');
    foreach (['view', 'update'] as $ability) {
        $this->user->givePermissionTo(Permission::findOrCreate('catalogs.contract.'.$ability, 'web'));
    }
    $this->oldTrade = TradeCategory::query()->create(['code' => 'OLD', 'name' => 'Rubro anterior', 'is_active' => true]);
    $this->newTrade = TradeCategory::query()->create(['code' => 'NEW', 'name' => 'Nuevo rubro', 'is_active' => true]);
    $this->contract = Contract::factory()->create([
        'contract_type_id' => ContractType::query()->value('id'),
        'contract_modality_id' => ContractModality::query()->value('id'),
        'contract_status_id' => ContractStatus::query()->where('code', 'VIG')->value('id'),
        'trade_category_id' => $this->oldTrade->id,
        'start_date' => '2026-01-01',
        'pdf_path' => 'public/contracts/firmado.pdf',
    ]);
    $this->contract->forceFill(['signed_at' => '2026-01-01 10:00:00'])->save();
    $this->data = [
        'trade_category_id' => $this->newTrade->id,
        'previous_trade_category_id' => $this->oldTrade->id,
        '_version' => $this->contract->updated_at->toISOString(),
        'reason' => 'Corrección según oficio.',
    ];
    $this->actingAs($this->user);
});

it('changes only the trade category on issued contracts and audits the reason', function (string $status): void {
    $this->contract->update(['contract_status_id' => ContractStatus::query()->where('code', $status)->value('id')]);
    $this->data['_version'] = $this->contract->updated_at->toISOString();
    $before = $this->contract->fresh()->getAttributes();

    $this->patch(route('catalogs.contract.trade-category', $this->contract), $this->data + [
        'number' => 'NO-CAMBIAR', 'monthly_price_eur' => 1, 'local_ids' => [999],
    ])->assertSessionHasNoErrors()->assertRedirect(route('catalogs.contract.show', $this->contract));

    $after = $this->contract->fresh()->getAttributes();
    expect($after['trade_category_id'])->toBe($this->newTrade->id);
    unset($before['trade_category_id'], $before['updated_at'], $after['trade_category_id'], $after['updated_at']);
    expect($after)->toBe($before);
    $audit = $this->contract->audits()->where('event', 'trade_category_changed')->sole();
    expect($audit->old_values)->toBe(['trade_category_id' => $this->oldTrade->id])
        ->and($audit->new_values)->toBe(['trade_category_id' => $this->newTrade->id, 'reason' => $this->data['reason']])
        ->and($audit->user_id)->toBe($this->user->id);
})->with(['VIG', 'EXT', 'VENC']);

it('rejects drafts and terminated contracts', function (string $status): void {
    $this->contract->update(['contract_status_id' => ContractStatus::query()->where('code', $status)->value('id')]);
    $this->data['_version'] = $this->contract->updated_at->toISOString();
    $this->patch(route('catalogs.contract.trade-category', $this->contract), $this->data)
        ->assertSessionHasErrors('trade_category_id');
    expect($this->contract->fresh()->trade_category_id)->toBe($this->oldTrade->id);
})->with(['BORR', 'TERM']);

it('validates the new category and required reason', function (string $case, string $field): void {
    match ($case) {
        'inactive' => $this->newTrade->update(['is_active' => false]),
        'deleted' => $this->newTrade->delete(),
        'missing' => $this->data['trade_category_id'] = 999999,
        'same' => $this->data['trade_category_id'] = $this->oldTrade->id,
        'reason' => $this->data['reason'] = '   ',
        'version' => $this->data['_version'] = '',
        'old_version' => $this->data['_version'] = '2000-01-01',
        'old_category' => $this->data['previous_trade_category_id'] = null,
    };
    $this->patch(route('catalogs.contract.trade-category', $this->contract), $this->data)
        ->assertSessionHasErrors($field);
    expect($this->contract->fresh()->trade_category_id)->toBe($this->oldTrade->id)
        ->and($this->contract->audits()->where('event', 'trade_category_changed')->count())->toBe(0);
})->with([
    ['inactive', 'trade_category_id'], ['deleted', 'trade_category_id'], ['missing', 'trade_category_id'],
    ['same', 'trade_category_id'], ['reason', 'reason'], ['version', '_version'],
    ['old_version', 'trade_category_id'], ['old_category', 'trade_category_id'],
]);

it('requires update permission on the endpoint and hides the action', function (): void {
    $this->user->revokePermissionTo('catalogs.contract.update');
    $this->patch(route('catalogs.contract.trade-category', $this->contract), $this->data)->assertForbidden();
    $this->get(route('catalogs.contract.show', $this->contract))
        ->assertInertia(fn (Assert $page) => $page->where('allowedActions.canChangeTradeCategory', false));
});

it('shows the action only for issued contracts with active trade options', function (string $status, bool $allowed): void {
    $this->contract->update(['contract_status_id' => ContractStatus::query()->where('code', $status)->value('id')]);
    $this->oldTrade->update(['is_active' => false]);
    $this->get(route('catalogs.contract.show', $this->contract))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('allowedActions.canChangeTradeCategory', $allowed)
            ->has('options.trade_categories', 1)
            ->where('options.trade_categories.0.id', $this->newTrade->id));
})->with([['VIG', true], ['EXT', true], ['VENC', true], ['BORR', false], ['TERM', false]]);
