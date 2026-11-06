<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Models\Aluno;
use App\Models\Auditoria;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AlunoService
{
    public const JUSTIFICATIVA_DESATIVACAO = 'Cadastro do aluno desativado.';

    public const JUSTIFICATIVA_ANONIMIZACAO = 'Dados do aluno anonimizados a pedido (LGPD).';

    public const CAMPOS_PESSOAIS = ['nome_completo', 'registro_academico', 'email'];

    public const VALOR_ANONIMIZADO = '[anonimizado]';

    public function __construct(private ReservaService $reservaService) {}

    public function criar(array $data): Aluno
    {
        return Aluno::create($data)->load('curso');
    }

    public function listarPaginado(int $porPagina = 15, array $filtros = []): LengthAwarePaginator
    {
        return Aluno::with(['curso', ...Auditoria::RELACOES])
            ->when($filtros['busca'] ?? null, fn ($q, $busca) => $q->where(fn ($w) => $w
                ->whereLike('nome_completo', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('registro_academico', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('email', "%{$busca}%", caseSensitive: false)))
            ->when($filtros['curso_id'] ?? null, fn ($q, $curso) => $q->where('curso_id', $curso))
            ->when(isset($filtros['ativo']), fn ($q) => $q->where('ativo', $filtros['ativo']))
            ->orderBy('nome_completo')
            ->orderBy('id')
            ->paginate($porPagina);
    }

    public function detalhar(Aluno $aluno): Aluno
    {
        return $aluno
            ->load(['curso', ...Auditoria::RELACOES])
            ->loadCount(['reservas as reservas_futuras_count' => fn ($q) => $q->ativasFuturas()]);
    }

    public function atualizar(Aluno $aluno, array $data, ?User $admin): array
    {
        return DB::transaction(function () use ($aluno, $data, $admin) {
            $aluno = $this->travar($aluno);
            $revogadas = 0;

            if (array_key_exists('ativo', $data) && ! $data['ativo'] && $aluno->ativo) {
                $revogadas = $this->revogarReservasFuturas($aluno, $admin);
            }

            $aluno->update($data);

            return [$this->detalhar($aluno), $revogadas];
        });
    }

    public function desativar(Aluno $aluno, ?User $admin): array
    {
        return $this->atualizar($aluno, ['ativo' => false], $admin);
    }

    public function anonimizar(Aluno $aluno, ?User $admin): Aluno
    {
        return DB::transaction(function () use ($aluno, $admin) {
            $aluno = $this->travar($aluno);

            $reservas = Reserva::where('aluno_id', $aluno->id)->ativasFuturas()->get();

            foreach ($reservas as $reserva) {
                $this->reservaService->revogar($reserva, $admin, self::JUSTIFICATIVA_ANONIMIZACAO, notificar: false);
            }

            $aluno->update([
                'nome_completo'      => 'Aluno anonimizado',
                'registro_academico' => 'A'.str_pad((string) $aluno->id, 9, '0', STR_PAD_LEFT),
                'email'              => "anonimizado-{$aluno->id}@anonimizado.invalid",
                'ativo'              => false,
                'anonimizado_em'     => now(),
            ]);

            $this->anonimizarHistorico($aluno);

            return $this->detalhar($aluno);
        });
    }

    private function anonimizarHistorico(Aluno $aluno): void
    {
        $aluno->auditorias()->get()->each(function (Auditoria $auditoria) {
            $auditoria->update([
                'antes'  => $this->ocultarCamposPessoais($auditoria->antes),
                'depois' => $this->ocultarCamposPessoais($auditoria->depois),
            ]);
        });
    }

    private function ocultarCamposPessoais(?array $valores): ?array
    {
        if ($valores === null) {
            return null;
        }

        foreach (self::CAMPOS_PESSOAIS as $campo) {
            if (array_key_exists($campo, $valores)) {
                $valores[$campo] = self::VALOR_ANONIMIZADO;
            }
        }

        return $valores;
    }

    private function travar(Aluno $aluno): Aluno
    {
        $aluno = Aluno::whereKey($aluno->id)->lockForUpdate()->firstOrFail();

        if ($aluno->anonimizado()) {
            throw new RegraNegocioException('Este aluno foi anonimizado e não pode mais ser alterado.', 409);
        }

        return $aluno;
    }

    private function revogarReservasFuturas(Aluno $aluno, ?User $admin): int
    {
        $reservas = Reserva::where('aluno_id', $aluno->id)->ativasFuturas()->get();

        foreach ($reservas as $reserva) {
            $this->reservaService->revogar($reserva, $admin, self::JUSTIFICATIVA_DESATIVACAO);
        }

        return $reservas->count();
    }
}
