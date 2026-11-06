<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Mail\ReservaRevogadaMail;
use App\Models\Aluno;
use App\Models\DataBloqueada;
use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DatasBloqueadasTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->quadra = Espaco::factory()->quadra(null)->create();
    }

    private function reservar(string $data, string $horario = 'PRIMEIRO_HORARIO', ?Espaco $espaco = null)
    {
        return $this->postJson('/api/reservas', [
            'registro_academico' => Aluno::factory()->create()->registro_academico,
            'espaco_id'          => ($espaco ?? $this->quadra)->id,
            'data_reserva'       => $data,
            'horario'            => $horario,
        ]);
    }

    public function test_bloqueio_de_dia_inteiro_impede_reserva_com_o_motivo(): void
    {
        DataBloqueada::factory()->entre('2026-11-10', '2026-11-12')->create(['motivo' => 'Recesso acadêmico']);

        $this->reservar('2026-11-11')
            ->assertStatus(422)
            ->assertJsonPath('errors.data_reserva.0', 'Data indisponível em 11/11/2026: Recesso acadêmico.');
        $this->reservar('2026-11-13')->assertCreated();
    }

    public function test_bloqueio_de_um_espaco_nao_afeta_os_outros(): void
    {
        DataBloqueada::factory()->create(['data_inicio' => '2026-11-10', 'data_fim' => '2026-11-10', 'espaco_id' => $this->quadra->id]);

        $this->reservar('2026-11-10')->assertStatus(422);
        $this->reservar('2026-11-10', 'PRIMEIRO_HORARIO', Espaco::factory()->create())->assertCreated();
    }

    public function test_bloqueio_de_parte_do_dia_usa_sobreposicao(): void
    {
        DataBloqueada::factory()->entre('2026-11-10', '2026-11-11')->dasHoras('19:00', '20:30')->create(['motivo' => 'Manutenção da iluminação']);

        $this->reservar('2026-11-10')
            ->assertStatus(422)
            ->assertJsonPath('errors.data_reserva.0', 'Horário indisponível em 10/11/2026 (19:00–20:30): Manutenção da iluminação.');
        $this->reservar('2026-11-11')->assertStatus(422);
        $this->reservar('2026-11-10', 'SEGUNDO_HORARIO')->assertCreated();
    }

    public function test_janela_que_invade_os_dois_horarios_bloqueia_ambos(): void
    {
        DataBloqueada::factory()->dasHoras('20:00', '21:00')->create(['data_inicio' => '2026-11-10', 'data_fim' => '2026-11-10']);

        $this->reservar('2026-11-10', 'PRIMEIRO_HORARIO')->assertStatus(422);
        $this->reservar('2026-11-10', 'SEGUNDO_HORARIO')->assertStatus(422);
    }

    public function test_eventos_ignoram_bloqueios(): void
    {
        $this->comoAdmin();
        DataBloqueada::factory()->create(['data_inicio' => '2026-11-10', 'data_fim' => '2026-11-10']);

        $this->postJson('/api/admin/reservas/eventos', [
            'espaco_id'    => $this->quadra->id,
            'data_reserva' => '2026-11-10',
            'horario'      => 'PRIMEIRO_HORARIO',
            'descricao'    => 'Aula magna',
        ])->assertCreated();
    }

    public function test_criar_bloqueio_revoga_reservas_de_alunos_atingidas_e_avisa(): void
    {
        $admin = $this->comoAdmin();
        $atingida = Reserva::factory()->for($this->quadra)->create(['data_reserva' => '2026-11-10']);
        $outroHorario = Reserva::factory()->for($this->quadra)->noHorario(HorarioReservaConstant::SEGUNDO_HORARIO)->create(['data_reserva' => '2026-11-10']);
        $outroEspaco = Reserva::factory()->create(['data_reserva' => '2026-11-10']);
        $evento = Reserva::factory()->for($this->quadra)->evento()->create(['data_reserva' => '2026-11-11']);

        $this->postJson('/api/admin/datas-bloqueadas', [
            'data_inicio' => '2026-11-10',
            'data_fim'    => '2026-11-11',
            'hora_inicio' => '19:00',
            'hora_fim'    => '20:30',
            'espaco_id'   => $this->quadra->id,
            'motivo'      => 'Manutenção da quadra',
        ])->assertCreated()
            ->assertJsonPath('reservas_revogadas', 1)
            ->assertJsonPath('data.dia_inteiro', false)
            ->assertJsonPath('data.espaco.id', $this->quadra->id);

        $this->assertDatabaseHas('reservas', [
            'id'                      => $atingida->id, 'status' => 'REVOGADO', 'revogada_por' => $admin->id,
            'justificativa_revogacao' => 'Data bloqueada: Manutenção da quadra',
        ]);
        foreach ([$outroHorario, $outroEspaco, $evento] as $reserva) {
            $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'status' => 'RESERVADO']);
        }
        Mail::assertQueued(ReservaRevogadaMail::class, 1);
        Mail::assertQueued(ReservaRevogadaMail::class, fn ($mail) => $mail->hasTo($atingida->aluno->email));
    }

    public function test_validacoes_do_bloqueio(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/datas-bloqueadas', [
            'data_inicio' => '2026-11-12',
            'data_fim'    => '2026-11-10',
            'hora_inicio' => '20:00',
            'motivo'      => 'Teste',
        ])->assertStatus(422)->assertJsonValidationErrors(['data_fim', 'hora_fim']);

        $this->postJson('/api/admin/datas-bloqueadas', [
            'data_inicio' => '2026-11-10',
            'data_fim'    => '2026-11-10',
            'hora_inicio' => '21:00',
            'hora_fim'    => '20:00',
            'motivo'      => 'Teste',
        ])->assertStatus(422)->assertJsonValidationErrors('hora_fim');
    }

    public function test_edita_so_o_motivo_e_exclui(): void
    {
        $this->comoAdmin();
        $bloqueio = DataBloqueada::factory()->create();

        $this->patchJson("/api/admin/datas-bloqueadas/{$bloqueio->id}", ['motivo' => 'Semana acadêmica', 'data_fim' => '2026-12-31'])
            ->assertOk()
            ->assertJsonPath('data.motivo', 'Semana acadêmica')
            ->assertJsonPath('data.data_fim', $bloqueio->data_fim->toDateString());

        $this->deleteJson("/api/admin/datas-bloqueadas/{$bloqueio->id}")->assertNoContent();
        $this->assertDatabaseCount('datas_bloqueadas', 0);
    }

    public function test_listagem_filtra_por_periodo_e_espaco(): void
    {
        $this->comoAdmin();
        DataBloqueada::factory()->entre('2026-11-10', '2026-11-10')->create();
        DataBloqueada::factory()->entre('2026-12-01', '2026-12-05')->create(['espaco_id' => $this->quadra->id]);
        DataBloqueada::factory()->entre('2026-12-02', '2026-12-02')->create(['espaco_id' => Espaco::factory()->create()->id]);

        $this->getJson('/api/admin/datas-bloqueadas')->assertJsonCount(3, 'data');
        $this->getJson('/api/admin/datas-bloqueadas?de=2026-12-01&ate=2026-12-31')->assertJsonCount(2, 'data');
        $this->getJson("/api/admin/datas-bloqueadas?espaco_id={$this->quadra->id}")->assertJsonCount(2, 'data');
    }

    public function test_rotas_exigem_login(): void
    {
        $this->getJson('/api/admin/datas-bloqueadas')->assertUnauthorized();
    }
}
