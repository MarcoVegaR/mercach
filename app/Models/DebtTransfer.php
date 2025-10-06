<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DebtTransfer>>
 */
class DebtTransfer extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $table = 'debt_transfers';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'executed_at', 'performed_by_user_id',
        'market_id', 'local_id',
        'from_debtor_type', 'from_debtor_id',
        'to_debtor_type', 'to_debtor_id',
        'new_contract_id', 'reason_id', 'note',
        'total_amount_minor', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'total_amount_minor' => 'integer',
        ];
    }
}
