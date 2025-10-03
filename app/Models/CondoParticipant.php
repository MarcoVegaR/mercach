<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable as AuditableTrait;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>>
 */
class CondoParticipant extends Model implements AuditableContract
{
    /** @use HasFactory<Factory<self>> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'condo_participants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'condo_period_id',
        'local_id',
        'area_m2_snapshot',
        'included',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condo_period_id' => 'integer',
            'local_id' => 'integer',
            'area_m2_snapshot' => 'decimal:2',
            'included' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\CondoPeriod, static>
     */
    public function period(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CondoPeriod::class, 'condo_period_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Local, static>
     */
    public function local(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Local::class);
    }
}
