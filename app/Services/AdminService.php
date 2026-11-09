<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Mail\ConviteAdminMail;
use App\Models\Auditoria;
use App\Models\User;
use App\Support\FrontEnd;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminService
{
    public function listarPaginado(int $porPagina = 15, array $filtros = []): LengthAwarePaginator
    {
        return User::with(Auditoria::RELACOES)
            ->when($filtros['busca'] ?? null, fn ($q, $busca) => $q->where(fn ($w) => $w
                ->whereLike('name', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('email', "%{$busca}%", caseSensitive: false)))
            ->when(isset($filtros['ativo']), fn ($q) => $q->where('ativo', $filtros['ativo']))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($porPagina);
    }

    public function detalhar(User $admin): User
    {
        return $admin->load(Auditoria::RELACOES);
    }

    public function convidar(array $data, User $convidadoPor): User
    {
        return DB::transaction(function () use ($data, $convidadoPor) {
            $admin = User::create([
                'name'     => $data['nome'],
                'email'    => $data['email'],
                'password' => Str::random(64),
                'ativo'    => true,
            ]);

            $this->enviarConvite($admin, $convidadoPor);

            return $this->detalhar($admin);
        });
    }

    public function reenviarConvite(User $admin, User $convidadoPor): void
    {
        if (! $admin->ativo) {
            throw new RegraNegocioException('Admin desativado não pode receber convite.', 409);
        }

        if ($admin->conviteAceito()) {
            throw new RegraNegocioException('Este admin já aceitou o convite. Para trocar a senha, use "esqueci minha senha".', 409);
        }

        $this->enviarConvite($admin, $convidadoPor);
    }

    public function atualizar(User $admin, array $data, User $responsavel): User
    {
        return DB::transaction(function () use ($admin, $data, $responsavel) {
            if (array_key_exists('ativo', $data) && ! $data['ativo']) {
                $this->verificarDesativacao($admin, $responsavel);
            }

            $campos = array_filter([
                'name'  => $data['nome'] ?? null,
                'email' => $data['email'] ?? null,
                'ativo' => $data['ativo'] ?? null,
            ], fn ($valor) => $valor !== null);

            $admin->update($campos);

            if (! $admin->ativo) {
                $admin->tokens()->delete();
            }

            return $this->detalhar($admin);
        });
    }

    public function desativar(User $admin, User $responsavel): User
    {
        return $this->atualizar($admin, ['ativo' => false], $responsavel);
    }

    private function verificarDesativacao(User $admin, User $responsavel): void
    {
        if ($admin->is($responsavel)) {
            throw new RegraNegocioException('Você não pode desativar a si mesmo.');
        }

        $ativos = User::where('ativo', true)->orderBy('id')->lockForUpdate()->get();

        if (! $ativos->contains($responsavel) || $ativos->count() < 2) {
            throw new RegraNegocioException('É preciso manter pelo menos um admin ativo.', 409);
        }
    }

    private function enviarConvite(User $admin, User $convidadoPor): void
    {
        $token = Password::broker('convites')->createToken($admin);
        $expiraEm = now()->addMinutes(config('auth.passwords.convites.expire'));

        Mail::to($admin->email)->queue((new ConviteAdminMail(
            $admin->name,
            FrontEnd::url("aceitar-convite/{$token}"),
            $expiraEm,
            $convidadoPor->name,
        ))->afterCommit());
    }
}
