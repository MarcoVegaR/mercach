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
class CondoExpense extends Model implements AuditableContract
{
    /** @use HasFactory<Factory<self>> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'condo_expenses';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'condo_period_id',
        'expense_type_id',
        'amount_usd_minor',
        'invoice_number',
        'expense_date',
        'attachment_path',
        'note',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condo_period_id' => 'integer',
            'expense_type_id' => 'integer',
            'amount_usd_minor' => 'integer',
            'expense_date' => 'date',
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
}
