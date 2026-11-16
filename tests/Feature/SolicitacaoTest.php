<?php

namespace Tests\Feature;

use App\Constants\HorarioReservaConstant;
use App\Mail\PedidoConfirmacaoMail;
use App\Mail\SolicitacaoRespondidaMail;
use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\ItemReserva;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SolicitacaoTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $sala10;

    private Espaco $sala11;

    private Espaco $lab;

    private Equipamento $projetor;

    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->sala10 = Espaco::factory()->quadra(null)->create(['nome' => 'Sala 10']);
        $this->sala11 = Espaco::factory()->quadra(null)->create(['nome' => 'Sala 11']);
        $this->lab = Espaco::factory()->laboratorio()->create(['nome' => 'Laboratório de Informática']);
        $this->projetor = Equipamento::factory()->create([
            'espaco_id'          => $this->lab->id,
            'nome'               => 'Projetor',
            'quantidade_total'   => 2,
            'aceita_solicitacao' => true,
        ]);
        $this->aluno = Aluno::factory()->create(['nome_completo' => 'Maria Souza']);
    }

    private function reservar(Espaco $espaco, array $extra = [], ?Aluno $aluno = null)
    {
        return $this->postJson('/api/reservas', [
            'registro_academico' => ($aluno ?? $this->aluno)->registro_academico,
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-11',
            'horario'            => HorarioReservaConstant::PRIMEIRO_HORARIO->value,
            ...$extra,
        ]);
    }

    private function pedir(int $quantidade = 1, ?Espaco $espaco = null, ?Aluno $aluno = null)
    {
        return $this->reservar($espaco ?? $this->sala10, ['solicitacoes' => [['id' => $this->projetor->id, 'quantidade' => $quantidade]]], $aluno);
    }

    private function solicitaveis(Espaco $espaco)
    {
        return $this->getJson("/api/espacos/{$espaco->id}/equipamentos/solicitaveis?data=2026-11-11&hora_inicio=19:00&hora_fim=20:30");
    }

    public function test_lista_so_itens_que_aceitam_pedido_de_outros_espacos_com_o_disponivel(): void
    {
        Equipamento::factory()->create(['espaco_id' => $this->lab->id, 'nome' => 'Microscópio']);
        Equipamento::factory()->create(['espaco_id' => $this->sala10->id, 'nome' => 'Bola', 'aceita_solicitacao' => true]);

        $this->solicitaveis($this->sala10)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'Projetor')
            ->assertJsonPath('data.0.espaco', 'Laboratório de Informática')
            ->assertJsonPath('data.0.disponivel', 2);

        $this->solicitaveis($this->lab)->assertOk()->assertJsonMissing(['nome' => 'Projetor']);
    }

    public function test_pedido_fica_aguardando_e_segura_a_quantidade_para_todos(): void
    {
        $this->pedir(2)->assertCreated()
            ->assertJsonPath('data.equipamentos.0.solicitacao.situacao', 'AGUARDANDO');

        $this->solicitaveis($this->sala11)->assertJsonPath('data.0.disponivel', 0);
        $this->getJson("/api/espacos/{$this->lab->id}/equipamentos/disponibilidade?data=2026-11-11&hora_inicio=19:00&hora_fim=20:30")
            ->assertJsonPath('data.0.disponivel', 0);

        $this->pedir(1, $this->sala11, Aluno::factory()->create())->assertStatus(409)
            ->assertJsonPath('dados.equipamentos_indisponiveis.0.disponivel', 0);

        Mail::assertQueued(PedidoConfirmacaoMail::class, fn ($mail) => str_contains($mail->render(), 'pedido aguardando aprovação'));
    }

    public function test_aprovado_nao_vira_compartilhamento(): void
    {
        $id = $this->pedir()->json('data.id');
        $this->comoAdmin();
        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/aprovar")->assertOk();

        $outro = Aluno::factory()->create();
        $this->reservar($this->sala10, [
            'data_reserva' => '2026-11-12',
            'equipamentos' => [['id' => $this->projetor->id, 'quantidade' => 1]],
        ], $outro)->assertUnprocessable()->assertJsonValidationErrors('equipamentos.0.id');

        $this->solicitaveis($this->sala10)->assertJsonPath('data.0.nome', 'Projetor');
    }

    public function test_nao_pede_item_que_nao_aceita_nem_do_proprio_espaco(): void
    {
        $this->projetor->update(['aceita_solicitacao' => false]);
        $this->pedir()->assertUnprocessable()->assertJsonValidationErrors('solicitacoes.0.id');

        $this->projetor->update(['aceita_solicitacao' => true]);
        $this->reservar($this->lab, ['solicitacoes' => [['id' => $this->projetor->id, 'quantidade' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('solicitacoes.0.id');
        $this->pedir(3)->assertUnprocessable()->assertJsonValidationErrors('solicitacoes.0.quantidade');
    }

    public function test_pedido_precisa_de_2_horas_de_antecedencia(): void
    {
        $this->travelTo(now()->setTime(17, 30));

        $this->reservar($this->sala10, [
            'data_reserva' => '2026-11-09',
            'solicitacoes' => [['id' => $this->projetor->id, 'quantidade' => 1]],
        ])->assertUnprocessable()
            ->assertJsonPath('errors.solicitacoes.0', 'Pedidos de equipamento de outro espaço precisam de pelo menos 2 horas de antecedência.');
    }

    public function test_admin_aprova_e_o_aluno_recebe_email_sem_link_de_cancelar(): void
    {
        $id = $this->pedir()->json('data.id');
        $admin = $this->comoAdmin();

        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/aprovar")->assertOk()
            ->assertJsonPath('data.equipamentos.0.solicitacao.situacao', 'APROVADA');

        $item = ItemReserva::sole();
        $this->assertSame($admin->id, $item->respondida_por);
        Mail::assertQueued(SolicitacaoRespondidaMail::class, fn ($mail) => $mail->hasTo($this->aluno->email)
            && $mail->linkCancelar === null
            && $mail->envelope()->subject === 'Seu pedido de equipamento foi aprovado');

        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/recusar", ['justificativa' => 'Projetor em manutenção.'])
            ->assertStatus(409)->assertJsonPath('message', 'Este pedido já foi respondido.');
    }

    public function test_recusa_libera_a_quantidade_mantem_a_reserva_e_manda_o_link_de_cancelar(): void
    {
        $id = $this->pedir(2)->json('data.id');
        $this->comoAdmin();

        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/recusar", ['justificativa' => 'curta'])
            ->assertUnprocessable()->assertJsonValidationErrors('justificativa');
        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/recusar", ['justificativa' => 'Projetor em manutenção.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'RESERVADO')
            ->assertJsonPath('data.equipamentos.0.solicitacao.situacao', 'RECUSADA')
            ->assertJsonPath('data.equipamentos.0.solicitacao.justificativa', 'Projetor em manutenção.');

        $this->solicitaveis($this->sala11)->assertJsonPath('data.0.disponivel', 2);

        Mail::assertQueued(SolicitacaoRespondidaMail::class, function (SolicitacaoRespondidaMail $mail) {
            $html = $mail->render();

            return str_contains((string) $mail->linkCancelar, '/reservas/cancelar/')
                && str_contains($html, 'Projetor em manutenção.')
                && str_contains($html, 'continua valendo')
                && str_contains($html, 'Cancelar reserva');
        });
    }

    public function test_pedido_sem_resposta_vence_2_horas_antes_e_avisa(): void
    {
        $id = $this->pedir()->json('data.id');

        $this->travelTo('2026-11-11 16:59');
        Artisan::call('reservas:vencer-solicitacoes');
        $this->assertSame('AGUARDANDO', ItemReserva::sole()->situacao_solicitacao->value);

        $this->travelTo('2026-11-11 17:00');
        Artisan::call('reservas:vencer-solicitacoes');
        $this->assertSame('VENCIDA', ItemReserva::sole()->situacao_solicitacao->value);
        $this->assertSame('RESERVADO', Reserva::find($id)->status->value);

        Mail::assertQueued(SolicitacaoRespondidaMail::class, fn ($mail) => $mail->envelope()->subject === 'Seu pedido de equipamento ficou sem resposta'
            && $mail->linkCancelar !== null);

        $this->comoAdmin();
        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/aprovar")->assertStatus(409);
    }

    public function test_filtro_de_pedidos_aguardando_e_devolucao_so_dos_aprovados(): void
    {
        $id = $this->pedir()->json('data.id');
        Reserva::factory()->create(['data_reserva' => '2026-11-11']);
        $this->comoAdmin();

        $ids = collect($this->getJson('/api/admin/reservas?solicitacao=AGUARDANDO')->json('data'))->pluck('id')->all();
        $this->assertSame([$id], $ids);

        $this->postJson("/api/admin/reservas/{$id}/solicitacoes/{$this->projetor->id}/recusar", ['justificativa' => 'Projetor em manutenção.']);
        $this->travelTo('2026-11-11 21:00');

        $this->postJson("/api/admin/reservas/{$id}/devolucao", ['itens' => [[
            'equipamento_id' => $this->projetor->id, 'quantidade_devolvida' => 1, 'situacao' => 'DEVOLVIDO',
        ]]])->assertUnprocessable();
        $this->assertSame([], collect($this->getJson('/api/admin/reservas?devolucao=PENDENTE')->json('data'))->pluck('id')->all());
    }

    public function test_exportacao_mostra_a_situacao_do_pedido(): void
    {
        $this->pedir();
        $this->comoAdmin();

        $csv = $this->get('/api/admin/reservas/exportar?colunas[]=equipamentos')->streamedContent();

        $this->assertStringContainsString('Projetor x1 (pedido aguardando aprovação)', $csv);
    }

    public function test_admin_marca_o_equipamento_como_aceita_solicitacao(): void
    {
        $this->comoAdmin();
        $microscopio = Equipamento::factory()->create(['espaco_id' => $this->lab->id]);

        $this->patchJson("/api/admin/equipamentos/{$microscopio->id}", ['aceita_solicitacao' => true])->assertOk()
            ->assertJsonPath('data.aceita_solicitacao', true);
    }
}
