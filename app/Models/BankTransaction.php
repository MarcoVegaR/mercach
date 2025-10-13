<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Optional model for future reconciliation flows.
 * Table is optional; create only if migration exists.
 *
 * @phpstan-use HasFactory<Factory<self>>
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankTransaction> factory($count = null, $state = [])
 */
class BankTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bank_transactions';

    protected $fillable = [
        'payment_id',
        'raw_request',
        'raw_response',
        'resp_code',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<\App\Models\Payment, self>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
