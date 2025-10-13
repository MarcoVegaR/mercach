<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditApplication>>
 *
 * @phpstan-use HasFactory<Factory<\App\Models\CreditApplication>>
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditApplication> factory($count = null, $state = [])
 */
class CreditApplication extends Model
{
    use HasFactory;

    protected $table = 'credit_applications';

    protected $fillable = [
        'customer_credit_id',
        'payment_id',
        'charge_id',
        'amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'customer_credit_id' => 'integer',
            'payment_id' => 'integer',
            'charge_id' => 'integer',
            'amount_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<\App\Models\CustomerCredit, self>
     */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(CustomerCredit::class, 'customer_credit_id');
    }

    /**
     * @return BelongsTo<\App\Models\Payment, self>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<\App\Models\Charge, self>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }
}
