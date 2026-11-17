<?php

namespace Tests\Feature;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\MovimentacaoEstoque;
use App\Models\Reserva;
use App\Repositories\DisponibilidadeEquipamentoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function movimentar(Equipamento $equipamento, array $dados): TestResponse
    {
        return $this->postJson("/api/admin/equipamentos/{$equipamento->id}/movimentacoes", $dados + ['estado' => 'Conferido no inventário.']);
    }

    public function test_cadastro_do_equipamento_registra_a_entrada_inicial(): void
    {
        $admin = $this->comoAdmin();
        $espaco = Espaco::factory()->create();

        $id = $this->postJson('/api/admin/equipamentos', ['espaco_id' => $espaco->id, 'nome' => 'Projetor', 'quantidade_total' => 3])
            ->assertCreated()->json('data.id');

        $movimentacao = MovimentacaoEstoque::sole();
        $this->assertSame($id, $movimentacao->equipamento_id);
        $this->assertSame('ENTRADA', $movimentacao->tipo->value);
        $this->assertSame(3, $movimentacao->quantidade);
        $this->assertSame($admin->id, $movimentacao->user_id);
        $this->assertSame(3, $movimentacao->quantidade_total_depois);
    }

    public function test_entrada_baixa_manutencao_retorno_e_ajuste_mudam_o_estoque(): void
    {
        $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 5]);

        $this->movimentar($equipamento, ['tipo' => 'ENTRADA', 'quantidade' => 2])->assertCreated()
            ->assertJsonPath('equipamento.quantidade_total', 7);
        $this->movimentar($equipamento, ['tipo' => 'MANUTENCAO', 'quantidade' => 3])->assertCreated()
            ->assertJsonPath('equipamento.quantidade_em_manutencao', 3);
        $this->movimentar($equipamento, ['tipo' => 'RETORNO', 'quantidade' => 1])->assertCreated()
            ->assertJsonPath('equipamento.quantidade_em_manutencao', 2);
        $this->movimentar($equipamento, ['tipo' => 'BAIXA', 'quantidade' => 1, 'da_manutencao' => true])->assertCreated()
            ->assertJsonPath('equipamento.quantidade_total', 6)
            ->assertJsonPath('equipamento.quantidade_em_manutencao', 1);
        $this->movimentar($equipamento, ['tipo' => 'AJUSTE', 'quantidade' => -2])->assertCreated()
            ->assertJsonPath('data.quantidade', -2)
            ->assertJsonPath('data.quantidade_total_depois', 4)
            ->assertJsonPath('data.em_manutencao_depois', 1);

        $this->assertSame(3, $equipamento->fresh()->utilizavel());
    }

    public function test_recusa_quantidades_impossiveis(): void
    {
        $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 2]);

        $this->movimentar($equipamento, ['tipo' => 'RETORNO', 'quantidade' => 1])->assertStatus(422)
            ->assertJsonPath('message', 'Só há 0 unidade(s) em manutenção.');
        $this->movimentar($equipamento, ['tipo' => 'BAIXA', 'quantidade' => 3])->assertStatus(422);
        $this->movimentar($equipamento, ['tipo' => 'MANUTENCAO', 'quantidade' => 3])->assertStatus(422);
        $this->movimentar($equipamento, ['tipo' => 'BAIXA', 'quantidade' => -1])->assertJsonValidationErrors('quantidade');
        $this->movimentar($equipamento, ['tipo' => 'AJUSTE', 'quantidade' => 0])->assertJsonValidationErrors('quantidade');
        $this->postJson("/api/admin/equipamentos/{$equipamento->id}/movimentacoes", ['tipo' => 'BAIXA', 'quantidade' => 1])
            ->assertJsonValidationErrors('estado');
        $this->assertSame(0, MovimentacaoEstoque::count());
    }

    public function test_nao_devolvido_exige_uma_reserva_com_o_equipamento(): void
    {
        $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 3]);
        $reserva = Reserva::factory()->create(['espaco_id' => $equipamento->espaco_id, 'data_reserva' => '2026-11-06']);
        $outra = Reserva::factory()->create(['espaco_id' => $equipamento->espaco_id, 'data_reserva' => '2026-11-06']);
        $reserva->equipamentos()->attach($equipamento->id, ['quantidade' => 1]);

        $this->movimentar($equipamento, ['tipo' => 'NAO_DEVOLVIDO', 'quantidade' => 1])->assertJsonValidationErrors('reserva_id');
        $this->movimentar($equipamento, ['tipo' => 'NAO_DEVOLVIDO', 'quantidade' => 1, 'reserva_id' => $outra->id])
            ->assertStatus(422)->assertJsonPath('message', 'Este equipamento não faz parte da reserva informada.');
        $this->movimentar($equipamento, ['tipo' => 'NAO_DEVOLVIDO', 'quantidade' => 1, 'reserva_id' => $reserva->id])
            ->assertCreated()
            ->assertJsonPath('data.reserva.id', $reserva->id)
            ->assertJsonPath('equipamento.quantidade_total', 2);
    }

    public function test_manutencao_respeita_as_reservas_futuras_mas_nao_as_que_ja_terminaram(): void
    {
        $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 2]);
        $hojeCedo = Reserva::factory()->create([
            'espaco_id' => $equipamento->espaco_id, 'data_reserva' => '2026-11-09', 'hora_inicio' => '07:00:00', 'hora_fim' => '08:00:00',
        ]);
        $hojeCedo->equipamentos()->attach($equipamento->id, ['quantidade' => 2]);
        $amanha = Reserva::factory()->create(['espaco_id' => $equipamento->espaco_id, 'data_reserva' => '2026-11-10']);
        $amanha->equipamentos()->attach($equipamento->id, ['quantidade' => 1]);

        $this->movimentar($equipamento, ['tipo' => 'MANUTENCAO', 'quantidade' => 2])->assertStatus(409);
        $this->movimentar($equipamento, ['tipo' => 'MANUTENCAO', 'quantidade' => 1])->assertCreated();

        $disponivel = app(DisponibilidadeEquipamentoRepository::class)->disponiveis([$equipamento->fresh()], '2026-11-11', '19:00:00', '20:30:00');
        $this->assertSame(1, $disponivel[$equipamento->id]);
    }

    public function test_reserva_nao_passa_do_estoque_utilizavel(): void
    {
        $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 3, 'quantidade_em_manutencao' => 2]);

        $this->getJson("/api/admin/equipamentos/{$equipamento->id}")
            ->assertJsonPath('data.quantidade_em_manutencao', 2);
        $this->getJson("/api/espacos/{$equipamento->espaco_id}/equipamentos")
            ->assertJsonMissingPath('data.0.quantidade_em_manutencao');
    }

    public function test_lista_as_movimentacoes_da_mais_recente_para_a_mais_antiga(): void
    {
        $admin = $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 3]);
        $this->movimentar($equipamento, ['tipo' => 'ENTRADA', 'quantidade' => 1]);
        $this->travel(1)->minutes();
        $this->movimentar($equipamento, ['tipo' => 'MANUTENCAO', 'quantidade' => 1, 'estado' => 'Tela trincada.']);

        $this->getJson("/api/admin/equipamentos/{$equipamento->id}/movimentacoes")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.tipo', 'MANUTENCAO')
            ->assertJsonPath('data.0.estado', 'Tela trincada.')
            ->assertJsonPath('data.0.feito_por.nome', $admin->name)
            ->assertJsonPath('data.1.tipo', 'ENTRADA');
    }
}
