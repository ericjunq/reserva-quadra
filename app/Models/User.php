<?php

namespace App\Models;

use App\Models\Concerns\Auditavel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'ativo', 'convite_aceito_em'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use Auditavel, HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'ativo'     => true,
        'principal' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'convite_aceito_em' => 'datetime',
            'ativo'             => 'boolean',
            'principal'         => 'boolean',
            'password'          => 'hashed',
        ];
    }

    public static function normalizarEmail(?string $email): ?string
    {
        return $email === null ? null : mb_strtolower(trim($email));
    }

    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $valor) => self::normalizarEmail($valor));
    }

    protected function camposSensiveisDaAuditoria(): array
    {
        return ['password', 'remember_token'];
    }

    public static function buscarPrincipal(): ?self
    {
        return self::where('principal', true)->first();
    }

    public function conviteAceito(): bool
    {
        return $this->convite_aceito_em !== null;
    }
}
