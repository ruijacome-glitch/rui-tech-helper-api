<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cliente extends Model
{
    protected $fillable = ['user_id', 'nome', 'telefone', 'email', 'morada', 'nif', 'notas'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
