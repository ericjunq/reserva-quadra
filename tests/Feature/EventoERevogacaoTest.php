<?php

namespace Tests\Feature;

use App\Mail\ReservaRevogadaMail;
use App\Models\Equipamento;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventoERevogacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function evento(Reserva $reserva, array $extra = []): array
    {
        return array_merge([
            'espaco_id'    => $reserva->espaco_id,
            'data_reserva' => $reserva->data_reserva->toDateString(),
            'horario'      => 'PRIMEIRO_HORARIO',
            'descricao'    => 'Torneio das atléticas',
        ], $extra);
    }

    public function test_evento_em_horario_ocupado_sem_justificativa_retorna_conflito(): void
    {
        $this->comoAdmin();
        $reserva = Reserva::factory()->create();

        $this->postJson('/api/admin/reservas/eventos', $this->evento($reserva))
            ->assertStatus(409)
            ->assertJsonPath('dados.reserva_conflitante_id', $reserva->id);
    }

    public function test_evento_com_justificativa_revoga_reserva_e_avisa_aluno(): void
    {
        $admin = $this->comoAdmin();
        $reserva = Reserva::factory()->create();

        $this->postJson('/api/admin/reservas/eventos', $this->evento($reserva, ['justificativa' => 'Evento institucional prioritário']))
            ->assertCreated()
            ->assertJsonPath('data.tipo', 'EVENTO');

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'status' => 'REVOGADO', 'revogada_por' => $admin->id]);
        Mail::assertQueued(ReservaRevogadaMail::class, fn ($mail) => $mail->hasTo($reserva->aluno->email));
    }

    public function test_evento_usa_equipamentos_liberados_pela_revogacao(): void
    {
        $this->comoAdmin();
        $reserva = Reserva::factory()->create();
        $bola = Equipamento::factory()->create(['espaco_id' => $reserva->espaco_id, 'quantidade_total' => 3]);
        $reserva->equipamentos()->attach($bola->id, ['quantidade' => 3]);

        $this->postJson('/api/admin/reservas/eventos', $this->evento($reserva, [
            'justificativa' => 'Evento institucional prioritário',
            'equipamentos'  => [['id' => $bola->id, 'quantidade' => 3]],
        ]))->assertCreated()->assertJsonPath('data.equipamentos.0.quantidade', 3);
    }

    public function test_revogar_exige_justificativa_e_reserva_ativa(): void
    {
        $this->comoAdmin();
        $reserva = Reserva::factory()->create();

        $this->postJson("/api/admin/reservas/{$reserva->id}/revogar", [])->assertStatus(422);
        $this->postJson("/api/admin/reservas/{$reserva->id}/revogar", ['justificativa' => 'Manutenção da quadra'])
            ->assertOk()
            ->assertJsonPath('data.status', 'REVOGADO')
            ->assertJsonPath('data.justificativa_revogacao', 'Manutenção da quadra');
        $this->postJson("/api/admin/reservas/{$reserva->id}/revogar", ['justificativa' => 'Manutenção da quadra'])->assertStatus(409);
    }

    public function test_nao_revoga_reserva_que_ja_passou(): void
    {
        $this->comoAdmin();
        $reserva = Reserva::factory()->create(['data_reserva' => '2026-11-06']);

        $this->postJson("/api/admin/reservas/{$reserva->id}/revogar", ['justificativa' => 'Teste de reserva antiga'])->assertStatus(422);
    }

    public function test_email_de_revogacao_mostra_o_motivo(): void
    {
        $reserva = Reserva::factory()->create(['justificativa_revogacao' => 'Manutenção da quadra']);

        $html = (new ReservaRevogadaMail($reserva->load('aluno', 'espaco')))->render();

        $this->assertStringContainsString('Manutenção da quadra', $html);
        $this->assertStringContainsString('10/11/2026', $html);
    }
}
