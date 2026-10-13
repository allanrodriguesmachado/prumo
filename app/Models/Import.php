<?php

namespace App\Models;

use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    use BelongsToHousehold;

    protected $fillable = ['household_id', 'user_id', 'filename', 'format', 'imported', 'skipped'];
}
