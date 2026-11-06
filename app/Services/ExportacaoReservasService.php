<?php

namespace App\Services;

use App\Constants\StatusConstant;
use App\Models\Reserva;
use App\Repositories\ReservaRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportacaoReservasService
{
    public const LOTE = 500;

    public const COLUNAS_OBRIGATORIAS = [
        'id'          => 'ID',
        'data'        => 'Data',
        'horario'     => 'Horário',
        'espaco'      => 'Espaço',
        'tipo'        => 'Tipo',
        'status'      => 'Status',
        'responsavel' => 'Responsável',
    ];

    public const COLUNAS_OPCIONAIS = [
        'tipo_espaco'        => ['Tipo do espaço'],
        'registro_academico' => ['RA'],
        'email'              => ['Email'],
        'curso'              => ['Curso'],
        'equipamentos'       => ['Equipamentos'],
        'descricao'          => ['Descrição'],
        'criada_em'          => ['Criada em'],
        'revogacao'          => ['Revogada por', 'Revogada em', 'Justificativa'],
    ];

    public function __construct(private ReservaRepository $reservas) {}

    public function colunasDisponiveis(): array
    {
        return [
            'obrigatorias' => collect(self::COLUNAS_OBRIGATORIAS)
                ->map(fn ($titulo, $chave) => ['chave' => $chave, 'titulo' => $titulo])->values()->all(),
            'opcionais' => collect(self::COLUNAS_OPCIONAIS)
                ->map(fn ($titulos, $chave) => ['chave' => $chave, 'titulo' => implode(', ', $titulos)])->values()->all(),
        ];
    }

    public function exportar(array $filtros, array $colunas, string $ordem = 'asc'): StreamedResponse
    {
        $colunas = array_values(array_intersect(array_keys(self::COLUNAS_OPCIONAIS), $colunas));

        $query = $this->reservas->ordenar($this->reservas->filtrar($filtros), $ordem)
            ->with($this->relacoes($colunas));

        return response()->streamDownload(function () use ($query, $colunas) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\u{FEFF}");
            $this->escreverLinha($saida, $this->cabecalho($colunas));

            foreach ($query->lazy(self::LOTE) as $reserva) {
                $this->escreverLinha($saida, $this->linha($reserva, $colunas));
            }

            fclose($saida);
        }, $this->nomeDoArquivo($filtros), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function relacoes(array $colunas): array
    {
        $relacoes = ['espaco', 'aluno', 'criador'];

        if (in_array('curso', $colunas, true)) {
            $relacoes[] = 'aluno.curso';
        }

        if (in_array('equipamentos', $colunas, true)) {
            $relacoes[] = 'equipamentos';
        }

        if (in_array('revogacao', $colunas, true)) {
            $relacoes[] = 'revogador';
        }

        return $relacoes;
    }

    private function cabecalho(array $colunas): array
    {
        $cabecalho = array_values(self::COLUNAS_OBRIGATORIAS);

        foreach ($colunas as $coluna) {
            array_push($cabecalho, ...self::COLUNAS_OPCIONAIS[$coluna]);
        }

        return $cabecalho;
    }

    private function linha(Reserva $reserva, array $colunas): array
    {
        $linha = [
            $reserva->id,
            $reserva->data_reserva->format('d/m/Y'),
            substr($reserva->hora_inicio, 0, 5).'–'.substr($reserva->hora_fim, 0, 5),
            $reserva->espaco->nome,
            $reserva->tipo->value,
            $reserva->status->value,
            $reserva->aluno?->nome_completo ?? $reserva->criador?->name,
        ];

        $revogada = $reserva->status === StatusConstant::REVOGADO;

        foreach ($colunas as $coluna) {
            array_push($linha, ...match ($coluna) {
                'tipo_espaco'        => [$reserva->espaco->tipo->value],
                'registro_academico' => [$reserva->aluno?->registro_academico],
                'email'              => [$reserva->aluno?->email],
                'curso'              => [$reserva->aluno?->curso?->nome],
                'equipamentos'       => [$reserva->equipamentos
                    ->map(fn ($e) => "{$e->nome} x{$e->pivot->quantidade}")->implode(', ')],
                'descricao' => [$reserva->descricao],
                'criada_em' => [$reserva->created_at?->format('d/m/Y H:i')],
                'revogacao' => [
                    $revogada ? $reserva->revogador?->name : null,
                    $revogada ? $reserva->revogada_em?->format('d/m/Y H:i') : null,
                    $revogada ? $reserva->justificativa_revogacao : null,
                ],
            });
        }

        return $linha;
    }

    private function escreverLinha($saida, array $valores): void
    {
        fputcsv($saida, array_map(fn ($valor) => $this->protegerContraFormula($valor), $valores), ';', '"', '');
    }

    private function protegerContraFormula(mixed $valor): string
    {
        $texto = (string) ($valor ?? '');

        return $texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'{$texto}" : $texto;
    }

    private function nomeDoArquivo(array $filtros): string
    {
        $de = $filtros['de'] ?? null;
        $ate = $filtros['ate'] ?? null;

        return match (true) {
            $de && $ate => "reservas_{$de}_a_{$ate}.csv",
            (bool) $de  => "reservas_a_partir_de_{$de}.csv",
            default     => "reservas_ate_{$ate}.csv",
        };
    }
}
