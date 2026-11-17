<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgreementSequence extends Model
{
    protected $primaryKey = 'year';

    public $incrementing = false;

    protected $fillable = ['year', 'next_number'];
}
