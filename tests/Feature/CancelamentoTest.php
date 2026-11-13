<?php

namespace Tests\Feature;

use App\Constants\StatusConstant;
use App\Mail\PedidoConfirmacaoMail;
use App\Mail\ReservaCanceladaMail;
use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\TokenReserva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CancelamentoTest extends TestCase
{
    use RefreshDatabase;

    private Aluno $aluno;

    private Espaco $quadra;

    private Equipamento $bola;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza', 'registro_academico' => '2024000001', 'email' => 'maria@email.com']);
        $this->quadra = Espaco::factory()->quadra(null)->create();
        $this->bola = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Bola', 'quantidade_total' => 2]);
    }

    private function reservar(string $data = '2026-11-12', string $horario = 'PRIMEIRO_HORARIO', ?Aluno $aluno = null)
    {
        return $this->postJson('/api/reservas', [
            'registro_academico' => ($aluno ?? $this->aluno)->registro_academico,
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => $data,
            'horario'            => $horario,
            'equipamentos'       => [['id' => $this->bola->id, 'quantidade' => 2]],
        ])->assertCreated();
    }

    private function tokenDoEmail(): string
    {
        $link = null;

        Mail::assertQueued(PedidoConfirmacaoMail::class, function (PedidoConfirmacaoMail $mail) use (&$link) {
            $link = $mail->linkCancelamento;

            return true;
        });

        return substr($link, strrpos($link, '/') + 1);
    }

    public function test_reserva_criada_envia_email_com_link_para_o_front(): void
    {
        $this->reservar();

        Mail::assertQueued(PedidoConfirmacaoMail::class, function (PedidoConfirmacaoMail $mail) {
            return $mail->hasTo('maria@email.com')
                && $mail->nome === 'Maria Souza'
                && str_starts_with($mail->linkCancelamento, 'http://localhost:4200/reservas/cancelar/')
                && strlen(substr($mail->linkCancelamento, strrpos($mail->linkCancelamento, '/') + 1)) === 64;
        });
    }

    public function test_banco_guarda_so_o_hash_do_token_valido_ate_o_inicio(): void
    {
        $id = $this->reservar()->json('data.id');
        $token = $this->tokenDoEmail();

        $registro = TokenReserva::where('reserva_id', $id)->where('finalidade', 'CANCELAR')->sole();
        $this->assertSame(hash('sha256', $token), $registro->token_hash);
        $this->assertNotSame($token, $registro->token_hash);
        $this->assertSame('2026-11-12 19:00:00', $registro->expira_em->format('Y-m-d H:i:s'));
        $this->assertSame('CANCELAR', $registro->finalidade->value);
    }

    public function test_resumo_pelo_get_sem_dados_pessoais(): void
    {
        $this->reservar();
        $token = $this->tokenDoEmail();

        $resposta = $this->getJson("/api/reservas/cancelamento/{$token}")->assertOk()
            ->assertJsonPath('data.status', 'RESERVADO')
            ->assertJsonPath('data.espaco.id', $this->quadra->id)
            ->assertJsonPath('data.equipamentos.0.nome', 'Bola')
            ->assertJsonPath('pode_cancelar', true)
            ->assertJsonMissingPath('data.aluno');

        foreach (['Maria Souza', '2024000001', 'maria@email.com'] as $dado) {
            $this->assertStringNotContainsString($dado, $resposta->getContent());
        }

        $this->assertSame(StatusConstant::RESERVADO, Reserva::sole()->status);
    }

    public function test_cancela_pelo_post_libera_horario_e_equipamentos_e_avisa(): void
    {
        $this->reservar();
        $token = $this->tokenDoEmail();

        $resposta = $this->postJson("/api/reservas/cancelamento/{$token}")->assertOk()
            ->assertJsonPath('data.status', 'CANCELADO')
            ->assertJsonMissingPath('data.aluno');
        $this->assertNotNull($resposta->json('data.cancelada_em'));
        $this->assertStringNotContainsString('maria@email.com', $resposta->getContent());

        $reserva = Reserva::sole();
        $this->assertSame(StatusConstant::CANCELADO, $reserva->status);
        $this->assertNull($reserva->atualizado_por);

        Mail::assertQueued(ReservaCanceladaMail::class, fn (ReservaCanceladaMail $mail) => $mail->hasTo('maria@email.com'));

        $this->reservar(aluno: Aluno::factory()->create());
    }

    public function test_link_so_pode_ser_usado_uma_vez(): void
    {
        $this->reservar();
        $token = $this->tokenDoEmail();

        $this->postJson("/api/reservas/cancelamento/{$token}")->assertOk();

        $this->postJson("/api/reservas/cancelamento/{$token}")->assertNotFound()
            ->assertExactJson(['message' => 'Link inválido ou expirado.']);
        $this->getJson("/api/reservas/cancelamento/{$token}")->assertNotFound();
    }

    public function test_token_invalido_ou_vencido(): void
    {
        $this->postJson('/api/reservas/cancelamento/'.str_repeat('a', 64))->assertNotFound()
            ->assertExactJson(['message' => 'Link inválido ou expirado.']);

        $this->reservar('2026-11-09');
        $token = $this->tokenDoEmail();

        $this->travelTo(Carbon::parse('2026-11-09 19:00'));

        $this->postJson("/api/reservas/cancelamento/{$token}")->assertNotFound();
        $this->assertSame(StatusConstant::RESERVADO, Reserva::sole()->status);
    }

    public function test_reserva_revogada_nao_pode_ser_cancelada(): void
    {
        $this->reservar();
        $token = $this->tokenDoEmail();
        Reserva::sole()->update(['status' => StatusConstant::REVOGADO]);

        $this->getJson("/api/reservas/cancelamento/{$token}")->assertOk()->assertJsonPath('pode_cancelar', false);
        $this->postJson("/api/reservas/cancelamento/{$token}")->assertStatus(409);
    }

    public function test_evento_nao_recebe_email_de_criacao(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/reservas/eventos', [
            'espaco_id'    => $this->quadra->id,
            'data_reserva' => '2026-11-12',
            'horario'      => 'PRIMEIRO_HORARIO',
            'descricao'    => 'Palestra',
        ])->assertCreated();

        Mail::assertNotQueued(PedidoConfirmacaoMail::class);
        $this->assertSame(0, TokenReserva::count());
    }

    public function test_resposta_da_criacao_nao_expoe_dados_do_aluno(): void
    {
        $resposta = $this->reservar()->assertJsonMissingPath('data.aluno');

        $this->assertStringNotContainsString('maria@email.com', $resposta->getContent());
    }
}
