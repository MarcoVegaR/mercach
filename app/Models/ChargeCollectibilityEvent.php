<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargeCollectibilityEvent extends Model
{
    public const ActionMarkedUncollectible = 'MARKED_UNCOLLECTIBLE';

    public const ActionRestored = 'RESTORED';

    protected $table = 'charge_collectibility_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'charge_id',
        'action',
        'reason',
        'outstanding_amount_minor',
        'outstanding_bs_minor',
        'user_id',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'charge_id' => 'integer',
            'outstanding_amount_minor' => 'integer',
            'outstanding_bs_minor' => 'integer',
            'user_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Charge, self>
     */
    public function charge(): BelongsTo
    {
        return $this->belongsTo(Charge::class);
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
