<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Constants\StatusConstant;
use App\Constants\TipoEspacoConstant;
use App\Exceptions\RegraNegocioException;
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
        return Espaco::create($data);
    }

    public function atualizar(Espaco $espaco, array $data): Espaco
    {
        if (array_key_exists('ativo', $data) && ! $data['ativo']) {
            $this->verificarReservasFuturas($espaco);
        }

        $espaco->update($data);

        return $espaco;
    }

    public function desativar(Espaco $espaco): void
    {
        $this->verificarReservasFuturas($espaco);

        $espaco->update(['ativo' => false]);
    }

    private function verificarReservasFuturas(Espaco $espaco): void
    {
        $quantidade = $espaco->reservas()
            ->where('status', StatusConstant::RESERVADO)
            ->whereDate('data_reserva', '>=', today())
            ->count();

        if ($quantidade > 0) {
            throw new RegraNegocioException(
                "O espaço possui {$quantidade} reserva(s) futura(s). Revogue-as antes de desativá-lo.",
                409,
            );
        }
    }
}
