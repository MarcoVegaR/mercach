<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerCredit>>
 *
 * @phpstan-use HasFactory<Factory<self>>
 *
 * @method static \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerCredit> factory($count = null, $state = [])
 */
class CustomerCredit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'customer_credits';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'debtor_type',
        'debtor_id',
        'source_payment_id',
        'currency',
        'balance_minor',
        'status',
        'created_from',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'debtor_id' => 'integer',
            'source_payment_id' => 'integer',
            'balance_minor' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<\App\Models\Payment, self>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'source_payment_id');
    }
}
