<?php

namespace App\Services;

use App\Constants\HorarioReservaConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Exceptions\RegraNegocioException;
use App\Mail\ReservaRevogadaMail;
use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReservaService
{
    public function __construct(private DisponibilidadeEquipamentoRepository $disponibilidade) {}

    public const RELACOES_ADMIN = ['aluno.curso', 'espaco', 'equipamentos', 'criador', 'revogador'];

    public function listar(array $filtros, string $ordem = 'asc', int $porPagina = 15): LengthAwarePaginator
    {
        return $this->filtrar($filtros)
            ->with(self::RELACOES_ADMIN)
            ->orderBy('data_reserva', $ordem)
            ->orderBy('hora_inicio', $ordem)
            ->orderBy('id', $ordem)
            ->paginate($porPagina);
    }

    public function totaisPorStatus(array $filtros): array
    {
        unset($filtros['status']);

        $totais = $this->filtrar($filtros)
            ->toBase()
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as total')
            ->pluck('total', 'status');

        return collect(StatusConstant::cases())
            ->mapWithKeys(fn (StatusConstant $status) => [$status->value => (int) ($totais[$status->value] ?? 0)])
            ->all();
    }

    private function filtrar(array $filtros): Builder
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
            ->when($filtros['busca'] ?? null, fn ($q, $busca) => $q->whereHas('aluno', fn ($a) => $a->where(fn ($w) => $w
                ->where('nome_completo', 'like', "%{$busca}%")
                ->orWhere('registro_academico', 'like', "%{$busca}%"))));
    }

    public function create(array $data): Reserva
    {
        $aluno = Aluno::where('registro_academico', $data['registro_academico'])->firstOrFail();
        $data = $this->resolverIntervalo($data);

        return DB::transaction(function () use ($aluno, $data) {
            $espaco = $this->travarEspaco($data['espaco_id']);
            $this->verificarConflitoDeEspaco($data);
            $this->verificarLimiteSemanalDoCurso($aluno, $espaco, $data);
            $this->verificarDisponibilidadeEquipamentos($data);

            $reserva = $aluno->reservas()->create([
                'data_reserva' => $data['data_reserva'],
                'horario'      => $data['horario'],
                'hora_inicio'  => $data['hora_inicio'],
                'hora_fim'     => $data['hora_fim'],
                'espaco_id'    => $data['espaco_id'],
                'tipo'         => TipoReservaConstant::ALUNO,
            ]);

            $this->anexarEquipamentos($reserva, $data);

            return $reserva->load('equipamentos');
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
                'data_reserva' => $data['data_reserva'],
                'horario'      => $data['horario'],
                'hora_inicio'  => $data['hora_inicio'],
                'hora_fim'     => $data['hora_fim'],
                'espaco_id'    => $data['espaco_id'],
                'descricao' => $data['descricao'],
                'user_id'   => $admin->id,
                'tipo'      => TipoReservaConstant::EVENTO,
            ]);

            $this->anexarEquipamentos($reserva, $data);

            return $reserva->load('equipamentos');
        });
    }

    public function revogar(Reserva $reserva, ?User $admin, string $justificativa): Reserva
    {
        return DB::transaction(function () use ($reserva, $admin, $justificativa) {
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

            if ($reserva->aluno) {
                Mail::to($reserva->aluno->email)->queue(
                    (new ReservaRevogadaMail($reserva->load('aluno', 'espaco')))->afterCommit()
                );
            }

            return $reserva;
        });
    }

    private function resolverIntervalo(array $data): array
    {
        $horario = HorarioReservaConstant::from($data['horario']);

        return $data + [
            'hora_inicio' => $horario->horaInicio(),
            'hora_fim'    => $horario->horaFim(),
        ];
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

        $total = Reserva::query()
            ->join('alunos', 'alunos.id', '=', 'reservas.aluno_id')
            ->where('alunos.curso_id', $aluno->curso_id)
            ->where('reservas.espaco_id', $espaco->id)
            ->where('reservas.tipo', TipoReservaConstant::ALUNO)
            ->where('reservas.status', StatusConstant::RESERVADO)
            ->whereDate('reservas.data_reserva', '>=', $segunda->toDateString())
            ->whereDate('reservas.data_reserva', '<=', $sexta->toDateString())
            ->count();

        if ($total >= $limite) {
            $curso = $aluno->curso->nome;

            throw new RegraNegocioException(
                "O curso {$curso} já tem {$total} reserva(s) nesta semana para {$espaco->nome}. O limite é {$limite} por semana.",
                409,
            );
        }
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
                    'id'          => $equipamento->id,
                    'nome'        => $equipamento->nome,
                    'solicitado'  => $item['quantidade'],
                    'disponivel'  => $disponiveis[$equipamento->id],
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

    private function buscarConflito(array $data): ?Reserva
    {
        return Reserva::where('espaco_id', $data['espaco_id'])
            ->whereDate('data_reserva', $data['data_reserva'])
            ->where('hora_inicio', '<', $data['hora_fim'])
            ->where('hora_fim', '>', $data['hora_inicio'])
            ->where('status', StatusConstant::RESERVADO)
            ->first();
    }
}
