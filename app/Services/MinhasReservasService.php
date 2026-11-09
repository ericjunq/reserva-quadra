<?php

namespace App\Services;

use App\Exceptions\RegraNegocioException;
use App\Models\Aluno;
use App\Repositories\ReservaRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class MinhasReservasService
{
    public const DADOS_NAO_ENCONTRADOS = 'Não encontramos reservas para esses dados.';

    public function __construct(private ReservaRepository $reservas) {}

    public function listar(string $registroAcademico, string $email, bool $somenteFuturas, int $porPagina): LengthAwarePaginator
    {
        $aluno = Aluno::query()
            ->where('registro_academico', $registroAcademico)
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->whereNull('anonimizado_em')
            ->first();

        if (! $aluno) {
            throw new RegraNegocioException(self::DADOS_NAO_ENCONTRADOS, 404);
        }

        return $this->reservas
            ->ordenar($this->reservas->doAluno($aluno->id, $somenteFuturas), $somenteFuturas ? 'asc' : 'desc')
            ->with(['espaco', 'equipamentos'])
            ->paginate($porPagina);
    }
}
