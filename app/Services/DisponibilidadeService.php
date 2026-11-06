<?php

namespace App\Services;

use App\Constants\HorarioReservaConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Models\Curso;
use App\Models\DataBloqueada;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use App\Rules\AntecedenciaMaxima;
use App\Rules\HorarioAindaReservavel;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DisponibilidadeService
{
    public function __construct(
        private DisponibilidadeEquipamentoRepository $disponibilidade,
        private EquipamentoService $equipamentoService,
    ) {}

    public function consultar(Espaco $espaco, string $de, string $ate, ?Curso $curso = null): array
    {
        $equipamentos = $this->equipamentoService->listarAtivos($espaco);
        $uso = $this->disponibilidade->usoNoPeriodo($equipamentos->pluck('id')->all(), $de, $ate)->groupBy('data');
        $reservas = $this->reservasDoPeriodo($espaco, $de, $ate)->groupBy(fn (Reserva $r) => $r->data_reserva->toDateString());
        $bloqueios = $this->bloqueiosDoPeriodo($espaco, $de, $ate);
        $semanas = $curso && $espaco->limite_semanal_por_curso ? $this->semanasDoCurso($espaco, $curso, $de, $ate) : null;

        $dias = [];

        foreach (CarbonPeriod::create($de, $ate) as $dia) {
            $data = $dia->toDateString();
            $bloqueiosDoDia = $bloqueios->filter(
                fn (DataBloqueada $b) => $b->data_inicio->toDateString() <= $data && $b->data_fim->toDateString() >= $data
            );
            $motivo = $this->motivoDoDia($dia, $bloqueiosDoDia);
            $restantesNaSemana = $semanas[$dia->copy()->startOfWeek(Carbon::MONDAY)->toDateString()]['restantes'] ?? null;

            $dias[] = [
                'data'        => $data,
                'reservavel'  => $motivo === null,
                'motivo'      => $motivo,
                'horarios'    => array_map(
                    fn (HorarioReservaConstant $horario) => $this->montarHorario(
                        $horario, $dia, $motivo, $reservas[$data] ?? collect(), $bloqueiosDoDia,
                        $equipamentos, $uso[$data] ?? collect(), $curso, $restantesNaSemana,
                    ),
                    HorarioReservaConstant::cases(),
                ),
            ];
        }

        return [
            'espaco'       => ['id' => $espaco->id, 'nome' => $espaco->nome, 'tipo' => $espaco->tipo],
            'de'           => $de,
            'ate'          => $ate,
            'reservas_ate' => AntecedenciaMaxima::dataLimite()->toDateString(),
            'curso'        => $curso ? [
                'id'                => $curso->id,
                'nome'              => $curso->nome,
                'limite_semanal'    => $espaco->limite_semanal_por_curso,
                'semanas'           => $semanas ? array_values($semanas) : [],
            ] : null,
            'dias'         => $dias,
        ];
    }

    private function montarHorario(
        HorarioReservaConstant $horario,
        Carbon $dia,
        ?string $motivoDoDia,
        Collection $reservas,
        Collection $bloqueios,
        Collection $equipamentos,
        Collection $uso,
        ?Curso $curso,
        ?int $restantesNaSemana,
    ): array {
        $inicio = $horario->horaInicio();
        $fim = $horario->horaFim();

        $reserva = $reservas->first(fn (Reserva $r) => $r->hora_inicio < $fim && $r->hora_fim > $inicio);
        $bloqueio = $bloqueios->first(fn (DataBloqueada $b) => $b->diaInteiro() || ($b->hora_inicio < $fim && $b->hora_fim > $inicio));

        [$situacao, $motivo] = match (true) {
            $reserva !== null => [$reserva->tipo === TipoReservaConstant::EVENTO ? 'EVENTO' : 'RESERVADO', null],
            $bloqueio !== null => ['BLOQUEADO', $bloqueio->motivo],
            $motivoDoDia !== null => ['INDISPONIVEL', $motivoDoDia],
            $dia->isToday() && now()->gt(HorarioAindaReservavel::limiteParaHoje($horario))
                => ['ENCERRADO', 'Reservas para hoje são aceitas até ' . HorarioAindaReservavel::limiteParaHoje($horario)->format('H:i') . '.'],
            default => ['LIVRE', null],
        };

        $resultado = [
            'horario'    => $horario->value,
            'inicio'     => $horario->inicio(),
            'fim'        => $horario->fim(),
            'situacao'   => $situacao,
            'motivo'     => $motivo,
            'reserva'    => $reserva ? $this->montarReserva($reserva, $curso) : null,
        ];

        if ($curso) {
            $resultado['disponivel_para_o_curso'] = $situacao === 'LIVRE' && ($restantesNaSemana === null || $restantesNaSemana > 0);
        }

        $resultado['equipamentos'] = $equipamentos->map(function ($equipamento) use ($uso, $inicio, $fim) {
            $emUso = $uso
                ->where('equipamento_id', $equipamento->id)
                ->filter(fn ($linha) => $linha->hora_inicio < $fim && $linha->hora_fim > $inicio)
                ->sum('total');

            return [
                'id'         => $equipamento->id,
                'nome'       => $equipamento->nome,
                'total'      => $equipamento->quantidade_total,
                'disponivel' => max($equipamento->quantidade_total - $emUso, 0),
            ];
        })->values()->all();

        return $resultado;
    }

    private function montarReserva(Reserva $reserva, ?Curso $curso): array
    {
        $dados = [
            'id'            => $reserva->id,
            'tipo'          => $reserva->tipo,
            'descricao'     => $reserva->descricao,
            'reservado_por' => $reserva->aluno
                ? ['tipo' => 'ALUNO', 'nome' => $reserva->aluno->nome_completo, 'curso' => $reserva->aluno->curso?->nome]
                : ['tipo' => 'ADMIN', 'nome' => $reserva->criador?->name, 'curso' => null],
        ];

        if ($curso) {
            $dados['do_curso'] = $reserva->aluno?->curso_id === $curso->id;
        }

        return $dados;
    }

    private function motivoDoDia(Carbon $dia, Collection $bloqueios): ?string
    {
        $diaInteiro = $bloqueios->first(fn (DataBloqueada $b) => $b->diaInteiro());

        return match (true) {
            $dia->lt(today())                          => 'Data passada.',
            $dia->isWeekend()                          => 'Fim de semana.',
            $dia->gt(AntecedenciaMaxima::dataLimite()) => 'Reservas abertas até ' . AntecedenciaMaxima::dataLimite()->format('d/m/Y') . '.',
            $diaInteiro !== null                       => $diaInteiro->motivo,
            default                                    => null,
        };
    }

    private function reservasDoPeriodo(Espaco $espaco, string $de, string $ate): Collection
    {
        return Reserva::with(['aluno.curso', 'criador'])
            ->where('espaco_id', $espaco->id)
            ->where('status', StatusConstant::RESERVADO)
            ->whereBetween('data_reserva', [$de, $ate])
            ->get();
    }

    private function bloqueiosDoPeriodo(Espaco $espaco, string $de, string $ate): Collection
    {
        return DataBloqueada::query()
            ->where('data_inicio', '<=', $ate)
            ->where('data_fim', '>=', $de)
            ->where(fn ($q) => $q->whereNull('espaco_id')->orWhere('espaco_id', $espaco->id))
            ->get();
    }

    private function semanasDoCurso(Espaco $espaco, Curso $curso, string $de, string $ate): array
    {
        $primeiraSegunda = Carbon::parse($de)->startOfWeek(Carbon::MONDAY);
        $ultimaSexta = Carbon::parse($ate)->startOfWeek(Carbon::MONDAY)->addDays(4);

        $porSemana = Reserva::query()
            ->join('alunos', 'alunos.id', '=', 'reservas.aluno_id')
            ->where('alunos.curso_id', $curso->id)
            ->where('reservas.espaco_id', $espaco->id)
            ->where('reservas.tipo', TipoReservaConstant::ALUNO)
            ->where('reservas.status', StatusConstant::RESERVADO)
            ->whereBetween('reservas.data_reserva', [$primeiraSegunda->toDateString(), $ultimaSexta->toDateString()])
            ->pluck('reservas.data_reserva')
            ->countBy(fn ($data) => Carbon::parse($data)->startOfWeek(Carbon::MONDAY)->toDateString());

        $semanas = [];

        for ($segunda = $primeiraSegunda->copy(); $segunda->lte($ultimaSexta); $segunda->addWeek()) {
            $chave = $segunda->toDateString();
            $usadas = $porSemana[$chave] ?? 0;

            $semanas[$chave] = [
                'inicio'    => $chave,
                'fim'       => $segunda->copy()->addDays(4)->toDateString(),
                'reservas'  => $usadas,
                'restantes' => max($espaco->limite_semanal_por_curso - $usadas, 0),
            ];
        }

        return $semanas;
    }
}
