<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DebtTransferItem>>
 */
class DebtTransferItem extends Model
{
    /** @use HasFactory<Factory<self>> */
    use HasFactory;

    protected $table = 'debt_transfer_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'debt_transfer_id', 'charge_id',
        'amount_minor', 'currency', 'period', 'issued_on', 'due_on', 'kind',
        'prev_debtor_type', 'prev_debtor_id', 'new_debtor_type', 'new_debtor_id',
        'prev_contract_id', 'new_contract_id',
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
