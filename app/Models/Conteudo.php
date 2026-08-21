<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conteudo extends Model
{
    protected $primaryKey = 'chave';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['chave', 'valor'];

    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }
}
