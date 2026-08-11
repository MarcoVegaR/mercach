<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifeProofSequence extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'key',
        'next_number',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
        ];
    }
}
