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
use App\Repositories\ReservaRepository;
use App\Rules\AntecedenciaMaxima;
use App\Rules\HorarioAindaReservavel;
use App\Support\HorizonteCalendario;
use App\Support\IntervaloReserva;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DisponibilidadeService
{
    public function __construct(
        private DisponibilidadeEquipamentoRepository $disponibilidade,
        private ReservaRepository $reservas,
        private EquipamentoService $equipamentoService,
    ) {}

    public function consultar(Espaco $espaco, string $de, string $ate, ?Curso $curso = null, bool $identificarCurso = true): array
    {
        $equipamentos = $espaco->livre() ? collect() : $this->equipamentoService->listarAtivos($espaco);
        $uso = $this->disponibilidade->usoNoPeriodo($equipamentos->pluck('id')->all(), $de, $ate)->groupBy('data');
        $intervalos = $espaco->livre() ? [] : $this->intervalosFixos();
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

            $abreEm = $bloqueiosDoDia->contains(fn (DataBloqueada $b) => $b->diaInteiro())
                ? null
                : HorizonteCalendario::abreEm($dia)?->toDateString();

            if ($espaco->livre()) {
                $dias[] = [...$this->montarDiaLivre($espaco, $dia, $motivo, $reservas[$data] ?? collect(), $bloqueiosDoDia, $curso), 'abre_em' => $abreEm];

                continue;
            }

            $dias[] = [
                'data'       => $data,
                'reservavel' => $motivo === null,
                'motivo'     => $motivo,
                'abre_em'    => $abreEm,
                'horarios'   => array_map(
                    fn (array $intervalo) => $this->montarHorario(
                        $intervalo, $dia, $motivo, $reservas[$data] ?? collect(), $bloqueiosDoDia,
                        $equipamentos, $uso[$data] ?? collect(), $curso, $restantesNaSemana,
                    ),
                    $intervalos,
                ),
            ];
        }

        return [
            'espaco' => [
                'id'                     => $espaco->id,
                'nome'                   => $espaco->nome,
                'tipo'                   => $espaco->tipo,
                'modo_horario'           => $espaco->modo_horario,
                'abre_as'                => $espaco->abre_as ? IntervaloReserva::curta($espaco->abre_as) : null,
                'fecha_as'               => $espaco->fecha_as ? IntervaloReserva::curta($espaco->fecha_as) : null,
                'duracao_maxima_minutos' => $espaco->duracao_maxima_minutos,
                'bloco_minutos'          => $espaco->livre() ? IntervaloReserva::bloco() : null,
            ],
            'de'             => $de,
            'ate'            => $ate,
            'reservas_ate'   => AntecedenciaMaxima::dataLimite()->toDateString(),
            'calendario_ate' => HorizonteCalendario::ate()->toDateString(),
            'curso'          => $curso ? [
                ...($identificarCurso ? ['id' => $curso->id, 'nome' => $curso->nome] : []),
                'limite_semanal' => $espaco->limite_semanal_por_curso,
                'semanas'        => $semanas ? array_values($semanas) : [],
            ] : null,
            'dias' => $dias,
        ];
    }

    private function montarDiaLivre(Espaco $espaco, Carbon $dia, ?string $motivo, Collection $reservas, Collection $bloqueios, ?Curso $curso): array
    {
        $fimMinimo = null;

        if ($motivo === null && $dia->isToday()) {
            $fimMinimo = $this->fimMinimoDeHoje();

            if ($fimMinimo > IntervaloReserva::curta($espaco->fecha_as)) {
                [$motivo, $fimMinimo] = ['Os horários de hoje já foram encerrados.', null];
            }
        }

        $ocupados = $reservas
            ->map(fn (Reserva $reserva) => [
                'inicio'   => IntervaloReserva::curta($reserva->hora_inicio),
                'fim'      => IntervaloReserva::curta($reserva->hora_fim),
                'situacao' => $reserva->tipo === TipoReservaConstant::EVENTO ? 'EVENTO' : 'RESERVADO',
                'motivo'   => null,
                'reserva'  => $this->montarReserva($reserva, $curso),
            ])
            ->concat($bloqueios->reject(fn (DataBloqueada $bloqueio) => $bloqueio->diaInteiro())->map(fn (DataBloqueada $bloqueio) => [
                'inicio'   => IntervaloReserva::curta($bloqueio->hora_inicio),
                'fim'      => IntervaloReserva::curta($bloqueio->hora_fim),
                'situacao' => 'BLOQUEADO',
                'motivo'   => $bloqueio->motivo,
                'reserva'  => null,
            ]))
            ->sortBy('inicio')
            ->values()
            ->all();

        return [
            'data'       => $dia->toDateString(),
            'reservavel' => $motivo === null,
            'motivo'     => $motivo,
            'fim_minimo' => $fimMinimo,
            'ocupados'   => $ocupados,
        ];
    }

    private function fimMinimoDeHoje(): string
    {
        $agora = now();
        $minutos = $agora->hour * 60 + $agora->minute + ($agora->second > 0 ? 1 : 0) + (int) config('reservas.minutos_antes_do_fim');
        $bloco = IntervaloReserva::bloco();

        return IntervaloReserva::curta(IntervaloReserva::hora((int) ceil($minutos / $bloco) * $bloco));
    }

    private function intervalosFixos(): array
    {
        return array_map(
            fn (HorarioReservaConstant $horario) => [$horario, $horario->horaInicio(), $horario->horaFim()],
            HorarioReservaConstant::cases(),
        );
    }

    private function montarHorario(
        array $intervalo,
        Carbon $dia,
        ?string $motivoDoDia,
        Collection $reservas,
        Collection $bloqueios,
        Collection $equipamentos,
        Collection $uso,
        ?Curso $curso,
        ?int $restantesNaSemana,
    ): array {
        [$horario, $inicio, $fim] = $intervalo;

        $reserva = $reservas->first(fn (Reserva $r) => $r->hora_inicio < $fim && $r->hora_fim > $inicio);
        $bloqueio = $bloqueios->first(fn (DataBloqueada $b) => $b->diaInteiro() || ($b->hora_inicio < $fim && $b->hora_fim > $inicio));

        [$situacao, $motivo] = match (true) {
            $reserva !== null                                                          => [$reserva->tipo === TipoReservaConstant::EVENTO ? 'EVENTO' : 'RESERVADO', null],
            $bloqueio !== null                                                         => ['BLOQUEADO', $bloqueio->motivo],
            $motivoDoDia !== null                                                      => ['INDISPONIVEL', $motivoDoDia],
            $dia->isToday() && now()->gt(HorarioAindaReservavel::limiteParaHoje($fim)) => ['ENCERRADO', 'Reservas para hoje são aceitas até '.HorarioAindaReservavel::limiteParaHoje($fim)->format('H:i').'.'],
            default                                                                    => ['LIVRE', null],
        };

        $resultado = [
            'horario'  => $horario?->value,
            'inicio'   => IntervaloReserva::curta($inicio),
            'fim'      => IntervaloReserva::curta($fim),
            'situacao' => $situacao,
            'motivo'   => $motivo,
            'reserva'  => $reserva ? $this->montarReserva($reserva, $curso) : null,
        ];

        if ($curso) {
            $resultado['disponivel_para_o_curso'] = $situacao === 'LIVRE' && ($restantesNaSemana === null || $restantesNaSemana > 0);
        }

        $resultado['equipamentos'] = $equipamentos->map(function ($equipamento) use ($uso, $inicio, $fim) {
            $emUso = DisponibilidadeEquipamentoRepository::pico($uso->where('equipamento_id', $equipamento->id), $inicio, $fim);

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
            $diaInteiro !== null                       => $diaInteiro->motivo,
            $dia->gt(AntecedenciaMaxima::dataLimite()) => 'Abre para reserva em '.HorizonteCalendario::abreEm($dia)->format('d/m/Y').'.',
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

        $porSemana = $this->reservas->contarPorSemanaDoCurso(
            $curso->id, $espaco->id, $primeiraSegunda->toDateString(), $ultimaSexta->toDateString(),
        );

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
