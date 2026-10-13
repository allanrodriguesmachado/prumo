<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetOperation extends Model
{
    public const TYPES = ['buy' => 'Compra / aporte', 'sell' => 'Venda / resgate', 'dividend' => 'Dividendo', 'jcp' => 'JCP', 'yield' => 'Rendimento'];

    protected $fillable = ['asset_id', 'type', 'date', 'quantity', 'price', 'fees', 'total'];

    protected function casts(): array
    {
        return ['date' => 'date', 'quantity' => 'float', 'price' => 'float', 'fees' => 'float', 'total' => 'float'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
