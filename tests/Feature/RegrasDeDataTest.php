<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Espaco;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegrasDeDataTest extends TestCase
{
    use RefreshDatabase;

    private function reservar(string $data, string $horario = 'PRIMEIRO_HORARIO')
    {
        return $this->postJson('/api/reservas', [
            'registro_academico' => Aluno::factory()->create()->registro_academico,
            'espaco_id'          => Espaco::factory()->create()->id,
            'data_reserva'       => $data,
            'horario'            => $horario,
        ]);
    }

    public function test_bloqueia_fim_de_semana_para_alunos(): void
    {
        $this->reservar('2026-11-14')->assertStatus(422)->assertJsonValidationErrors('data_reserva');
        $this->reservar('2026-11-15')->assertStatus(422)->assertJsonValidationErrors('data_reserva');
        $this->reservar('2026-11-13')->assertCreated();
    }

    public function test_eventos_podem_ser_no_fim_de_semana(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/reservas/eventos', [
            'espaco_id'    => Espaco::factory()->quadra()->create()->id,
            'data_reserva' => '2026-11-14',
            'horario'      => 'PRIMEIRO_HORARIO',
            'descricao'    => 'Campeonato das atléticas',
        ])->assertCreated();
    }

    public function test_antecedencia_maxima_de_dez_dias_uteis(): void
    {
        $this->reservar('2026-11-23')->assertCreated();
        $this->reservar('2026-11-24')->assertStatus(422)->assertJsonValidationErrors('data_reserva');
    }

    public function test_nao_aceita_data_passada(): void
    {
        $this->reservar('2026-11-06')->assertStatus(422)->assertJsonValidationErrors('data_reserva');
    }

    public function test_horario_de_hoje_aceito_ate_vinte_minutos_antes_do_fim(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 20:10'));
        $this->reservar('2026-11-09', 'PRIMEIRO_HORARIO')->assertCreated();

        $this->travelTo(Carbon::parse('2026-11-09 20:11'));
        $this->reservar('2026-11-09', 'PRIMEIRO_HORARIO')->assertStatus(422)->assertJsonValidationErrors('horario');
        $this->reservar('2026-11-09', 'SEGUNDO_HORARIO')->assertCreated();
        $this->reservar('2026-11-10', 'PRIMEIRO_HORARIO')->assertCreated();
    }
}
