<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\MarketTariffServiceInterface;
use App\Exceptions\DomainActionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MarketTariffService extends BaseService implements MarketTariffServiceInterface
{
    /** @var array<int,string>|null */
    private ?array $marketNames = null;

    private function getMarketName(int $marketId): string
    {
        if ($this->marketNames === null) {
            $this->marketNames = DB::table('markets')->pluck('name', 'id')->mapWithKeys(function ($name, $id) {
                return [(int) $id => (string) $name];
            })->all();
        }

        return $this->marketNames[$marketId] ?? (string) $marketId;
    }

    private function computeIsCurrent(int $marketId, ?string $validFrom, bool $isActive): bool
    {
        if (! $isActive || empty($validFrom)) {
            return false;
        }
        $today = date('Y-m-d');
        // Latest valid_from not in the future among active tariffs for this market
        $latest = DB::table('market_tariffs')
            ->where('market_id', $marketId)
            ->where('is_active', true)
            ->where('valid_from', '<=', $today)
            ->max('valid_from');

        return $latest !== null && $validFrom === $latest;
    }

    /**
     * Mapea un Model a array para 'rows'.
     * El generador reemplazará 'id' => $model->getAttribute('id'),
            'market_id' => $model->getAttribute('market_id'),
            'valid_from' => $model->getAttribute('valid_from'),
            'price_per_m2_eur_minor' => $model->getAttribute('price_per_m2_eur_minor'),
            'is_current' => $model->getAttribute('is_current'),
            'is_active' => (bool) ($model->getAttribute('is_active') ?? true),
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at') con el shape correcto según --fields.
     *
     * @return array<string, mixed>
     */
    protected function toRow(Model $model): array
    {
        $marketId = (int) $model->getAttribute('market_id');
        $validFrom = $model->getAttribute('valid_from');
        $isActive = (bool) ($model->getAttribute('is_active') ?? true);

        return [
            'id' => $model->getAttribute('id'),
            'market_id' => $model->getAttribute('market_id'),
            'market_name' => $this->getMarketName((int) $model->getAttribute('market_id')),
            'valid_from' => $validFrom,
            'price_per_m2_eur_minor' => $model->getAttribute('price_per_m2_eur_minor'),
            // Derive current by date (latest valid_from <= today among active)
            'is_current' => $this->computeIsCurrent($marketId, $validFrom, $isActive),
            'is_active' => $isActive,
            'created_at' => $model->getAttribute('created_at'),
            'updated_at' => $model->getAttribute('updated_at'),
        ];
    }

    /**
     * Columnas por defecto de exportación (cabeceras).
     * El generador reemplazará 'id' => '#',
            'market_id' => 'Market id',
            'valid_from' => 'Valid from',
            'price_per_m2_eur_minor' => 'Price per m2 eur minor',
            'is_current' => 'Is current',
            'is_active' => 'Estado',
            'created_at' => 'Creado'.
     *
     * @return array<string, string|int>
     */
    protected function defaultExportColumns(): array
    {
        return [
            'id' => '#',
            'market_id' => 'Market id',
            'valid_from' => 'Valid from',
            'price_per_m2_eur_minor' => 'Price per m2 eur minor',
            'is_current' => 'Is current',
            'is_active' => 'Estado',
            'created_at' => 'Creado',
        ];
    }

    /**
     * FQCN del modelo del repositorio (para filename de export, entre otros).
     */
    protected function repoModelClass(): string
    {
        return \App\Models\MarketTariff::class;
    }

    /**
     * Ensure exclusivity: when creating a new tariff with is_current=true for a market,
     * unset previous current for that market.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeCreate(array &$attributes): void
    {
        // Normalize inputs
        if (isset($attributes['valid_from'])) {
            $attributes['valid_from'] = (string) $attributes['valid_from'];
        }
        if (isset($attributes['price_per_m2_eur_minor'])) {
            $attributes['price_per_m2_eur_minor'] = (int) $attributes['price_per_m2_eur_minor'];
        }
        // is_current is derived; but DB column is NOT NULL, set false consistently
        $attributes['is_current'] = false;
    }

    /**
     * After creating, if is_current=true, unset previous current for market.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function afterCreate(Model $model, array $attributes): void {}

    /**
     * Ensure exclusivity on update when toggling is_current=true.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function beforeUpdate(Model $model, array &$attributes): void
    {
        // Normalize types
        if (array_key_exists('price_per_m2_eur_minor', $attributes)) {
            $attributes['price_per_m2_eur_minor'] = (int) $attributes['price_per_m2_eur_minor'];
        }
        // is_current is derived; ignore if provided
        if (array_key_exists('is_current', $attributes)) {
            unset($attributes['is_current']);
        }

        // Prevent deactivating the last active tariff in the market
        if (array_key_exists('is_active', $attributes) && (bool) $attributes['is_active'] === false) {
            $marketId = (int) ($attributes['market_id'] ?? $model->getAttribute('market_id'));
            $otherActives = (int) DB::table('market_tariffs')
                ->where('market_id', $marketId)
                ->where('id', '!=', $model->getKey())
                ->where('is_active', true)
                ->count();
            if ($otherActives === 0) {
                throw new DomainActionException('Debe existir al menos una tarifa activa para el mercado.');
            }
        }
    }

    /**
     * Extra data for index view (stats, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIndexExtras(): array
    {
        // Basic stats used by the Index page cards.
        $model = \App\Models\MarketTariff::query();
        $total = (int) $model->count();
        $active = (int) (clone $model)->where('is_active', true)->count();

        return [
            'stats' => [
                'total' => $total,
                'active' => $active,
            ],
        ];
    }
}
