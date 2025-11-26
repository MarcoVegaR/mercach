<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Códigos de estado de cargo (charge_statuses.code).
 */
enum ChargeStatusCode: string
{
    case ISSUED = 'ISSUED';
    case PARTIAL = 'PARTIAL';
    case SETTLED = 'SETTLED';
    case CANCELED = 'CANCELED';

    /**
     * Estados que permiten cobro (cargo abierto).
     *
     * @return list<string>
     */
    public static function collectable(): array
    {
        return [
            self::ISSUED->value,
            self::PARTIAL->value,
        ];
    }

    /**
     * Estados terminales (cargo cerrado).
     *
     * @return list<string>
     */
    public static function closed(): array
    {
        return [
            self::SETTLED->value,
            self::CANCELED->value,
        ];
    }

    /**
     * Obtener IDs de la tabla charge_statuses para los códigos dados.
     *
     * @param  list<self>  $codes
     * @return list<int>
     */
    public static function ids(array $codes): array
    {
        $values = array_map(fn (self $c) => $c->value, $codes);

        return \App\Models\ChargeStatus::query()
            ->whereIn('code', $values)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * IDs de estados cobrables.
     *
     * @return list<int>
     */
    public static function collectableIds(): array
    {
        return self::ids([self::ISSUED, self::PARTIAL]);
    }

    /**
     * Obtener el ID de un código específico.
     */
    public function id(): int
    {
        return (int) (\App\Models\ChargeStatus::query()
            ->where('code', $this->value)
            ->value('id') ?? 0);
    }
}
