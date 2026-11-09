<?php

namespace App\Repositories;

use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReservaRepository
{
    public function filtrar(array $filtros): Builder
    {
        return Reserva::query()
            ->when($filtros['de'] ?? null, fn ($q, $de) => $q->where('data_reserva', '>=', $de))
            ->when($filtros['ate'] ?? null, fn ($q, $ate) => $q->where('data_reserva', '<=', $ate))
            ->when($filtros['espaco_id'] ?? null, fn ($q, $id) => $q->where('espaco_id', $id))
            ->when($filtros['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['horario'] ?? null, fn ($q, $horario) => $q->where('horario', $horario))
            ->when($filtros['tipo_espaco'] ?? null, fn ($q, $tipo) => $q->whereHas('espaco', fn ($e) => $e->where('tipo', $tipo)))
            ->when($filtros['curso_id'] ?? null, fn ($q, $curso) => $q->whereHas('aluno', fn ($a) => $a->where('curso_id', $curso)))
            ->when($filtros['devolucao'] ?? null, fn ($q, $situacao) => $q
                ->where('status', StatusConstant::RESERVADO)
                ->jaIniciadas()
                ->whereHas('equipamentos', fn ($e) => $e->where('item_reservas.situacao_devolucao', $situacao)))
            ->when($filtros['busca'] ?? null, fn ($q, $busca) => $q->whereHas('aluno', fn ($a) => $a->where(fn ($w) => $w
                ->whereLike('nome_completo', "%{$busca}%", caseSensitive: false)
                ->orWhereLike('registro_academico', "%{$busca}%", caseSensitive: false))));
    }

    public function totaisPorStatus(array $filtros): array
    {
        $totais = $this->filtrar($filtros)
            ->toBase()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        return collect(StatusConstant::cases())
            ->mapWithKeys(fn (StatusConstant $status) => [$status->value => (int) ($totais[$status->value] ?? 0)])
            ->all();
    }

    public function contarPorSemanaDoCurso(int $cursoId, int $espacoId, string $de, string $ate): Collection
    {
        return Reserva::query()
            ->join('alunos', 'alunos.id', '=', 'reservas.aluno_id')
            ->where('alunos.curso_id', $cursoId)
            ->where('reservas.espaco_id', $espacoId)
            ->where('reservas.tipo', TipoReservaConstant::ALUNO)
            ->where('reservas.status', StatusConstant::RESERVADO)
            ->whereBetween('reservas.data_reserva', [$de, $ate])
            ->pluck('reservas.data_reserva')
            ->countBy(fn ($data) => Carbon::parse($data)->startOfWeek(Carbon::MONDAY)->toDateString());
    }

    public function doAluno(int $alunoId, bool $somenteFuturas = true): Builder
    {
        return Reserva::query()
            ->where('aluno_id', $alunoId)
            ->when($somenteFuturas, fn ($q) => $q->where('data_reserva', '>=', today()->toDateString()));
    }

    public function ordenar(Builder $query, string $ordem = 'asc'): Builder
    {
        return $query
            ->orderBy('data_reserva', $ordem)
            ->orderBy('hora_inicio', $ordem)
            ->orderBy('id', $ordem);
    }
}
