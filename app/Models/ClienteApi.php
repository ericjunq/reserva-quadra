<?php

namespace App\Models;

use App\Constants\SituacaoClienteApiConstant;
use App\Models\Concerns\Auditavel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class ClienteApi extends Model implements AuthenticatableContract
{
    use Auditavel, Authenticatable, HasApiTokens, HasFactory;

    protected $table = 'clientes_api';

    protected $fillable = [
        'nome',
        'organizacao',
        'responsavel_nome',
        'responsavel_email',
        'finalidade',
        'permissoes',
        'limite_por_minuto',
        'ativo',
        'termos_versao',
        'termos_aceitos_em',
        'aceite_token_hash',
        'aceite_expira_em',
        'acesso_expira_em',
        'aviso_vencimento_enviado_em',
    ];

    protected $hidden = ['aceite_token_hash'];

    protected $attributes = [
        'ativo' => true,
    ];

    protected function casts(): array
    {
        return [
            'permissoes'                  => 'array',
            'limite_por_minuto'           => 'integer',
            'ativo'                       => 'boolean',
            'termos_aceitos_em'           => 'datetime',
            'aceite_expira_em'            => 'datetime',
            'acesso_expira_em'            => 'datetime',
            'aviso_vencimento_enviado_em' => 'datetime',
        ];
    }

    protected function responsavelEmail(): Attribute
    {
        return Attribute::set(fn (?string $valor) => User::normalizarEmail($valor));
    }

    protected function camposSensiveisDaAuditoria(): array
    {
        return ['aceite_token_hash'];
    }

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function situacao(): SituacaoClienteApiConstant
    {
        return match (true) {
            ! $this->ativo                    => SituacaoClienteApiConstant::DESATIVADO,
            $this->acesso_expira_em === null  => SituacaoClienteApiConstant::AGUARDANDO_ACEITE,
            $this->acesso_expira_em->isPast() => SituacaoClienteApiConstant::VENCIDO,
            default                           => SituacaoClienteApiConstant::ATIVO,
        };
    }

    public function linkDeAceitePendente(): bool
    {
        return $this->aceite_token_hash !== null && $this->aceite_expira_em?->isFuture();
    }
}
