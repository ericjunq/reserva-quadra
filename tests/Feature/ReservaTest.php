<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Espaco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservaTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Aluno $aluno, Espaco $espaco, array $extra = []): array
    {
        return array_merge([
            'registro_academico' => $aluno->registro_academico,
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
        ], $extra);
    }

    public function test_aluno_reserva_um_espaco(): void
    {
        $aluno = Aluno::factory()->create();
        $espaco = Espaco::factory()->create();

        $this->postJson('/api/reservas', $this->payload($aluno, $espaco))
            ->assertCreated()
            ->assertJsonPath('data.status', 'RESERVADO')
            ->assertJsonPath('data.tipo', 'ALUNO')
            ->assertJsonPath('data.data_reserva', '2026-11-10')
            ->assertJsonPath('data.hora_inicio', '19:00')
            ->assertJsonPath('data.hora_fim', '20:30');
    }

    public function test_ra_inexistente_e_espaco_inativo_sao_recusados(): void
    {
        $espaco = Espaco::factory()->inativo()->create();

        $this->postJson('/api/reservas', [
            'registro_academico' => '0000000000',
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertStatus(422)->assertJsonValidationErrors(['registro_academico', 'espaco_id']);
    }

    public function test_mesmo_espaco_e_horario_da_conflito(): void
    {
        $espaco = Espaco::factory()->create();

        $this->postJson('/api/reservas', $this->payload(Aluno::factory()->create(), $espaco))->assertCreated();
        $this->postJson('/api/reservas', $this->payload(Aluno::factory()->create(), $espaco))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe uma reserva para esse espaço nesse horário.');
    }

    public function test_outro_horario_ou_outro_espaco_nao_conflitam(): void
    {
        $aluno = Aluno::factory()->create();
        [$a, $b] = Espaco::factory()->count(2)->create();

        $this->postJson('/api/reservas', $this->payload($aluno, $a))->assertCreated();
        $this->postJson('/api/reservas', $this->payload($aluno, $a, ['horario' => 'SEGUNDO_HORARIO']))->assertCreated();
        $this->postJson('/api/reservas', $this->payload($aluno, $b))->assertCreated();
    }
}
