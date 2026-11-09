<?php

namespace App\Services;

use App\Constants\ModoHorarioConstant;
use App\Constants\StatusConstant;
use App\Constants\TipoEspacoConstant;
use App\Exceptions\RegraNegocioException;
use App\Models\Auditoria;
use App\Models\Espaco;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EspacoService
{
    public function listarPaginado(int $porPagina = 15, ?TipoEspacoConstant $tipo = null): LengthAwarePaginator
    {
        return Espaco::with(Auditoria::RELACOES)
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('nome')
            ->paginate($porPagina);
    }

    public function listarAtivos(?TipoEspacoConstant $tipo = null): Collection
    {
        return Espaco::query()
            ->where('ativo', true)
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderBy('nome')
            ->get();
    }

    public function criar(array $data): Espaco
    {
        return Espaco::create($this->limparConfiguracaoFixa($data));
    }

    public function atualizar(Espaco $espaco, array $data): Espaco
    {
        if (array_key_exists('ativo', $data) && ! $data['ativo']) {
            $this->verificarReservasFuturas($espaco);
        }

        $novoModo = isset($data['modo_horario']) ? ModoHorarioConstant::from($data['modo_horario']) : $espaco->modo_horario;

        if ($novoModo !== $espaco->modo_horario) {
            $this->verificarReservasFuturas($espaco, 'trocar o modo de horário');
        }

        $espaco->update($this->limparConfiguracaoFixa(['modo_horario' => $novoModo->value] + $data));

        return $espaco;
    }

    private function limparConfiguracaoFixa(array $data): array
    {
        if (($data['modo_horario'] ?? ModoHorarioConstant::FIXO->value) !== ModoHorarioConstant::FIXO->value) {
            return $data;
        }

        return ['abre_as' => null, 'fecha_as' => null, 'duracao_maxima_minutos' => null] + $data;
    }

    public function desativar(Espaco $espaco): void
    {
        $this->verificarReservasFuturas($espaco);

        $espaco->update(['ativo' => false]);
    }

    private function verificarReservasFuturas(Espaco $espaco, string $acao = 'desativá-lo'): void
    {
        $quantidade = $espaco->reservas()
            ->where('status', StatusConstant::RESERVADO)
            ->where('data_reserva', '>=', today()->toDateString())
            ->count();

        if ($quantidade > 0) {
            throw new RegraNegocioException(
                "O espaço possui {$quantidade} reserva(s) futura(s). Revogue-as antes de {$acao}.",
                409,
            );
        }
    }
}
