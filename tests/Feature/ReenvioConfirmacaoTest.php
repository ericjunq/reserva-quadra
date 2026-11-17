<?php

namespace Tests\Feature;

use App\Constants\FinalidadeTokenConstant;
use App\Constants\HorarioReservaConstant;
use App\Mail\PedidoConfirmacaoMail;
use App\Models\Aluno;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\TokenReserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReenvioConfirmacaoTest extends TestCase
{
    use RefreshDatabase;

    private Aluno $aluno;

    private int $reservaId;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->aluno = Aluno::factory()->create(['email' => 'maria@email.com']);
        $this->reservaId = $this->postJson('/api/reservas', [
            'registro_academico' => $this->aluno->registro_academico,
            'espaco_id'          => Espaco::factory()->quadra()->create()->id,
            'data_reserva'       => '2026-11-12',
            'horario'            => HorarioReservaConstant::PRIMEIRO_HORARIO->value,
        ])->json('data.id');
    }

    private function reenviar(array $extra = [])
    {
        return $this->postJson('/api/minhas-reservas/reenviar-confirmacao', [
            'registro_academico' => $this->aluno->registro_academico,
            'email'              => 'MARIA@email.com',
            'reserva_id'         => $this->reservaId,
            ...$extra,
        ]);
    }

    private function linkConfirmar(PedidoConfirmacaoMail $mail): string
    {
        return basename($mail->linkConfirmacao);
    }

    public function test_reenvia_com_links_novos_e_invalida_os_anteriores_sem_mudar_o_prazo(): void
    {
        $prazo = Reserva::find($this->reservaId)->confirmar_ate;
        $antigo = $this->linkConfirmar(Mail::queued(PedidoConfirmacaoMail::class)->first());

        $this->travel(6)->minutes();
        $this->reenviar()->assertOk()
            ->assertJsonPath('message', 'Enviamos o email de confirmação de novo. Os links do email anterior não valem mais.');

        Mail::assertQueuedCount(2);
        $novo = $this->linkConfirmar(Mail::queued(PedidoConfirmacaoMail::class)->last());
        $this->assertNotSame($antigo, $novo);
        $this->getJson("/api/reservas/confirmacao/{$antigo}")->assertNotFound();
        $this->getJson("/api/reservas/confirmacao/{$novo}")->assertOk();
        $this->assertSame(1, TokenReserva::where('finalidade', FinalidadeTokenConstant::CANCELAR)->whereNull('usado_em')->count());
        $this->assertTrue(Reserva::find($this->reservaId)->confirmar_ate->equalTo($prazo));
    }

    public function test_espera_5_minutos_e_no_maximo_3_reenvios(): void
    {
        $this->reenviar()->assertStatus(429)->assertJsonPath('message', 'Aguarde 5 minuto(s) para pedir o email de novo.');

        foreach (range(1, 3) as $vez) {
            $this->travel(6)->minutes();
            $this->reenviar()->assertOk();
        }

        $this->travel(6)->minutes();
        $this->reenviar()->assertStatus(429)
            ->assertJsonPath('message', 'Você já pediu o email de novo 3 vezes. Se ele não chegar, procure a coordenação.');
    }

    public function test_dados_errados_ou_reserva_de_outro_aluno_respondem_igual(): void
    {
        $this->travel(6)->minutes();
        $outra = Reserva::factory()->create();

        $this->reenviar(['email' => 'outro@email.com'])->assertNotFound()
            ->assertJsonPath('message', 'Não encontramos reservas para esses dados.');
        $this->reenviar(['reserva_id' => $outra->id])->assertNotFound()
            ->assertJsonPath('message', 'Não encontramos reservas para esses dados.');
        $this->reenviar(['registro_academico' => '123'])->assertUnprocessable();
    }

    public function test_reserva_ja_confirmada_nao_reenvia(): void
    {
        Reserva::find($this->reservaId)->update(['confirmada_em' => now()]);
        $this->travel(6)->minutes();

        $this->reenviar()->assertStatus(409)->assertJsonPath('message', 'Esta reserva não está aguardando confirmação.');
    }
}
