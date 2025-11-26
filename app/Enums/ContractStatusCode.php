<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Códigos de estado de contrato (contract_statuses.code).
 */
enum ContractStatusCode: string
{
    case BORRADOR = 'BOR';
    case VIGENTE = 'VIG';
    case EXTENDIDO = 'EXT';
    case VENCIDO = 'VENC';
    case TERMINADO = 'TERM';

    /**
     * Estados que generan cargos (contrato activo operacionalmente).
     *
     * @return list<string>
     */
    public static function activeForCharges(): array
    {
        return [
            self::VIGENTE->value,
            self::EXTENDIDO->value,
            self::VENCIDO->value,
        ];
    }

    /**
     * Estados que mantienen el local ocupado.
     *
     * @return list<string>
     */
    public static function occupying(): array
    {
        return [
            self::VIGENTE->value,
            self::EXTENDIDO->value,
            self::VENCIDO->value,
        ];
    }

    /**
     * Estados terminales (contrato cerrado).
     *
     * @return list<string>
     */
    public static function closed(): array
    {
        return [self::TERMINADO->value];
    }

    /**
     * Obtener el ID de la tabla contract_statuses.
     */
    public function id(): int
    {
        return (int) (\App\Models\ContractStatus::query()
            ->where('code', $this->value)
            ->value('id') ?? 0);
    }

    /**
     * Obtener IDs para múltiples códigos.
     *
     * @param  list<self>  $codes
     * @return list<int>
     */
    public static function ids(array $codes): array
    {
        $values = array_map(fn (self $c) => $c->value, $codes);

        return \App\Models\ContractStatus::query()
            ->whereIn('code', $values)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
