<?php

namespace Tests\Feature;

use App\Constants\EtapaConfirmacaoConstant;
use App\Constants\StatusConstant;
use App\Mail\PedidoConfirmacaoMail;
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

    private function reservar(string $data, string $horario = 'PRIMEIRO_HORARIO'): Reserva
    {
        $id = $this->postJson('/api/reservas', [
            'registro_academico' => $this->aluno->registro_academico,
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => $data,
            'horario'            => $horario,
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

    private function confirmar(Reserva $reserva, bool $aceite = true)
    {
        $token = $this->tokenDeConfirmacao($reserva);

        return $this->postJson("/api/reservas/confirmacao/{$token}", ['aceite_termos' => $aceite]);
    }

    private function tokenDeConfirmacao(Reserva $reserva, EtapaConfirmacaoConstant $etapa = EtapaConfirmacaoConstant::CONFIRMACAO): string
    {
        $link = null;

        Mail::assertQueued(PedidoConfirmacaoMail::class, function (PedidoConfirmacaoMail $mail) use ($reserva, $etapa, &$link) {
            if ($mail->reserva->id === $reserva->id && $mail->etapa === $etapa) {
                $link = $mail->linkConfirmacao;
            }

            return true;
        });

        return $this->token($link);
    }

    public function test_toda_reserva_nasce_aguardando_confirmacao_com_prazo_de_24_horas(): void
    {
        $reserva = $this->reservar('2026-11-12');

        $this->assertSame('2026-11-10 10:00:00', $reserva->confirmar_ate->format('Y-m-d H:i:s'));
        $this->assertNull($reserva->confirmada_em);
        $this->assertSame(StatusConstant::RESERVADO, $reserva->status);

        $pedido = $this->pedido();
        $this->assertTrue($pedido->hasTo('maria@email.com'));
        $this->assertSame(EtapaConfirmacaoConstant::CONFIRMACAO, $pedido->etapa);
        $this->assertSame('Confirme sua reserva', $pedido->envelope()->subject);
        $this->assertSame('2026-11-10 10:00:00', $pedido->prazo->format('Y-m-d H:i:s'));
        $this->assertFalse($pedido->avisarReconfirmacao);
        $this->assertStringStartsWith('http://localhost:4200/reservas/confirmar/', $pedido->linkConfirmacao);
        $this->assertStringStartsWith('http://localhost:4200/reservas/cancelar/', $pedido->linkCancelamento);
        $this->assertStringContainsString('termos de uso', $pedido->render());

        $confirmar = TokenReserva::where('reserva_id', $reserva->id)->where('finalidade', 'CONFIRMAR')->sole();
        $this->assertSame('2026-11-10 10:00:00', $confirmar->expira_em->format('Y-m-d H:i:s'));
    }

    public function test_reserva_distante_avisa_que_havera_uma_nova_confirmacao(): void
    {
        $this->reservar('2026-11-20');

        $this->assertTrue($this->pedido()->avisarReconfirmacao);
    }

    public function test_reserva_para_hoje_tem_uma_hora_e_nunca_passa_do_inicio(): void
    {
        $this->assertSame('2026-11-09 11:00:00', $this->reservar('2026-11-09', 'SEGUNDO_HORARIO')->confirmar_ate->format('Y-m-d H:i:s'));

        $this->travelTo(Carbon::parse('2026-11-09 18:30'));
        $this->assertSame('2026-11-09 19:00:00', $this->reservar('2026-11-09')->confirmar_ate->format('Y-m-d H:i:s'));
    }

    public function test_reserva_que_ja_comecou_tem_prazo_ate_o_fim(): void
    {
        $this->travelTo(Carbon::parse('2026-11-09 19:50'));

        $this->assertSame('2026-11-09 20:30:00', $this->reservar('2026-11-09')->confirmar_ate->format('Y-m-d H:i:s'));
    }

    public function test_confirmacao_exige_o_aceite_dos_termos(): void
    {
        $reserva = $this->reservar('2026-11-12');
        $token = $this->tokenDeConfirmacao($reserva);

        $this->getJson("/api/reservas/confirmacao/{$token}")->assertOk()
            ->assertJsonPath('etapa', 'CONFIRMACAO')
            ->assertJsonPath('pode_confirmar', true)
            ->assertJsonPath('exige_aceite_termos', true)
            ->assertJsonPath('termos_versao', '2026-11')
            ->assertJsonPath('data.espaco.id', $this->quadra->id)
            ->assertJsonMissingPath('data.aluno');

        $this->postJson("/api/reservas/confirmacao/{$token}")->assertStatus(422)
            ->assertJsonPath('errors.aceite_termos.0', 'Para confirmar, leia e aceite os termos de uso e o aviso de privacidade.');
        $this->postJson("/api/reservas/confirmacao/{$token}", ['aceite_termos' => false])->assertStatus(422);
        $this->assertNull($reserva->fresh()->confirmada_em);

        $this->postJson("/api/reservas/confirmacao/{$token}", ['aceite_termos' => true])->assertOk()
            ->assertJsonPath('data.status', 'RESERVADO')
            ->assertJsonPath('data.termos_versao', '2026-11');

        $reserva->refresh();
        $this->assertNotNull($reserva->confirmada_em);
        $this->assertNotNull($reserva->termos_aceitos_em);
        $this->assertSame('2026-11', $reserva->termos_versao);
        $this->assertNull($reserva->atualizado_por);

        $this->postJson("/api/reservas/confirmacao/{$token}", ['aceite_termos' => true])->assertNotFound()
            ->assertExactJson(['message' => 'Link inválido ou expirado.']);
    }

    public function test_link_de_confirmacao_vence_no_prazo(): void
    {
        $reserva = $this->reservar('2026-11-12');
        $token = $this->tokenDeConfirmacao($reserva);

        $this->travelTo(Carbon::parse('2026-11-10 09:59'));
        $this->getJson("/api/reservas/confirmacao/{$token}")->assertOk();

        $this->travelTo(Carbon::parse('2026-11-10 10:00'));
        $this->postJson("/api/reservas/confirmacao/{$token}", ['aceite_termos' => true])->assertNotFound();
    }

    public function test_cancelar_pelo_email_de_confirmacao(): void
    {
        $reserva = $this->reservar('2026-11-11');
        $token = $this->token($this->pedido()->linkCancelamento);

        $this->postJson("/api/reservas/cancelamento/{$token}")->assertOk();

        $this->assertSame(StatusConstant::CANCELADO, $reserva->fresh()->status);
    }

    public function test_revoga_quem_nao_confirmou_no_prazo(): void
    {
        $naoConfirmada = $this->reservar('2026-11-12');
        $confirmada = $this->reservar('2026-11-13');
        $this->confirmar($confirmada)->assertOk();
        $antiga = $this->reservaExistente('2026-11-12', ['horario' => 'SEGUNDO_HORARIO', 'hora_inicio' => '20:30', 'hora_fim' => '22:00']);

        $this->travelTo(Carbon::parse('2026-11-10 09:59'));
        Artisan::call('reservas:revogar-nao-confirmadas');
        $this->assertSame(StatusConstant::RESERVADO, $naoConfirmada->fresh()->status);

        $this->travelTo(Carbon::parse('2026-11-10 10:00'));
        Artisan::call('reservas:revogar-nao-confirmadas');

        $this->assertSame(StatusConstant::REVOGADO, $naoConfirmada->fresh()->status);
        $this->assertSame('Reserva não confirmada no prazo.', $naoConfirmada->fresh()->justificativa_revogacao);
        $this->assertNull($naoConfirmada->fresh()->revogada_por);
        $this->assertSame(StatusConstant::RESERVADO, $confirmada->fresh()->status);
        $this->assertSame(StatusConstant::RESERVADO, $antiga->fresh()->status);
        Mail::assertQueued(ReservaRevogadaMail::class, 1);
    }

    public function test_reconfirmacao_tres_dias_uteis_antes(): void
    {
        $reserva = $this->reservaExistente('2026-11-16', ['confirmada_em' => '2026-11-02 10:00:00']);

        $this->travelTo(Carbon::parse('2026-11-10 08:00'));
        Artisan::call('reservas:pedir-reconfirmacao');
        $this->assertNull($reserva->fresh()->reconfirmacao_pedida_em);

        $this->travelTo(Carbon::parse('2026-11-11 08:00'));
        Artisan::call('reservas:pedir-reconfirmacao');
        $reserva->refresh();
        $this->assertNotNull($reserva->reconfirmacao_pedida_em);
        $this->assertSame('2026-11-15 00:00:00', $reserva->reconfirmar_ate->format('Y-m-d H:i:s'));

        $pedido = $this->pedido();
        $this->assertSame(EtapaConfirmacaoConstant::RECONFIRMACAO, $pedido->etapa);
        $this->assertSame('Sua reserva está chegando: confirme de novo', $pedido->envelope()->subject);
        $this->assertSame('2026-11-14 23:59', $pedido->prazo->format('Y-m-d H:i'));

        Artisan::call('reservas:pedir-reconfirmacao');
        Mail::assertQueued(PedidoConfirmacaoMail::class, 1);
    }

    public function test_quem_confirmou_ha_pouco_nao_recebe_a_reconfirmacao(): void
    {
        $reserva = $this->reservaExistente('2026-11-16', ['confirmada_em' => '2026-11-09 10:00:00']);

        foreach (['2026-11-11', '2026-11-12', '2026-11-13'] as $dia) {
            $this->travelTo(Carbon::parse("{$dia} 08:00"));
            Artisan::call('reservas:pedir-reconfirmacao');
        }

        $this->assertNull($reserva->fresh()->reconfirmacao_pedida_em);
        Mail::assertNotQueued(PedidoConfirmacaoMail::class);
    }

    public function test_reconfirmacao_ignora_eventos_revogadas_nao_confirmadas_e_proximas(): void
    {
        $antes = ['confirmada_em' => '2026-10-20 10:00:00'];
        $this->reservaExistente('2026-11-11', ['aluno_id' => null, 'tipo' => 'EVENTO', 'descricao' => 'Palestra', ...$antes]);
        $this->reservaExistente('2026-11-11', ['status' => 'REVOGADO', ...$antes]);
        $this->reservaExistente('2026-11-11');
        $this->reservaExistente('2026-11-10', $antes);
        $this->reservaExistente('2026-11-12', ['reconfirmacao_pedida_em' => now(), ...$antes]);

        $this->assertSame(0, Artisan::call('reservas:pedir-reconfirmacao'));
        Mail::assertNotQueued(PedidoConfirmacaoMail::class);
    }

    public function test_reconfirmacao_pelo_link_sem_novo_aceite(): void
    {
        $reserva = $this->reservaExistente('2026-11-16', ['confirmada_em' => '2026-11-02 10:00:00']);
        $this->travelTo(Carbon::parse('2026-11-11 08:00'));
        Artisan::call('reservas:pedir-reconfirmacao');
        $token = $this->tokenDeConfirmacao($reserva, EtapaConfirmacaoConstant::RECONFIRMACAO);

        $this->getJson("/api/reservas/confirmacao/{$token}")->assertOk()
            ->assertJsonPath('etapa', 'RECONFIRMACAO')
            ->assertJsonPath('exige_aceite_termos', false)
            ->assertJsonPath('pode_confirmar', true);

        $this->postJson("/api/reservas/confirmacao/{$token}")->assertOk();
        $this->assertNotNull($reserva->fresh()->reconfirmada_em);
    }

    public function test_revoga_quem_nao_reconfirmou_no_comeco_da_vespera(): void
    {
        $semResposta = $this->reservaExistente('2026-11-16', ['confirmada_em' => '2026-11-02 10:00:00']);
        $reconfirmada = $this->reservaExistente('2026-11-16', ['confirmada_em' => '2026-11-02 10:00:00', 'horario' => 'SEGUNDO_HORARIO', 'hora_inicio' => '20:30', 'hora_fim' => '22:00']);
        $this->travelTo(Carbon::parse('2026-11-11 08:00'));
        Artisan::call('reservas:pedir-reconfirmacao');
        $this->postJson('/api/reservas/confirmacao/'.$this->tokenDeConfirmacao($reconfirmada, EtapaConfirmacaoConstant::RECONFIRMACAO))->assertOk();

        $this->travelTo(Carbon::parse('2026-11-14 23:59'));
        Artisan::call('reservas:revogar-nao-confirmadas');
        $this->assertSame(StatusConstant::RESERVADO, $semResposta->fresh()->status);

        $this->travelTo(Carbon::parse('2026-11-15 00:00'));
        Artisan::call('reservas:revogar-nao-confirmadas');
        $this->assertSame(StatusConstant::REVOGADO, $semResposta->fresh()->status);
        $this->assertSame(StatusConstant::RESERVADO, $reconfirmada->fresh()->status);
    }

    public function test_fluxo_completo_pelos_comandos(): void
    {
        $reserva = $this->reservar('2026-11-20');
        $this->confirmar($reserva)->assertOk();

        $this->travelTo(Carbon::parse('2026-11-17 08:00'));
        Artisan::call('reservas:pedir-reconfirmacao');
        $this->assertNotNull($reserva->fresh()->reconfirmacao_pedida_em);

        $this->travelTo(Carbon::parse('2026-11-19 00:00'));
        Artisan::call('reservas:revogar-nao-confirmadas');

        $this->assertSame(StatusConstant::REVOGADO, $reserva->fresh()->status);
    }

    public function test_filtro_de_confirmacao_na_listagem(): void
    {
        $this->comoAdmin();

        $aguardando = $this->reservaExistente('2026-11-10');
        $confirmada = $this->reservaExistente('2026-11-10', ['confirmada_em' => now()]);
        $aguardandoReconfirmacao = $this->reservaExistente('2026-11-12', ['confirmada_em' => now(), 'reconfirmacao_pedida_em' => now()]);
        $reconfirmada = $this->reservaExistente('2026-11-12', ['confirmada_em' => now(), 'reconfirmacao_pedida_em' => now(), 'reconfirmada_em' => now()]);
        $this->reservaExistente('2026-11-12', ['aluno_id' => null, 'tipo' => 'EVENTO', 'descricao' => 'Palestra']);

        $ids = fn ($situacao) => collect($this->getJson("/api/admin/reservas?confirmacao={$situacao}")->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$aguardando->id], $ids('AGUARDANDO_CONFIRMACAO'));
        $this->assertSame([$confirmada->id], $ids('CONFIRMADA'));
        $this->assertSame([$aguardandoReconfirmacao->id], $ids('AGUARDANDO_RECONFIRMACAO'));
        $this->assertSame([$reconfirmada->id], $ids('RECONFIRMADA'));

        $this->getJson('/api/admin/reservas?confirmacao=TALVEZ')->assertStatus(422)->assertJsonValidationErrors('confirmacao');
    }

    public function test_comandos_estao_agendados(): void
    {
        Artisan::call('schedule:list');
        $saida = Artisan::output();

        $this->assertStringContainsString('reservas:pedir-reconfirmacao', $saida);
        $this->assertStringContainsString('reservas:revogar-nao-confirmadas', $saida);
    }

    public function test_termos_publicos_com_versao_e_cache(): void
    {
        $resposta = $this->getJson('/api/termos')->assertOk()
            ->assertJsonPath('data.versao', '2026-11')
            ->assertJsonPath('data.rascunho', true)
            ->assertJsonPath('data.termos_de_uso.0.titulo', 'Para que serve o sistema')
            ->assertJsonPath('data.privacidade.0.titulo', 'Quem trata os seus dados');

        $this->assertStringContainsString('public', $resposta->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=3600', $resposta->headers->get('Cache-Control'));
        $texto = json_encode($resposta->json(), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('10 dias úteis', $texto);
        $this->assertStringContainsString('[a confirmar com a instituição]', $texto);
    }
}
