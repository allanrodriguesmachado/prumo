<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetWorthSnapshot extends Model
{
    public $timestamps = false;

    protected $fillable = ['household_id', 'user_id', 'date', 'cash', 'investments', 'goals', 'debts', 'cards', 'total', 'estimated'];

    protected function casts(): array
    {
        return ['date' => 'date', 'cash' => 'float', 'investments' => 'float', 'goals' => 'float', 'debts' => 'float', 'cards' => 'float', 'total' => 'float', 'estimated' => 'boolean'];
    }
}
