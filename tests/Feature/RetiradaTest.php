<?php

namespace Tests\Feature;

use App\Constants\SituacaoSolicitacaoConstant;
use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\ItemReserva;
use App\Models\Reserva;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RetiradaTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;

    private Equipamento $bola;

    private Equipamento $rede;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->quadra = Espaco::factory()->quadra(null)->create(['nome' => 'Quadra']);
        $this->bola = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Bola', 'quantidade_total' => 10]);
        $this->rede = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Rede', 'quantidade_total' => 2]);
    }

    private function reservaDeHoje(array $estado = []): Reserva
    {
        $reserva = Reserva::factory()->create(['data_reserva' => '2026-11-09', 'espaco_id' => $this->quadra->id, ...$estado]);
        $reserva->equipamentos()->attach([
            $this->bola->id => ['quantidade' => 2],
            $this->rede->id => ['quantidade' => 1],
        ]);

        return $reserva;
    }

    private function retirar(Reserva $reserva, array $itens)
    {
        return $this->postJson("/api/admin/reservas/{$reserva->id}/retirada", ['itens' => $itens]);
    }

    private function item(Reserva $reserva, Equipamento $equipamento): ItemReserva
    {
        return ItemReserva::where('reserva_id', $reserva->id)->where('equipamento_id', $equipamento->id)->firstOrFail();
    }

    private function as(string $hora): void
    {
        $this->travelTo(Carbon::parse("2026-11-09 {$hora}"));
    }

    public function test_exige_login(): void
    {
        $this->retirar($this->reservaDeHoje(), [])->assertUnauthorized();
        $this->getJson('/api/admin/equipamentos/fora-agora')->assertUnauthorized();
    }

    public function test_registra_a_quantidade_retirada_e_quem_registrou(): void
    {
        $reserva = $this->reservaDeHoje();
        $admin = $this->comoAdmin();
        $this->as('18:40');

        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 1]])->assertOk()
            ->assertJsonPath('data.equipamentos.0.retirada.quantidade', 1)
            ->assertJsonPath('data.equipamentos.1.retirada', null);

        $item = $this->item($reserva, $this->bola);
        $this->assertSame(1, $item->quantidade_retirada);
        $this->assertSame($admin->id, $item->retirado_por);
        $this->assertSame('2026-11-09 18:40:00', $item->retirado_em->toDateTimeString());
        Mail::assertNothingQueued();
    }

    public function test_so_a_partir_de_trinta_minutos_antes_e_ate_o_fim(): void
    {
        $reserva = $this->reservaDeHoje();
        $this->comoAdmin();
        $itens = [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 2]];

        $this->as('18:29');
        $this->retirar($reserva, $itens)->assertUnprocessable()
            ->assertJsonPath('message', 'A retirada só pode ser registrada a partir de 09/11 18:30.');

        $this->as('18:30');
        $this->retirar($reserva, $itens)->assertOk();

        $this->as('20:31');
        $this->retirar($reserva, $itens)->assertUnprocessable()
            ->assertJsonPath('message', 'A reserva já terminou. Registre a devolução.');
    }

    public function test_so_reservas_ativas(): void
    {
        $reserva = $this->reservaDeHoje(['status' => 'CANCELADO']);
        $this->comoAdmin();
        $this->as('19:00');

        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 1]])->assertConflict();
    }

    public function test_itens_invalidos_voltam_com_o_motivo(): void
    {
        $reserva = $this->reservaDeHoje();
        $outro = Equipamento::factory()->create();
        $pedido = Equipamento::factory()->create();
        $reserva->equipamentos()->attach($pedido->id, ['quantidade' => 1, 'situacao_solicitacao' => SituacaoSolicitacaoConstant::RECUSADA]);
        $this->comoAdmin();
        $this->as('19:00');

        $resposta = $this->retirar($reserva, [
            ['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 3],
            ['equipamento_id' => $outro->id, 'quantidade_retirada' => 1],
            ['equipamento_id' => $pedido->id, 'quantidade_retirada' => 1],
        ])->assertUnprocessable();

        $this->assertSame([
            'A quantidade retirada não pode passar da reservada (2).',
            'O equipamento não faz parte desta reserva.',
            'O equipamento não faz parte desta reserva.',
        ], array_column($resposta->json('dados.itens_invalidos'), 'motivo'));
        $this->assertNull($this->item($reserva, $this->bola)->retirado_em);

        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => -1]])
            ->assertUnprocessable()->assertJsonValidationErrors('itens.0.quantidade_retirada');
    }

    public function test_devolucao_confere_com_a_quantidade_retirada(): void
    {
        $reserva = $this->reservaDeHoje();
        $this->comoAdmin();
        $this->as('18:50');
        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 1]])->assertOk();

        $this->as('20:40');
        $devolver = fn (int $quantidade) => $this->postJson("/api/admin/reservas/{$reserva->id}/devolucao", ['itens' => [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => $quantidade, 'situacao' => 'DEVOLVIDO'],
        ]]);

        $devolver(2)->assertUnprocessable()
            ->assertJsonPath('dados.itens_invalidos.0.motivo', 'A quantidade devolvida não pode passar da retirada (1).');
        $devolver(1)->assertOk();

        $this->postJson("/api/admin/reservas/{$reserva->id}/devolucao", ['itens' => [
            ['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 1, 'situacao' => 'DEVOLVIDO'],
        ]])->assertOk();
    }

    public function test_correcao_fica_no_historico_e_nao_pode_ficar_abaixo_do_devolvido(): void
    {
        $reserva = $this->reservaDeHoje();
        $this->comoAdmin();
        $this->as('18:45');
        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 1]])->assertOk();

        $this->as('19:10');
        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 1]])->assertOk();
        $this->assertSame('2026-11-09 18:45:00', $this->item($reserva, $this->bola)->retirado_em->toDateTimeString());

        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 2]])->assertOk();
        $item = $this->item($reserva, $this->bola);
        $this->assertSame('2026-11-09 19:10:00', $item->retirado_em->toDateTimeString());
        $this->assertTrue($item->auditorias()->get()->contains(
            fn ($auditoria) => ($auditoria->antes['quantidade_retirada'] ?? null) === 1 && $auditoria->depois['quantidade_retirada'] === 2,
        ));

        $this->postJson("/api/admin/reservas/{$reserva->id}/devolucao", ['itens' => [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO'],
        ]])->assertOk();
        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 1]])->assertUnprocessable()
            ->assertJsonPath('dados.itens_invalidos.0.motivo', 'Já foram registradas 2 unidade(s) devolvidas.');
    }

    public function test_fora_agora_lista_o_que_saiu_e_ainda_nao_voltou(): void
    {
        $aluno = Aluno::factory()->create(['nome_completo' => 'Carla Mendes']);
        $reserva = $this->reservaDeHoje(['aluno_id' => $aluno->id]);
        $evento = $this->reservaDeHoje(['aluno_id' => null, 'tipo' => 'EVENTO', 'descricao' => 'Torneio', 'responsavel_nome' => 'Prof. Lima', 'horario' => 'SEGUNDO_HORARIO', 'hora_inicio' => '20:40', 'hora_fim' => '22:10']);
        $this->comoAdmin();

        $this->as('18:40');
        $this->retirar($reserva, [
            ['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 2],
            ['equipamento_id' => $this->rede->id, 'quantidade_retirada' => 0],
        ])->assertOk();
        $this->as('20:35');
        $this->retirar($evento, [['equipamento_id' => $this->rede->id, 'quantidade_retirada' => 1]])->assertOk();
        $this->postJson("/api/admin/reservas/{$reserva->id}/devolucao", ['itens' => [
            ['equipamento_id' => $this->rede->id, 'quantidade_devolvida' => 0, 'situacao' => 'DEVOLVIDO'],
        ]])->assertOk();

        $resposta = $this->getJson('/api/admin/equipamentos/fora-agora')->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.equipamento.nome', 'Bola')
            ->assertJsonPath('data.0.quantidade', 2)
            ->assertJsonPath('data.0.reserva.com_quem', 'Carla Mendes')
            ->assertJsonPath('data.0.reserva.espaco', 'Quadra')
            ->assertJsonPath('data.0.reserva.terminou', true)
            ->assertJsonPath('data.1.reserva.com_quem', 'Prof. Lima')
            ->assertJsonPath('data.1.reserva.terminou', false);
        $this->assertStringNotContainsString($aluno->registro_academico, $resposta->getContent());
        $this->assertStringNotContainsString($aluno->email, $resposta->getContent());

        $this->postJson("/api/admin/reservas/{$reserva->id}/devolucao", ['itens' => [
            ['equipamento_id' => $this->bola->id, 'quantidade_devolvida' => 2, 'situacao' => 'DEVOLVIDO'],
        ]])->assertOk();
        $this->getJson('/api/admin/equipamentos/fora-agora')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_fora_agora_nao_faz_uma_consulta_por_item(): void
    {
        $this->comoAdmin();
        $criar = function (int $quantas) {
            for ($i = 0; $i < $quantas; $i++) {
                $reserva = Reserva::factory()->create(['data_reserva' => '2026-11-09']);
                $reserva->equipamentos()->attach(Equipamento::factory()->create()->id, [
                    'quantidade' => 1, 'quantidade_retirada' => 1, 'retirado_em' => now(),
                ]);
            }
        };
        $contar = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/admin/equipamentos/fora-agora')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $criar(2);
        $poucos = $contar();
        $criar(10);

        $this->assertSame($poucos, $contar());
    }

    public function test_aluno_ve_a_retirada_em_minhas_reservas_e_a_exportacao_mostra(): void
    {
        $aluno = Aluno::factory()->create(['email' => 'carla@email.com']);
        $reserva = $this->reservaDeHoje(['aluno_id' => $aluno->id]);
        $this->comoAdmin();
        $this->as('19:05');
        $this->retirar($reserva, [['equipamento_id' => $this->bola->id, 'quantidade_retirada' => 2]])->assertOk();

        $this->postJson('/api/minhas-reservas', ['registro_academico' => $aluno->registro_academico, 'email' => 'carla@email.com', 'periodo' => 'TODAS'])
            ->assertOk()
            ->assertJsonPath('data.0.equipamentos.0.retirada.quantidade', 2)
            ->assertJsonMissingPath('data.0.equipamentos.0.retirada.retirado_por');

        $csv = $this->get('/api/admin/reservas/exportar?de=2026-11-09&ate=2026-11-09&colunas[]=equipamentos')->streamedContent();
        $this->assertStringContainsString('Bola x2 (retirado 2 às 19:05)', $csv);
    }
}
