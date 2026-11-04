<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Espaco extends Model
{
    public function equipamentos(): HasMany
    {
        return $this->hasMany(Equipamento::class);
    }
}
