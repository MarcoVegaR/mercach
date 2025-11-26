<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Métodos de pago (payment_types.code).
 */
enum PaymentMethod: string
{
    case TRANSFER = 'TRF';
    case PAGO_MOVIL = 'PMOV';
    case DEBITO = 'DEB';
    case EXONERADO = 'EXO';

    /**
     * Normalizar código desde input de usuario.
     */
    public static function normalize(string $input): string
    {
        $upper = strtoupper(trim($input));

        return match ($upper) {
            'TRANSFER', 'TRANSFERENCIA' => self::TRANSFER->value,
            'PAGOMOVIL', 'PAGO MOVIL', 'PAGO-MOVIL' => self::PAGO_MOVIL->value,
            'DEBITO', 'DEBIT' => self::DEBITO->value,
            'EXONERADO', 'EXONERATION' => self::EXONERADO->value,
            default => $upper,
        };
    }

    /**
     * Métodos que requieren verificación bancaria.
     *
     * @return list<self>
     */
    public static function requiresBankVerification(): array
    {
        return [self::TRANSFER, self::PAGO_MOVIL];
    }

    /**
     * Métodos manuales (no requieren gateway).
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::DEBITO, self::EXONERADO];
    }

    /**
     * ¿Es método manual?
     */
    public function isManual(): bool
    {
        return in_array($this, self::manual(), true);
    }

    /**
     * ¿Requiere verificación bancaria?
     */
    public function requiresVerification(): bool
    {
        return in_array($this, self::requiresBankVerification(), true);
    }

    /**
     * Tipo de transacción para el gateway (sTrxType).
     */
    public function gatewayTrxType(): ?int
    {
        return match ($this) {
            self::PAGO_MOVIL => 300,
            self::TRANSFER => 211,
            default => null,
        };
    }

    /**
     * Etiqueta amigable para UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::TRANSFER => 'Transferencia',
            self::PAGO_MOVIL => 'Pago Móvil',
            self::DEBITO => 'Débito',
            self::EXONERADO => 'Exonerado',
        };
    }
}
