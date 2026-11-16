<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesafioLogin extends Model
{
    use Prunable;

    public const MAXIMO_TENTATIVAS = 5;

    protected $table = 'desafios_login';

    protected $fillable = [
        'user_id', 'desafio_hash', 'codigo_hash', 'expira_em', 'tentativas', 'reenvios', 'ultimo_envio_em', 'usado_em',
    ];

    protected function casts(): array
    {
        return [
            'expira_em'       => 'datetime',
            'ultimo_envio_em' => 'datetime',
            'usado_em'        => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function valido(): bool
    {
        return $this->usado_em === null
            && $this->expira_em->isFuture()
            && $this->tentativas < self::MAXIMO_TENTATIVAS;
    }

    public function prunable(): Builder
    {
        return static::where('expira_em', '<', now()->subDay());
    }
}
