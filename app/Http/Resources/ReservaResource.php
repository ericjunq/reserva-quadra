<?php

namespace App\Http\Resources;

use App\Constants\StatusConstant;
use Illuminate\Http\Request;
use App\Http\Resources\Concerns\ExibeAuditoria;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservaResource extends JsonResource
{
    use ExibeAuditoria;

    public function toArray(Request $request): array
    {
        $data = $this->data_reserva->format('Y-m-d');
        $revogada = $this->status === StatusConstant::REVOGADO;

        return [
            'id'             => $this->id,
            'data_reserva'   => $data,
            'horario'        => $this->horario?->value,
            'hora_inicio'    => substr($this->hora_inicio, 0, 5),
            'hora_fim'       => substr($this->hora_fim, 0, 5),
            'status'         => $this->status->value,
            'tipo'           => $this->tipo->value,
            'espaco_id'      => $this->espaco_id,
            'espaco'         => $this->whenLoaded('espaco', fn () => [
                'id'   => $this->espaco->id,
                'nome' => $this->espaco->nome,
                'tipo' => $this->espaco->tipo,
            ]),
            'aluno_id'       => $this->aluno_id,
            'aluno'          => $this->whenLoaded('aluno', fn () => $this->aluno ? [
                'id'                 => $this->aluno->id,
                'nome_completo'      => $this->aluno->nome_completo,
                'registro_academico' => $this->aluno->registro_academico,
                'email'              => $this->aluno->email,
                'curso'              => $this->aluno->relationLoaded('curso') && $this->aluno->curso
                    ? ['id' => $this->aluno->curso->id, 'nome' => $this->aluno->curso->nome]
                    : null,
            ] : null),
            'descricao'      => $this->descricao,
            'equipamentos'   => $this->whenLoaded('equipamentos', fn () => $this->equipamentos->map(fn ($e) => [
                'id'         => $e->id,
                'nome'       => $e->nome,
                'quantidade' => $e->pivot->quantidade,
            ])),
            'justificativa_revogacao' => $this->when($revogada, $this->justificativa_revogacao),
            'revogada_em'    => $this->when($revogada, $this->revogada_em),
            'revogada_por'   => $this->when($revogada && $this->relationLoaded('revogador'), fn () => $this->revogador ? [
                'id'   => $this->revogador->id,
                'nome' => $this->revogador->name,
            ] : null),
            $this->auditoria(),
        ];
    }
}
