<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LimiteSemanalTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    private Curso $ti;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quadra = Espaco::factory()->quadra(3)->create(['nome' => 'Quadra']);
        $this->ti = Curso::factory()->create(['nome' => 'TI']);
    }

    private function reservar(Curso $curso, string $data, ?Espaco $espaco = null)
    {
        return $this->postJson('/api/reservas', [
            'registro_academico' => Aluno::factory()->for($curso)->create()->registro_academico,
            'espaco_id'          => ($espaco ?? $this->quadra)->id,
            'data_reserva'       => $data,
            'horario'            => 'PRIMEIRO_HORARIO',
        ]);
    }

    public function test_curso_pode_reservar_a_quadra_ate_tres_vezes_na_semana(): void
    {
        $this->reservar($this->ti, '2026-11-09')->assertCreated();
        $this->reservar($this->ti, '2026-11-10')->assertCreated();
        $this->reservar($this->ti, '2026-11-11')->assertCreated();

        $this->reservar($this->ti, '2026-11-12')
            ->assertStatus(409)
            ->assertJsonPath('message', 'O seu curso já atingiu o limite de 3 reserva(s) nesta semana para Quadra.')
            ->assertJsonMissingPath('dados');
    }

    public function test_limite_e_por_curso_e_reinicia_na_semana_seguinte(): void
    {
        foreach (['2026-11-09', '2026-11-10', '2026-11-11'] as $data) {
            $this->reservar($this->ti, $data)->assertCreated();
        }

        $this->reservar(Curso::factory()->create(), '2026-11-12')->assertCreated();
        $this->reservar($this->ti, '2026-11-16')->assertCreated();
    }

    public function test_revogadas_e_eventos_nao_contam(): void
    {
        $aluno = Aluno::factory()->for($this->ti)->create();
        Reserva::factory()->for($this->quadra)->for($aluno)->revogada()->count(3)
            ->sequence(['data_reserva' => '2026-11-09'], ['data_reserva' => '2026-11-10'], ['data_reserva' => '2026-11-11'])
            ->create();
        Reserva::factory()->for($this->quadra)->evento()->noHorario(HorarioReservaConstant::SEGUNDO_HORARIO)->create(['data_reserva' => '2026-11-12']);

        $this->reservar($this->ti, '2026-11-12')->assertCreated();
    }

    public function test_espaco_sem_limite_nao_restringe(): void
    {
        $sala = Espaco::factory()->create();

        foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12'] as $data) {
            $this->reservar($this->ti, $data, $sala)->assertCreated();
        }
    }
}
