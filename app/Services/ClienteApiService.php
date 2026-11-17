<?php

namespace App\Services;

use App\Constants\SituacaoClienteApiConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\AceiteApiMail;
use App\Models\Auditoria;
use App\Models\ClienteApi;
use App\Support\FrontEnd;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ClienteApiService
{
    public const LINK_INVALIDO = 'Link inválido ou expirado. Peça um novo link a um administrador do sistema de reservas.';

    public const NOME_DO_TOKEN = 'parceiro';

    public function listarPaginado(int $porPagina, array $filtros): LengthAwarePaginator
    {
        return ClienteApi::with(Auditoria::RELACOES)
            ->when($filtros['busca'] ?? null, fn ($q, $busca) => $q->where(fn ($w) => $w
                ->whereLike('nome', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('organizacao', "%{$busca}%", caseSensitive: false)))
            ->when(isset($filtros['ativo']), fn ($q) => $q->where('ativo', $filtros['ativo']))
            ->orderBy('nome')
            ->orderBy('id')
            ->paginate($porPagina);
    }

    public function detalhar(ClienteApi $cliente): ClienteApi
    {
        return $cliente->load(Auditoria::RELACOES);
    }

    public function criar(array $data): ClienteApi
    {
        return DB::transaction(function () use ($data) {
            $cliente = ClienteApi::create([
                ...$data,
                'limite_por_minuto' => $data['limite_por_minuto'] ?? config('reservas.parceiros.limite_por_minuto'),
                'ativo'             => true,
            ]);

            $this->enviarLink($cliente, now()->addDays(config('reservas.parceiros.link_aceite_dias')));

            return $this->detalhar($cliente);
        });
    }

    public function atualizar(ClienteApi $cliente, array $data): ClienteApi
    {
        return DB::transaction(function () use ($cliente, $data) {
            $cliente = ClienteApi::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            $estavaAtivo = $cliente->ativo;

            $linkPendente = $cliente->linkDeAceitePendente();

            $cliente->fill($data);
            $trocouResponsavel = $cliente->isDirty('responsavel_email');
            $trocouPermissoes = $cliente->isDirty('permissoes');

            if (! $cliente->ativo) {
                $this->cortarAcesso($cliente);
            } elseif ($trocouResponsavel) {
                $cliente->fill(['aceite_token_hash' => null, 'aceite_expira_em' => null]);
            }

            $cliente->save();

            if ($cliente->ativo && $trocouPermissoes) {
                $cliente->tokens()->update(['abilities' => json_encode(array_values($cliente->permissoes))]);
            }

            if ($cliente->ativo && (! $estavaAtivo || ($trocouResponsavel && $linkPendente))) {
                $this->enviarLink($cliente, now()->addDays(config('reservas.parceiros.link_aceite_dias')));
            }

            return $this->detalhar($cliente);
        });
    }

    public function desativar(ClienteApi $cliente): ClienteApi
    {
        return $this->atualizar($cliente, ['ativo' => false]);
    }

    public function enviarLinkDeAceite(ClienteApi $cliente, bool $revogarAtual): ClienteApi
    {
        return DB::transaction(function () use ($cliente, $revogarAtual) {
            $cliente = ClienteApi::whereKey($cliente->id)->lockForUpdate()->firstOrFail();

            if (! $cliente->ativo) {
                throw new RegraNegocioException('Sistema desativado não recebe link de aceite. Reative o cadastro primeiro.', 409);
            }

            if ($revogarAtual) {
                $cliente->tokens()->delete();
                $cliente->acesso_expira_em = null;
            }

            $this->enviarLink($cliente, now()->addDays(config('reservas.parceiros.link_aceite_dias')));

            return $this->detalhar($cliente);
        });
    }

    public function buscarPeloLink(string $token): ClienteApi
    {
        $cliente = ClienteApi::where('aceite_token_hash', $this->hash($token))->first();

        if (! $cliente?->ativo || ! $cliente->linkDeAceitePendente()) {
            throw new RegraNegocioException(self::LINK_INVALIDO, 404);
        }

        return $cliente;
    }

    public function aceitar(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $cliente = ClienteApi::where('aceite_token_hash', $this->hash($token))->lockForUpdate()->first();

            if (! $cliente?->ativo || ! $cliente->linkDeAceitePendente()) {
                throw new RegraNegocioException(self::LINK_INVALIDO, 404);
            }

            $expiraEm = now()->addMonths(config('reservas.parceiros.validade_meses'));
            $acesso = $cliente->createToken(self::NOME_DO_TOKEN, array_values($cliente->permissoes), $expiraEm);

            $cliente->update([
                'termos_versao'               => config('reservas.parceiros.termos_versao'),
                'termos_aceitos_em'           => now(),
                'aceite_token_hash'           => null,
                'aceite_expira_em'            => null,
                'acesso_expira_em'            => $expiraEm,
                'aviso_vencimento_enviado_em' => null,
            ]);

            return [
                'token'      => $acesso->plainTextToken,
                'expira_em'  => $expiraEm->toIso8601String(),
                'permissoes' => array_values($cliente->permissoes),
            ];
        });
    }

    public function avisarVencimentos(): int
    {
        $limite = now()->addDays(config('reservas.parceiros.aviso_dias_antes'));

        $clientes = ClienteApi::query()
            ->where('ativo', true)
            ->where('acesso_expira_em', '>', now())
            ->where('acesso_expira_em', '<=', $limite)
            ->whereNull('aviso_vencimento_enviado_em')
            ->get();

        foreach ($clientes as $cliente) {
            DB::transaction(function () use ($cliente) {
                $minimo = now()->addDays(config('reservas.parceiros.link_aceite_dias'));
                $cliente->aviso_vencimento_enviado_em = now();

                $this->enviarLink($cliente, $cliente->acesso_expira_em->max($minimo), renovacao: true);
            });
        }

        return $clientes->count();
    }

    private function enviarLink(ClienteApi $cliente, CarbonInterface $expiraEm, bool $renovacao = false): void
    {
        $token = Str::random(64);

        $cliente->fill([
            'aceite_token_hash' => $this->hash($token),
            'aceite_expira_em'  => $expiraEm,
        ])->save();

        Mail::to($cliente->responsavel_email)->queue((new AceiteApiMail(
            $cliente->responsavel_nome,
            $cliente->nome,
            FrontEnd::url("parceiros/aceite/{$token}"),
            $expiraEm,
            $renovacao && $cliente->situacao() === SituacaoClienteApiConstant::ATIVO ? $cliente->acesso_expira_em : null,
        ))->afterCommit());
    }

    private function cortarAcesso(ClienteApi $cliente): void
    {
        $cliente->tokens()->delete();
        $cliente->fill([
            'aceite_token_hash' => null,
            'aceite_expira_em'  => null,
            'acesso_expira_em'  => null,
        ]);
    }

    private function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
