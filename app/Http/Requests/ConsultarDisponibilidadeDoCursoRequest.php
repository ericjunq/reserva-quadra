<?php

namespace App\Http\Requests;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Espaco;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;

class ConsultarDisponibilidadeDoCursoRequest extends ConsultarDisponibilidadeRequest
{
    private ?Aluno $aluno = null;

    public function authorize(): bool
    {
        abort_unless($this->espaco()->ativo, 404);

        return true;
    }

    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), 'curso_id'),
            'registro_academico' => ['bail', 'required', 'string', 'size:10', function (string $atributo, mixed $valor, Closure $falhar) {
                if ($this->espaco()->limite_semanal_por_curso === null) {
                    $falhar('Este espaço não tem limite semanal por curso. O RA só é pedido na hora de reservar.');
                }
            }],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $this->aluno = Aluno::with('curso')
                    ->where('registro_academico', $this->input('registro_academico'))
                    ->where('ativo', true)
                    ->first();

                if (! $this->aluno) {
                    $validator->errors()->add('registro_academico', 'Não encontramos um aluno ativo com este RA.');
                }
            },
        ];
    }

    public function curso(): ?Curso
    {
        return $this->aluno?->curso;
    }

    private function espaco(): Espaco
    {
        return $this->route('espaco');
    }
}
