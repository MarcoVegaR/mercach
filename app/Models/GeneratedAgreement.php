<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed> $snapshot
 */
class GeneratedAgreement extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'start_date' => 'date', 'signature_date' => 'date'];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<User, $this> */
    public function issuer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Los convenios emitidos no se pueden modificar.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Los convenios emitidos no se pueden eliminar.');
        });
    }
}
