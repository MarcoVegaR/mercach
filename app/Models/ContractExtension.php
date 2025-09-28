<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ContractExtension>>
 */
class ContractExtension extends Model
{
    /** @use HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<self>> */
    use HasFactory;

    protected $table = 'contract_extensions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'contract_id',
        'from_end_date',
        'to_end_date',
        'pdf_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_end_date' => 'date',
            'to_end_date' => 'date',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Contract, self>
     */
    public function contract(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
