<?php

namespace Tests\Feature;

use App\Constants\StatusConstant;
use App\Mail\PedidoConfirmacaoMail;
use App\Mail\ReservaCriadaMail;
use App\Mail\ReservaRevogadaMail;
use App\Models\Aluno;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\TokenReserva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ConfirmacaoTest extends TestCase
{
    use RefreshDatabase;

    private Aluno $aluno;

    private Espaco $quadra;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->aluno = Aluno::factory()->create(['email' => 'maria@email.com']);
        $this->quadra = Espaco::factory()->quadra(null)->create();
    }

    private function reservar(string $data): Reserva
    {
        $id = $this->postJson('/api/reservas', [
            'registro_academico' => $this->aluno->registro_academico,
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => $data,
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertCreated()->json('data.id');

        return Reserva::findOrFail($id);
    }

    private function reservaExistente(string $data, array $estado = []): Reserva
    {
        return Reserva::factory()->create(['data_reserva' => $data, 'aluno_id' => $this->aluno->id, ...$estado]);
    }

    private function pedido(): PedidoConfirmacaoMail
    {
        $pedido = null;

        Mail::assertQueued(PedidoConfirmacaoMail::class, function (PedidoConfirmacaoMail $mail) use (&$pedido) {
            $pedido = $mail;

            return true;
        });

        return $pedido;
    }

    private function token(string $link): string
    {
        return substr($link, strrpos($link, '/') + 1);
    }

    public function test_reserva_para_hoje_nasce_confirmada(): void
    {
        $reserva = $this->reservar('2026-11-09');

        $this->assertNotNull($reserva->confirmada_em);
        Mail::assertNotQueued(PedidoConfirmacaoMail::class);
        Mail::assertQueued(ReservaCriadaMail::class, fn (ReservaCriadaMail $mail) => $mail->pedidoConfirmacaoEm === null);
    }

    public function test_reserva_dentro_da_janela_ja_pede_confirmacao_na_criacao(): void
    {
        $reserva = $this->reservar('2026-11-11');

        $this->assertNotNull($reserva->confirmacao_enviada_em);
        $this->assertNull($reserva->confirmada_em);
        Mail::assertNotQueued(ReservaCriadaMail::class);

        $pedido = $this->pedido();
        $this->assertTrue($pedido->hasTo('maria@email.com'));
        $this->assertTrue($pedido->recemCriada);
        $this->assertSame('2026-11-10 23:59:59', $pedido->prazo->format('Y-m-d H:i:s'));
        $this->assertStringStartsWith('http://localhost:4200/reservas/confirmar/', $pedido->linkConfirmacao);
        $this->assertStringStartsWith('http://localhost:4200/reservas/cancelar/', $pedido->linkCancelamento);

        $confirmar = TokenReserva::where('reserva_id', $reserva->id)->where('finalidade', 'CONFIRMAR')->sole();
        $this->assertSame('2026-11-10 23:59:59', $confirmar->expira_em->format('Y-m-d H:i:s'));
    }

    public function test_reserva_fora_da_janela_avisa_quando_vira_o_pedido(): void
    {
        $reserva = $this->reservar('2026-11-12');

        $this->assertNull($reserva->confirmacao_enviada_em);
        Mail::assertNotQueued(PedidoConfirmacaoMail::class);
        Mail::assertQueued(ReservaCriadaMail::class, fn (ReservaCriadaMail $mail) => $mail->pedidoConfirmacaoEm?->toDateString() === '2026-11-10');
    }

    public function test_pedido_sai_dois_dias_uteis_antes(): void
    {
        $segunda = $this->reservaExistente('2026-11-16');

        $this->travelTo(Carbon::parse('2026-11-11 08:00'));
        Artisan::call('reservas:pedir-confirmacao');
        $this->assertNull($segunda->fresh()->confirmacao_enviada_em);

        $this->travelTo(Carbon::parse('2026-11-12 08:00'));
        Artisan::call('reservas:pedir-confirmacao');
        $this->assertNotNull($segunda->fresh()->confirmacao_enviada_em);

        $pedido = $this->pedido();
        $this->assertFalse($pedido->recemCriada);
        $this->assertSame('2026-11-15 23:59:59', $pedido->prazo->format('Y-m-d H:i:s'));

        Artisan::call('reservas:pedir-confirmacao');
        Mail::assertQueued(PedidoConfirmacaoMail::class, 1);
    }

    public function test_pedido_ignora_eventos_revogadas_confirmadas_e_do_dia(): void
    {
        $this->reservaExistente('2026-11-10', ['aluno_id' => null, 'tipo' => 'EVENTO', 'descricao' => 'Palestra']);
        $this->reservaExistente('2026-11-10', ['status' => 'REVOGADO']);
        $this->reservaExistente('2026-11-11', ['confirmada_em' => now()]);
        $this->reservaExistente('2026-11-09');

        $this->assertSame(0, Artisan::call('reservas:pedir-confirmacao'));
        Mail::assertNotQueued(PedidoConfirmacaoMail::class);
    }

    public function test_confirmacao_pelo_link(): void
    {
        $reserva = $this->reservar('2026-11-11');
        $token = $this->token($this->pedido()->linkConfirmacao);

        $this->getJson("/api/reservas/confirmacao/{$token}")->assertOk()
            ->assertJsonPath('pode_confirmar', true)
            ->assertJsonPath('data.espaco.id', $this->quadra->id)
            ->assertJsonMissingPath('data.aluno');

        $this->postJson("/api/reservas/confirmacao/{$token}")->assertOk()
            ->assertJsonPath('data.status', 'RESERVADO');

        $this->assertNotNull($reserva->fresh()->confirmada_em);
        $this->assertNull($reserva->fresh()->atualizado_por);

        $this->postJson("/api/reservas/confirmacao/{$token}")->assertNotFound()
            ->assertExactJson(['message' => 'Link inválido ou expirado.']);
    }

    public function test_link_de_confirmacao_vence_no_fim_do_dia_anterior(): void
    {
        $this->reservar('2026-11-11');
        $token = $this->token($this->pedido()->linkConfirmacao);

        $this->travelTo(Carbon::parse('2026-11-10 23:59'));
        $this->getJson("/api/reservas/confirmacao/{$token}")->assertOk();

        $this->travelTo(Carbon::parse('2026-11-11 00:00'));
        $this->postJson("/api/reservas/confirmacao/{$token}")->assertNotFound();
    }

    public function test_cancelar_pelo_email_de_confirmacao(): void
    {
        $reserva = $this->reservar('2026-11-11');
        $token = $this->token($this->pedido()->linkCancelamento);

        $this->postJson("/api/reservas/cancelamento/{$token}")->assertOk();

        $this->assertSame(StatusConstant::CANCELADO, $reserva->fresh()->status);
    }

    public function test_revoga_as_nao_confirmadas_no_dia_da_reserva(): void
    {
        $naoConfirmada = $this->reservaExistente('2026-11-11', ['confirmacao_enviada_em' => now()]);
        $confirmada = $this->reservaExistente('2026-11-11', ['confirmacao_enviada_em' => now(), 'confirmada_em' => now()]);
        $semPedido = $this->reservaExistente('2026-11-11');
        $outroDia = $this->reservaExistente('2026-11-12', ['confirmacao_enviada_em' => now()]);

        $this->travelTo(Carbon::parse('2026-11-11 00:05'));
        $this->assertSame(0, Artisan::call('reservas:revogar-nao-confirmadas'));

        $this->assertSame(StatusConstant::REVOGADO, $naoConfirmada->fresh()->status);
        $this->assertSame('Reserva não confirmada no prazo.', $naoConfirmada->fresh()->justificativa_revogacao);
        $this->assertNull($naoConfirmada->fresh()->revogada_por);
        $this->assertSame(StatusConstant::RESERVADO, $confirmada->fresh()->status);
        $this->assertSame(StatusConstant::RESERVADO, $semPedido->fresh()->status);
        $this->assertSame(StatusConstant::RESERVADO, $outroDia->fresh()->status);

        Mail::assertQueued(ReservaRevogadaMail::class, 1);
    }

    public function test_fluxo_completo_pelos_comandos(): void
    {
        $reserva = $this->reservar('2026-11-13');

        $this->travelTo(Carbon::parse('2026-11-11 08:00'));
        Artisan::call('reservas:pedir-confirmacao');
        $this->assertNotNull($reserva->fresh()->confirmacao_enviada_em);

        $this->travelTo(Carbon::parse('2026-11-13 00:05'));
        Artisan::call('reservas:revogar-nao-confirmadas');

        $this->assertSame(StatusConstant::REVOGADO, $reserva->fresh()->status);
    }

    public function test_filtro_de_confirmacao_na_listagem(): void
    {
        $this->comoAdmin();

        $aguardando = $this->reservaExistente('2026-11-10', ['confirmacao_enviada_em' => now()]);
        $confirmada = $this->reservaExistente('2026-11-10', ['confirmacao_enviada_em' => now(), 'confirmada_em' => now()]);
        $naoSolicitada = $this->reservaExistente('2026-11-12');
        $this->reservaExistente('2026-11-12', ['aluno_id' => null, 'tipo' => 'EVENTO', 'descricao' => 'Palestra']);

        $ids = fn ($situacao) => collect($this->getJson("/api/admin/reservas?confirmacao={$situacao}")->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$aguardando->id], $ids('AGUARDANDO'));
        $this->assertSame([$confirmada->id], $ids('CONFIRMADA'));
        $this->assertSame([$naoSolicitada->id], $ids('NAO_SOLICITADA'));

        $this->getJson('/api/admin/reservas?confirmacao=TALVEZ')->assertStatus(422)->assertJsonValidationErrors('confirmacao');
    }

    public function test_comandos_estao_agendados(): void
    {
        Artisan::call('schedule:list');
        $saida = Artisan::output();

        $this->assertStringContainsString('reservas:pedir-confirmacao', $saida);
        $this->assertStringContainsString('reservas:revogar-nao-confirmadas', $saida);
    }
}
