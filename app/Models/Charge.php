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
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Charge>>
 */
class Charge extends Model implements AuditableContract
{
    /** @use HasFactory<Factory<self>> */
    use AuditableTrait, HasFactory, SoftDeletes;

    protected $table = 'charges';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'market_id', 'local_id', 'contract_id', 'condo_period_id',
        'debtor_type', 'debtor_id',
        'origin_debtor_type', 'origin_debtor_id',
        'kind', 'currency', 'amount_minor',
        'period', 'issued_on', 'due_on',
        'charge_status_id', 'source', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'period' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
        ];
    }
}
