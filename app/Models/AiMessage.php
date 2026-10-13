<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $fillable = ['ai_conversation_id', 'role', 'content', 'provider'];

    protected function casts(): array
    {
        // conversas financeiras são dados sensíveis: criptografadas no banco
        return ['content' => 'encrypted'];
    }
}
