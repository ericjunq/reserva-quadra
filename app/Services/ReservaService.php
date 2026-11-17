<?php

namespace App\Services;

use App\Constants\OrigemReservaConstant;
use App\Constants\SituacaoSolicitacaoConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\ReservaRevogadaMail;
use App\Mail\UsoRegistradoMail;
use App\Models\Aluno;
use App\Models\Auditoria;
use App\Models\ClienteApi;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use App\Repositories\ReservaRepository;
use App\Support\IntervaloReserva;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReservaService
{
    public function __construct(
        private DisponibilidadeEquipamentoRepository $disponibilidade,
        private ReservaRepository $reservas,
        private ConfirmacaoService $confirmacao,
    ) {}

    public const RELACOES_ADMIN = ['aluno.curso', 'espaco', 'equipamentos', 'criador', 'atualizador', 'revogador', 'clienteApi:id,nome'];

    public function listar(array $filtros, string $ordem = 'asc', int $porPagina = 15): LengthAwarePaginator
    {
        return $this->reservas->ordenar($this->reservas->filtrar($filtros), $ordem)
            ->with(self::RELACOES_ADMIN)
            ->paginate($porPagina);
    }

    public function totaisPorStatus(array $filtros): array
    {
        unset($filtros['status']);

        return $this->reservas->totaisPorStatus($filtros);
    }

    public const RELACOES_PARCEIRO = ['espaco:id,nome', 'equipamentos'];

    public function create(array $data, ?User $admin = null, ?ClienteApi $cliente = null): Reserva
    {
        $aluno = Aluno::where('registro_academico', $data['registro_academico'])->firstOrFail();
        $data = $this->resolverIntervalo($data);

        return DB::transaction(function () use ($aluno, $data, $admin, $cliente) {
            $espaco = $this->travarEspaco($data['espaco_id']);
            $this->verificarConflitoDeEspaco($data);
            $this->verificarLimiteSemanalDoCurso($aluno, $espaco, $data);
            $this->verificarDisponibilidadeEquipamentos([
                ...$data,
                'equipamentos' => [...($data['equipamentos'] ?? []), ...($data['solicitacoes'] ?? [])],
            ]);

            $reserva = $aluno->reservas()->create([
                'data_reserva' => $data['data_reserva'],
                'horario'      => $data['horario'],
                'hora_inicio'  => $data['hora_inicio'],
                'hora_fim'     => $data['hora_fim'],
                'espaco_id'    => $data['espaco_id'],
                'tipo'         => TipoReservaConstant::ALUNO,
                'origem'       => match (true) {
                    $admin !== null   => OrigemReservaConstant::ADMIN,
                    $cliente !== null => OrigemReservaConstant::API,
                    default           => OrigemReservaConstant::SITE,
                },
                'criado_por'     => $admin?->id,
                'cliente_api_id' => $cliente?->id,
            ]);

            $this->anexarEquipamentos($reserva, $data);
            $this->anexarSolicitacoes($reserva, $data['solicitacoes'] ?? [], $admin);
            $this->confirmacao->pedirNaCriacao($reserva, $aluno);

            return match (true) {
                $admin !== null   => $reserva->load(self::RELACOES_ADMIN),
                $cliente !== null => $reserva->load(self::RELACOES_PARCEIRO),
                default           => $reserva->load('equipamentos'),
            };
        });
    }

    public function criarEvento(array $data, User $admin): Reserva
    {
        $data = $this->resolverIntervalo($data);

        return DB::transaction(function () use ($data, $admin) {
            $this->travarEspaco($data['espaco_id']);

            if ($conflito = $this->buscarConflito($data)) {
                if (empty($data['justificativa'])) {
                    throw new RegraNegocioException(
                        'Já existe uma reserva nesse horário. Envie uma justificativa para revogá-la e criar o evento.',
                        409,
                        ['reserva_conflitante_id' => $conflito->id],
                    );
                }

                $this->revogar($conflito, $admin, $data['justificativa']);
            }

            $this->verificarDisponibilidadeEquipamentos($data);

            $reserva = Reserva::create([
                'data_reserva'        => $data['data_reserva'],
                'horario'             => $data['horario'],
                'hora_inicio'         => $data['hora_inicio'],
                'hora_fim'            => $data['hora_fim'],
                'espaco_id'           => $data['espaco_id'],
                'descricao'           => $data['descricao'],
                'responsavel_nome'    => $data['responsavel_nome'],
                'responsavel_contato' => $data['responsavel_contato'] ?? null,
                'criado_por'          => $admin->id,
                'tipo'                => TipoReservaConstant::EVENTO,
                'origem'              => OrigemReservaConstant::ADMIN,
            ]);

            $this->anexarEquipamentos($reserva, $data);

            return $reserva->load('equipamentos');
        });
    }

    public const VALOR_REMOVIDO = '[removido]';

    public function atualizarResponsavel(Reserva $reserva, array $data): Reserva
    {
        if ($reserva->tipo !== TipoReservaConstant::EVENTO) {
            throw new RegraNegocioException('Só eventos têm responsável.', 422);
        }

        return DB::transaction(function () use ($reserva, $data) {
            if ($data['remover'] ?? false) {
                $reserva->update(['responsavel_nome' => null, 'responsavel_contato' => null]);
                $this->removerResponsavelDoHistorico($reserva);
            } else {
                $reserva->update([
                    'responsavel_nome'    => $data['responsavel_nome'],
                    'responsavel_contato' => $data['responsavel_contato'] ?? null,
                ]);
            }

            return $reserva->load(self::RELACOES_ADMIN);
        });
    }

    private function removerResponsavelDoHistorico(Reserva $reserva): void
    {
        $ocultar = function (?array $valores): ?array {
            foreach (['responsavel_nome', 'responsavel_contato'] as $campo) {
                if ($valores !== null && ($valores[$campo] ?? null) !== null) {
                    $valores[$campo] = self::VALOR_REMOVIDO;
                }
            }

            return $valores;
        };

        $reserva->auditorias()->get()->each(fn (Auditoria $auditoria) => $auditoria->update([
            'antes'  => $ocultar($auditoria->antes),
            'depois' => $ocultar($auditoria->depois),
        ]));
    }

    public function registrarUsoPosterior(array $data, User $admin): array
    {
        $aluno = empty($data['registro_academico'])
            ? null
            : Aluno::where('registro_academico', $data['registro_academico'])->firstOrFail();
        $data = $this->resolverIntervalo($data);

        return DB::transaction(function () use ($data, $admin, $aluno) {
            $espaco = $this->travarEspaco($data['espaco_id']);

            if ($conflito = $this->buscarConflito($data)) {
                throw new RegraNegocioException(
                    'Já existe uma reserva ou evento nesse horário. Confira o registro existente.',
                    409,
                    ['reserva_conflitante_id' => $conflito->id],
                );
            }

            $this->verificarDisponibilidadeEquipamentos($data);

            $reserva = Reserva::create([
                'data_reserva'        => $data['data_reserva'],
                'horario'             => $data['horario'],
                'hora_inicio'         => $data['hora_inicio'],
                'hora_fim'            => $data['hora_fim'],
                'espaco_id'           => $data['espaco_id'],
                'aluno_id'            => $aluno?->id,
                'descricao'           => $aluno ? null : $data['descricao'],
                'responsavel_nome'    => $aluno ? null : $data['responsavel_nome'],
                'responsavel_contato' => $aluno ? null : ($data['responsavel_contato'] ?? null),
                'criado_por'          => $admin->id,
                'tipo'                => $aluno ? TipoReservaConstant::ALUNO : TipoReservaConstant::EVENTO,
                'origem'              => OrigemReservaConstant::REGISTRO_POSTERIOR,
            ]);

            $this->anexarEquipamentos($reserva, $data);

            if ($aluno) {
                Mail::to($aluno->email)->queue((new UsoRegistradoMail($reserva->load('espaco')))->afterCommit());
            }

            return [$reserva->load(self::RELACOES_ADMIN), $aluno ? $this->avisoDoLimiteSemanal($aluno, $espaco, $data) : null];
        });
    }

    public function revogar(Reserva $reserva, ?User $admin, string $justificativa, bool $notificar = true): Reserva
    {
        return DB::transaction(function () use ($reserva, $admin, $justificativa, $notificar) {
            $reserva = Reserva::whereKey($reserva->id)->lockForUpdate()->firstOrFail();

            if ($reserva->status !== StatusConstant::RESERVADO) {
                throw new RegraNegocioException('Só é possível revogar reservas ativas.', 409);
            }

            if ($reserva->data_reserva->lt(today())) {
                throw new RegraNegocioException('Não é possível revogar uma reserva que já passou.');
            }

            $reserva->update([
                'status'                  => StatusConstant::REVOGADO,
                'justificativa_revogacao' => $justificativa,
                'revogada_em'             => now(),
                'revogada_por'            => $admin?->id,
            ]);

            if ($notificar && $reserva->aluno) {
                Mail::to($reserva->aluno->email)->queue(
                    (new ReservaRevogadaMail($reserva->load('aluno', 'espaco')))->afterCommit()
                );
            }

            return $reserva;
        });
    }

    public const JUSTIFICATIVA_NAO_CONFIRMADA = 'Reserva não confirmada no prazo.';

    public function revogarNaoConfirmadas(): int
    {
        $agora = now();

        $semConfirmacao = Reserva::query()
            ->where('status', StatusConstant::RESERVADO)
            ->where('confirmar_ate', '<=', $agora)
            ->whereNull('confirmada_em')
            ->get();

        $semReconfirmacao = Reserva::query()
            ->where('status', StatusConstant::RESERVADO)
            ->where('reconfirmar_ate', '<=', $agora)
            ->whereNull('reconfirmada_em')
            ->get();

        $naoMantidas = Reserva::query()
            ->where('status', StatusConstant::RESERVADO)
            ->where('manter_ate', '<=', $agora)
            ->whereNull('mantida_em')
            ->get();

        $revogadas = 0;

        foreach ($naoMantidas as $reserva) {
            try {
                $this->revogar($reserva, null, SolicitacaoService::JUSTIFICATIVA_NAO_MANTIDA);
                $revogadas++;
            } catch (RegraNegocioException) {
                continue;
            }
        }

        foreach ($semConfirmacao->concat($semReconfirmacao)->unique('id')->reject(fn (Reserva $r) => $naoMantidas->contains('id', $r->id)) as $reserva) {
            try {
                $this->revogar($reserva, null, self::JUSTIFICATIVA_NAO_CONFIRMADA);
                $revogadas++;
            } catch (RegraNegocioException) {
                continue;
            }
        }

        return $revogadas;
    }

    private function resolverIntervalo(array $data): array
    {
        [$inicio, $fim] = IntervaloReserva::doPedido($data);

        return [
            'horario'     => $data['horario'] ?? null,
            'hora_inicio' => $inicio,
            'hora_fim'    => $fim,
        ] + $data;
    }

    private function travarEspaco(int $espacoId): Espaco
    {
        return Espaco::whereKey($espacoId)->lockForUpdate()->firstOrFail();
    }

    private function verificarLimiteSemanalDoCurso(Aluno $aluno, Espaco $espaco, array $data): void
    {
        $limite = $espaco->limite_semanal_por_curso;

        if (! $limite) {
            return;
        }

        $segunda = Carbon::parse($data['data_reserva'])->startOfWeek(Carbon::MONDAY);
        $sexta = $segunda->copy()->addDays(4);

        $total = $this->reservas->contarPorSemanaDoCurso(
            $aluno->curso_id, $espaco->id, $segunda->toDateString(), $sexta->toDateString(),
        )->get($segunda->toDateString(), 0);

        if ($total >= $limite) {
            throw new RegraNegocioException(
                "O seu curso já atingiu o limite de {$limite} reserva(s) nesta semana para {$espaco->nome}.",
                409,
            );
        }
    }

    private function avisoDoLimiteSemanal(Aluno $aluno, Espaco $espaco, array $data): ?string
    {
        $limite = $espaco->limite_semanal_por_curso;

        if (! $limite || ! $aluno->curso_id) {
            return null;
        }

        $segunda = Carbon::parse($data['data_reserva'])->startOfWeek(Carbon::MONDAY);

        $total = $this->reservas->contarPorSemanaDoCurso(
            $aluno->curso_id, $espaco->id, $segunda->toDateString(), $segunda->copy()->addDays(4)->toDateString(),
        )->get($segunda->toDateString(), 0);

        return $total > $limite
            ? "O curso do aluno passou do limite semanal em {$espaco->nome}: {$total} de {$limite} nesta semana."
            : null;
    }

    private function verificarConflitoDeEspaco(array $data): void
    {
        if ($this->buscarConflito($data)) {
            throw new RegraNegocioException('Já existe uma reserva para esse espaço nesse horário.', 409);
        }
    }

    private function verificarDisponibilidadeEquipamentos(array $data): void
    {
        $itens = $data['equipamentos'] ?? [];

        if (! $itens) {
            return;
        }

        $ids = array_column($itens, 'id');

        $equipamentos = Equipamento::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $disponiveis = $this->disponibilidade->disponiveis(
            $equipamentos, $data['data_reserva'], $data['hora_inicio'], $data['hora_fim'],
        );

        $indisponiveis = [];

        foreach ($itens as $item) {
            $equipamento = $equipamentos[$item['id']];

            if ($item['quantidade'] > $disponiveis[$equipamento->id]) {
                $indisponiveis[] = [
                    'id'         => $equipamento->id,
                    'nome'       => $equipamento->nome,
                    'solicitado' => $item['quantidade'],
                    'disponivel' => $disponiveis[$equipamento->id],
                ];
            }
        }

        if ($indisponiveis) {
            throw new RegraNegocioException(
                'Alguns equipamentos não estão disponíveis nesse horário.',
                409,
                ['equipamentos_indisponiveis' => $indisponiveis],
            );
        }
    }

    private function anexarEquipamentos(Reserva $reserva, array $data): void
    {
        $itens = collect($data['equipamentos'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['id'] => ['quantidade' => $item['quantidade']]]);

        if ($itens->isNotEmpty()) {
            $reserva->equipamentos()->attach($itens->all());
        }
    }

    private function anexarSolicitacoes(Reserva $reserva, array $solicitacoes, ?User $admin = null): void
    {
        $situacao = $admin
            ? ['situacao_solicitacao' => SituacaoSolicitacaoConstant::APROVADA, 'respondida_em' => now(), 'respondida_por' => $admin->id]
            : ['situacao_solicitacao' => SituacaoSolicitacaoConstant::AGUARDANDO, 'responder_ate' => app(SolicitacaoService::class)->prazoDeResposta($reserva->inicio())];

        $itens = collect($solicitacoes)->mapWithKeys(fn ($item) => [$item['id'] => [
            'quantidade'              => $item['quantidade'],
            'solicitacao_obrigatoria' => (bool) ($item['obrigatorio'] ?? false),
            ...$situacao,
        ]]);

        if ($itens->isNotEmpty()) {
            $reserva->equipamentos()->attach($itens->all());
        }
    }

    private function buscarConflito(array $data): ?Reserva
    {
        return Reserva::where('espaco_id', $data['espaco_id'])
            ->where('data_reserva', $data['data_reserva'])
            ->where('hora_inicio', '<', $data['hora_fim'])
            ->where('hora_fim', '>', $data['hora_inicio'])
            ->where('status', StatusConstant::RESERVADO)
            ->first();
    }
}
