<?php

namespace App\Services;

use App\Constants\OrigemBloqueioConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoReservaConstant;
use App\Models\DataBloqueada;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Yasumi\Holiday;
use Yasumi\Yasumi;

class DataBloqueadaService
{
    private const FERIADOS_NACIONAIS_EXTRAS = ['goodFriday'];

    private const PONTOS_FACULTATIVOS = ['carnavalMonday', 'carnavalTuesday', 'ashWednesday', 'corpusChristi'];

    public function __construct(private ReservaService $reservaService) {}

    public function listarPaginado(int $porPagina = 15, ?string $de = null, ?string $ate = null, ?int $espacoId = null): LengthAwarePaginator
    {
        return DataBloqueada::with('espaco')
            ->when($de, fn ($q) => $q->where('data_fim', '>=', $de))
            ->when($ate, fn ($q) => $q->where('data_inicio', '<=', $ate))
            ->when($espacoId, fn ($q) => $q->where(fn ($q) => $q->whereNull('espaco_id')->orWhere('espaco_id', $espacoId)))
            ->orderBy('data_inicio')
            ->paginate($porPagina);
    }

    public function criar(array $data, ?User $admin, OrigemBloqueioConstant $origem = OrigemBloqueioConstant::MANUAL): array
    {
        return DB::transaction(function () use ($data, $admin, $origem) {
            $bloqueio = DataBloqueada::create($data + [
                'origem'     => $origem,
                'criado_por' => $admin?->id,
            ]);

            $revogadas = $this->revogarReservasAtingidas($bloqueio, $admin);

            return [$bloqueio->load('espaco'), $revogadas];
        });
    }

    public function atualizarMotivo(DataBloqueada $bloqueio, string $motivo): DataBloqueada
    {
        $bloqueio->update(['motivo' => $motivo]);

        return $bloqueio->load('espaco');
    }

    public function excluir(DataBloqueada $bloqueio): void
    {
        $bloqueio->delete();
    }

    public function importarFeriados(int $ano, bool $incluirFacultativos = false): int
    {
        $importados = 0;

        foreach (Yasumi::create('Brazil', $ano, 'pt_BR') as $feriado) {
            if (! $this->deveImportar($feriado, $incluirFacultativos)) {
                continue;
            }

            $data = $feriado->format('Y-m-d');

            $jaExiste = DataBloqueada::where('origem', OrigemBloqueioConstant::FERIADO)
                ->where('data_inicio', $data)
                ->exists();

            if ($jaExiste) {
                continue;
            }

            $this->criar([
                'data_inicio' => $data,
                'data_fim'    => $data,
                'motivo'      => $feriado->getName(),
            ], null, OrigemBloqueioConstant::FERIADO);

            $importados++;
        }

        return $importados;
    }

    private function deveImportar(Holiday $feriado, bool $incluirFacultativos): bool
    {
        return $feriado->getType() === Holiday::TYPE_OFFICIAL
            || in_array($feriado->getKey(), self::FERIADOS_NACIONAIS_EXTRAS, true)
            || ($incluirFacultativos && in_array($feriado->getKey(), self::PONTOS_FACULTATIVOS, true));
    }

    private function revogarReservasAtingidas(DataBloqueada $bloqueio, ?User $admin): int
    {
        $inicio = max($bloqueio->data_inicio->toDateString(), today()->toDateString());

        $reservas = Reserva::query()
            ->where('status', StatusConstant::RESERVADO)
            ->where('tipo', TipoReservaConstant::ALUNO)
            ->where('data_reserva', '>=', $inicio)
            ->where('data_reserva', '<=', $bloqueio->data_fim->toDateString())
            ->when($bloqueio->espaco_id, fn ($q) => $q->where('espaco_id', $bloqueio->espaco_id))
            ->when(! $bloqueio->diaInteiro(), fn ($q) => $q
                ->where('hora_inicio', '<', $bloqueio->hora_fim)
                ->where('hora_fim', '>', $bloqueio->hora_inicio))
            ->get();

        foreach ($reservas as $reserva) {
            $this->reservaService->revogar($reserva, $admin, "Data bloqueada: {$bloqueio->motivo}");
        }

        return $reservas->count();
    }
}
