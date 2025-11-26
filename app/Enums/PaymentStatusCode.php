<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Códigos de estado de pago (payment_statuses.code).
 *
 * Mapeo: UI Label → DB Code
 * - REGISTERED → REG
 * - CONFIRMED → CONF
 * - APPLIED → CONC
 */
enum PaymentStatusCode: string
{
    case REG = 'REG';
    case CONF = 'CONF';
    case CONC = 'CONC';

    /**
     * Etiqueta de UI para el código.
     */
    public function label(): string
    {
        return match ($this) {
            self::REG => 'REGISTERED',
            self::CONF => 'CONFIRMED',
            self::CONC => 'APPLIED',
        };
    }

    /**
     * Crear desde etiqueta UI (REGISTERED, CONFIRMED, APPLIED).
     */
    public static function fromLabel(string $label): ?self
    {
        return match (strtoupper($label)) {
            'REGISTERED' => self::REG,
            'CONFIRMED' => self::CONF,
            'APPLIED' => self::CONC,
            default => self::tryFrom(strtoupper($label)),
        };
    }

    /**
     * Obtener el ID de la tabla payment_statuses.
     */
    public function id(): int
    {
        return (int) (\App\Models\PaymentStatus::query()
            ->where('code', $this->value)
            ->value('id') ?? 0);
    }

    /**
     * Estados que permiten edición.
     *
     * @return list<self>
     */
    public static function editable(): array
    {
        return [self::REG];
    }

    /**
     * Estados que permiten eliminación.
     *
     * @return list<self>
     */
    public static function deletable(): array
    {
        return [self::REG, self::CONF];
    }
}
