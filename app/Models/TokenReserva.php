<?php

namespace App\Models;

use App\Constants\FinalidadeTokenConstant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TokenReserva extends Model
{
    protected $table = 'tokens_reserva';

    protected $fillable = ['reserva_id', 'finalidade', 'token_hash', 'expira_em', 'usado_em'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'finalidade' => FinalidadeTokenConstant::class,
            'expira_em'  => 'datetime',
            'usado_em'   => 'datetime',
        ];
    }

    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function valido(): bool
    {
        return $this->usado_em === null && $this->expira_em->isFuture();
    }
}
