<?php

namespace App\Models;

use App\Enums\PrecoSecao;
use Illuminate\Database\Eloquent\Model;

class Preco extends Model
{
    protected $fillable = ['secao', 'servico', 'valor', 'nota', 'ordem'];

    protected function casts(): array
    {
        return [
            'secao' => PrecoSecao::class,
            'ordem' => 'integer',
        ];
    }
}
