<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @phpstan-use HasFactory<Factory<self>>
 */
class PaymentAllocation extends Model implements AuditableContract
{
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'payment_allocations';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'charge_id',
        'local_id',
        'debtor_type',
        'debtor_id',
        'amount_bs_minor',
    ];

    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'charge_id' => 'integer',
            'local_id' => 'integer',
            'debtor_id' => 'integer',
            'amount_bs_minor' => 'integer',
        ];
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
