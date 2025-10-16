<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Receipt>> */
    use HasFactory;

    protected $table = 'receipts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_id',
        'charge_id',
        'market_id',
        'scope',
        'concept',
        'template_version',
        'parent_receipt_id',
        'series_code',
        'number_seq',
        'receipt_number',
        'issued_at',
        'status',
        'allocations_hash',
        'public_token',
        'pdf_path',
        'pdf_sha256',
        'rendered_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_id' => 'integer',
            'charge_id' => 'integer',
            'market_id' => 'integer',
            'number_seq' => 'integer',
            'issued_at' => 'datetime',
            'rendered_at' => 'datetime',
            'meta' => 'array',
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
     * @return BelongsTo<\App\Models\Market, self>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }
}
