<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptSequence extends Model
{
    /** @use \Illuminate\Database\Eloquent\Factories\HasFactory<\Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReceiptSequence>> */
    use HasFactory;

    protected $table = 'receipt_sequences';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'market_id',
        'series_code',
        'next_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'market_id' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
