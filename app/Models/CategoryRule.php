<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'pattern', 'category_id', 'hits'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
