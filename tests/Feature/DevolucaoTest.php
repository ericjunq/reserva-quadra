<?php

namespace Tests\Feature;

use App\Constants\AcaoAuditoriaConstant;
use App\Mail\DevolucaoComPendenciaMail;
use App\Models\Auditoria;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\ItemReserva;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DevolucaoTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    private Equipamento $bola;

    private Equipamento $rede;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->quadra = Espaco::factory()->quadra(null)->create();
        $this->bola = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Bola', 'quantidade_total' => 10]);
        $this->rede = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Rede', 'quantidade_total' => 2]);
    }

    private function reservaComItens(string $data = '2026-11-06', array $estado = []): Reserva
    {
        $reserva = Reserva::factory()->create(['data_reserva' => $data, 'espaco_id' => $this->quadra->id, ...$estado]);
        $reserva->equipamentos()->attach([
            $this->bola->id => ['quantidade' => 2],
            $this->rede->id => ['quantidade' => 1],
        ]);

        return $reserva;
    }

    private function devolver(Reserva $reserva, array $itens)
    {
        return $this->postJson("/api/admin/reservas/{$reserva->id}/devolucao", ['itens' => $itens]);
    }

    private function item(Reserva $reserva, Equipamento $equipamento): ItemReserva
    {
        return ItemReserva::where('reserva_id', $reserva->id)->where('equipamento_id', $equipamento->id)->firstOrFail();
    }

    public function test_exige_login(): void
    {
        $this->devolver($this->reservaComItens(), [])->assertUnauthorized();
    }

    public function test_itens_nascem_pendentes(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $this->getJson("/api/admin/reservas/{$reserva->id}")->assertOk()
            ->assertJsonPath('data.equipamentos.0.devolucao.situacao', 'PENDENTE')
            ->assertJsonPath('data.equipamentos.0.devolucao.quantidade_devolvida', null);
    }

    public function test_devolucao_completa_sem_aviso_ao_aluno(): void
    {
        $reserva = $this->reservaComItens();
        $admin = $this->comoAdmin();

        $resposta = $this->devolver($reserva, [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO'],
        ])->assertOk();

        $bola = collect($resposta->json('data.equipamentos'))->firstWhere('nome', 'Bola');
        $this->assertSame('DEVOLVIDO', $bola['devolucao']['situacao']);
        $this->assertSame(2, $bola['devolucao']['quantidade_devolvida']);
        $this->assertSame($admin->id, $bola['devolucao']['devolvido_por']);
        $this->assertNotNull($bola['devolucao']['devolvido_em']);

        $this->assertSame('PENDENTE', $this->item($reserva, $this->rede)->situacao_devolucao->value);
        Mail::assertNothingQueued();
    }

    public function test_avaria_e_falta_avisam_o_aluno(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $this->devolver($reserva, [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO_COM_AVARIA', 'observacao' => 'Uma bola furada'],
            ['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 0, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'Rede não foi entregue'],
        ])->assertOk();

        Mail::assertQueued(DevolucaoComPendenciaMail::class, function (DevolucaoComPendenciaMail $mail) use ($reserva) {
            return $mail->hasTo($reserva->aluno->email)
                && count($mail->itens) === 2
                && $mail->itens[1]['situacao'] === 'Não devolvido';
        });
    }

    public function test_avaria_ou_falta_nao_mexe_no_estoque_nem_bloqueia_o_aluno(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $this->devolver($reserva, [
            ['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 0, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'Perdida'],
        ])->assertOk();

        $this->assertSame(2, $this->rede->fresh()->quantidade_total);

        $this->postJson('/api/reservas', [
            'registro_academico' => $reserva->aluno->registro_academico,
            'espaco_id'          => $this->quadra->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
        ])->assertCreated();
    }

    public function test_correcao_atualiza_o_registro_e_fica_no_historico(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $falta = ['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 0, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'Não entregue'];
        $this->devolver($reserva, [$falta])->assertOk();
        $this->devolver($reserva, [$falta])->assertOk();
        Mail::assertQueued(DevolucaoComPendenciaMail::class, 1);

        $this->devolver($reserva, [['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 1, 'situacao' => 'DEVOLVIDO']])->assertOk();

        $item = $this->item($reserva, $this->rede);
        $this->assertSame('DEVOLVIDO', $item->situacao_devolucao->value);
        $this->assertNull($item->observacao_devolucao);

        $correcao = Auditoria::where('auditavel_type', ItemReserva::class)->where('auditavel_id', $item->id)->get()->last();
        $this->assertSame(AcaoAuditoriaConstant::ATUALIZACAO, $correcao->acao);
        $this->assertSame('NAO_DEVOLVIDO', $correcao->antes['situacao_devolucao']);
        $this->assertSame('DEVOLVIDO', $correcao->depois['situacao_devolucao']);
    }

    public function test_quantidade_precisa_combinar_com_a_situacao(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $casos = [
            ['quantidade_devolvida' => 1, 'situacao' => 'DEVOLVIDO'],
            ['quantidade_devolvida' => 3, 'situacao' => 'DEVOLVIDO_COM_AVARIA', 'observacao' => 'x'],
            ['quantidade_devolvida' => 0, 'situacao' => 'DEVOLVIDO_COM_AVARIA', 'observacao' => 'x'],
            ['quantidade_devolvida' => 2, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'x'],
        ];

        foreach ($casos as $caso) {
            $this->devolver($reserva, [['equipamento_id' => $this->bola->id, ...$caso]])
                ->assertStatus(422)
                ->assertJsonPath('dados.itens_invalidos.0.equipamento_id', $this->bola->id);
        }

        $this->assertSame('PENDENTE', $this->item($reserva, $this->bola)->situacao_devolucao->value);
    }

    public function test_equipamento_fora_da_reserva(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();
        $outro = Equipamento::factory()->for($this->quadra)->create();

        $this->devolver($reserva, [['equipamento_id' => $outro->id, 'quantidade_devolvida' => 1, 'situacao' => 'DEVOLVIDO']])
            ->assertStatus(422)
            ->assertJsonPath('dados.itens_invalidos.0.motivo', 'O equipamento não faz parte desta reserva.');
    }

    public function test_validacoes_do_pedido(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $this->devolver($reserva, [])->assertStatus(422)->assertJsonValidationErrors('itens');
        $this->devolver($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'PENDENTE']])
            ->assertStatus(422)->assertJsonValidationErrors('itens.0.situacao');
        $this->devolver($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 1, 'situacao' => 'DEVOLVIDO_COM_AVARIA']])
            ->assertStatus(422)->assertJsonValidationErrors('itens.0.observacao');
        $this->devolver($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => -1, 'situacao' => 'DEVOLVIDO']])
            ->assertStatus(422)->assertJsonValidationErrors('itens.0.quantidade_devolvida');
    }

    public function test_so_reservas_ativas_que_ja_comecaram(): void
    {
        $this->comoAdmin();
        $item = [['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO']];

        $this->devolver($this->reservaComItens('2026-11-09'), $item)->assertStatus(422);
        $this->devolver($this->reservaComItens('2026-11-06', ['status' => 'REVOGADO']), $item)->assertStatus(409);

        $this->travelTo(now()->setTime(19, 0));
        $this->devolver($this->reservaComItens('2026-11-09'), $item)->assertOk();
    }

    public function test_evento_sem_aluno_registra_sem_email(): void
    {
        $evento = $this->reservaComItens('2026-11-06', ['aluno_id' => null, 'tipo' => 'EVENTO', 'descricao' => 'Palestra']);
        $this->comoAdmin();

        $this->devolver($evento, [['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 0, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'Sumiu']])->assertOk();

        Mail::assertNothingQueued();
    }

    public function test_filtro_de_pendencias_na_listagem(): void
    {
        $this->comoAdmin();

        $pendente = $this->reservaComItens('2026-11-06');
        $resolvida = $this->reservaComItens('2026-11-05');
        $futura = $this->reservaComItens('2026-11-10');
        $this->reservaComItens('2026-11-04', ['status' => 'REVOGADO']);
        Reserva::factory()->create(['data_reserva' => '2026-11-03']);

        $this->devolver($resolvida, [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO'],
            ['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 0, 'situacao' => 'NAO_DEVOLVIDO', 'observacao' => 'Perdida'],
        ])->assertOk();

        $ids = fn ($params) => collect($this->getJson('/api/admin/reservas?'.http_build_query($params))->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$pendente->id], $ids(['devolucao' => 'PENDENTE']));
        $this->assertSame([$resolvida->id], $ids(['devolucao' => 'NAO_DEVOLVIDO']));
        $this->assertNotContains($futura->id, $ids(['devolucao' => 'PENDENTE']));

        $this->getJson('/api/admin/reservas?devolucao=SUMIU')->assertStatus(422)->assertJsonValidationErrors('devolucao');
    }

    public function test_exportacao_com_coluna_de_devolucao(): void
    {
        $reserva = $this->reservaComItens();
        $this->comoAdmin();

        $this->devolver($reserva, [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO_COM_AVARIA', 'observacao' => 'Furada'],
        ])->assertOk();

        $csv = $this->get('/api/admin/reservas/exportar?de=2026-11-01&colunas[]=devolucao')->assertOk()->streamedContent();

        $this->assertStringContainsString('Bola: Devolvido com avaria 2/2 (Furada); Rede: Pendente 0/1', $csv);
    }
}
