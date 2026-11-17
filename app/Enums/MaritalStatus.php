<?php

declare(strict_types=1);

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'single';
    case Married = 'married';
    case Divorced = 'divorced';
    case Widowed = 'widowed';

    public function label(bool $female = false): string
    {
        return match ($this) {
            self::Single => $female ? 'soltera' : 'soltero',
            self::Married => $female ? 'casada' : 'casado',
            self::Divorced => $female ? 'divorciada' : 'divorciado',
            self::Widowed => $female ? 'viuda' : 'viudo',
        };
    }
}
