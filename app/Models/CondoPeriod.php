<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>>
 */
class CondoPeriod extends Model implements AuditableContract
{
    /** @use HasFactory<Factory<self>> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'condo_periods';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'market_id',
        'period',
        'status',
        'finalized_at',
        'finalized_by_id',
        'reopened_at',
        'reopened_by_id',
        'locked_at',
        'note',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'market_id' => 'integer',
            'period' => 'date',
            'finalized_at' => 'datetime',
            'reopened_at' => 'datetime',
            'locked_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Relationships
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Market, static>
     */
    public function market(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\CondoExpense, static>
     */
    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CondoExpense::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\CondoParticipant, static>
     */
    public function participants(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CondoParticipant::class);
    }

    // Helpers
    public function isDraft(): bool
    {
        return (string) $this->getAttribute('status') === 'DRAFT';
    }

    public function isFinal(): bool
    {
        return (string) $this->getAttribute('status') === 'FINAL';
    }

    /**
     * Returns true if there are charges linked to this condo period (ignoring soft-deleted rows).
     */
    public function hasCharges(): bool
    {
        $id = $this->getKey();
        if (empty($id)) {
            return false;
        }

        return DB::table('charges')
            ->where('condo_period_id', '=', $id)
            ->whereNull('deleted_at')
            ->exists();
    }
}
