<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketIndex extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'date', 'value'];

    protected function casts(): array
    {
        return ['date' => 'date', 'value' => 'float'];
    }
}
