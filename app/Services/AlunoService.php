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

    private function travar(Aluno $aluno): Aluno
    {
        $aluno = Aluno::whereKey($aluno->id)->lockForUpdate()->firstOrFail();

        if ($aluno->anonimizado()) {
            throw new RegraNegocioException('Este aluno foi anonimizado e não pode mais ser alterado.', 409);
        }

        return $aluno;
    }

    private function revogarReservasFuturas(Aluno $aluno, ?User $admin, bool $notificar = true): int
    {
        $reservas = Reserva::where('aluno_id', $aluno->id)->ativasFuturas()->get();

        foreach ($reservas as $reserva) {
            $this->reservaService->revogar($reserva, $admin, self::JUSTIFICATIVA_DESATIVACAO, $notificar);
        }

        return $reservas->count();
    }
}
