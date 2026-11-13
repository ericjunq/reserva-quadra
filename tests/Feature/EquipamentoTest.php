<?php

namespace Tests\Feature;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipamentoTest extends TestCase
{
    use RefreshDatabase;

    public function test_nome_e_unico_dentro_do_espaco(): void
    {
        $this->comoAdmin();
        [$quadra, $lab] = Espaco::factory()->count(2)->create();

        $this->postJson('/api/admin/equipamentos', ['espaco_id' => $quadra->id, 'nome' => 'Bola', 'quantidade_total' => 3])->assertCreated();
        $this->postJson('/api/admin/equipamentos', ['espaco_id' => $quadra->id, 'nome' => 'Bola', 'quantidade_total' => 3])->assertStatus(422);
        $this->postJson('/api/admin/equipamentos', ['espaco_id' => $lab->id, 'nome' => 'Bola', 'quantidade_total' => 3])->assertCreated();
    }

    public function test_filtros_da_listagem_do_admin(): void
    {
        $this->comoAdmin();
        $espaco = Espaco::factory()->create();
        Equipamento::factory()->for($espaco)->create(['nome' => 'Bola de vôlei']);
        Equipamento::factory()->for($espaco)->inativo()->create(['nome' => 'Bola de futsal']);
        Equipamento::factory()->create(['nome' => 'Microscópio']);

        $this->getJson('/api/admin/equipamentos')->assertJsonCount(3, 'data');
        $this->getJson('/api/admin/equipamentos?nome=bola')->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/equipamentos?ativo=false')->assertJsonCount(1, 'data');
        $this->getJson("/api/admin/equipamentos?espaco_id={$espaco->id}&ativo=true")->assertJsonCount(1, 'data');
    }

    public function test_listagem_publica_mostra_o_espaco_e_so_os_compartilhados_com_ele(): void
    {
        $sala = Espaco::factory()->create();
        $quadra = Espaco::factory()->create(['tipo' => 'QUADRA']);
        $lab = Espaco::factory()->laboratorio()->create();
        Equipamento::factory()->for($sala)->create(['nome' => 'Quadro branco']);
        Equipamento::factory()->for($lab)->compartilhadoCom($sala)->create(['nome' => 'Microscópio']);
        Equipamento::factory()->for($lab)->create(['nome' => 'Bancada']);
        Equipamento::factory()->for($quadra)->create(['nome' => 'Bola']);

        $nomes = fn (Espaco $espaco) => collect($this->getJson("/api/espacos/{$espaco->id}/equipamentos")->assertOk()->json('data'))->pluck('nome')->all();

        $this->assertSame(['Microscópio', 'Quadro branco'], $nomes($sala));
        $this->assertSame(['Bola'], $nomes($quadra));
        $this->assertSame(['Bancada', 'Microscópio'], $nomes($lab));
        $this->assertArrayNotHasKey('espacos_compartilhados', $this->getJson("/api/espacos/{$sala->id}/equipamentos")->json('data.0'));
    }

    public function test_admin_escolhe_com_quais_espacos_o_equipamento_e_compartilhado(): void
    {
        $this->comoAdmin();
        $lab = Espaco::factory()->laboratorio()->create();
        [$salaA, $salaB] = Espaco::factory()->count(2)->create();

        $id = $this->postJson('/api/admin/equipamentos', [
            'espaco_id'              => $lab->id,
            'nome'                   => 'Projetor',
            'quantidade_total'       => 2,
            'espacos_compartilhados' => [$salaA->id],
        ])->assertCreated()
            ->assertJsonPath('data.espacos_compartilhados', [['id' => $salaA->id, 'nome' => $salaA->nome]])
            ->json('data.id');

        $this->patchJson("/api/admin/equipamentos/{$id}", ['espacos_compartilhados' => [$salaB->id, $salaA->id]])->assertOk()
            ->assertJsonCount(2, 'data.espacos_compartilhados');
        $this->getJson("/api/espacos/{$salaB->id}/equipamentos")->assertJsonPath('data.0.nome', 'Projetor');

        $this->patchJson("/api/admin/equipamentos/{$id}", ['espacos_compartilhados' => []])->assertOk()
            ->assertJsonPath('data.espacos_compartilhados', []);
        $this->getJson("/api/espacos/{$salaB->id}/equipamentos")->assertJsonCount(0, 'data');

        $historico = Equipamento::find($id)->auditorias()->where('acao', 'ATUALIZACAO')->orderBy('id')->get();
        $this->assertCount(3, $historico);
        $this->assertSame(['espacos_compartilhados' => []], $historico[0]->antes);
        $this->assertSame(['espacos_compartilhados' => [$salaA->id]], $historico[1]->antes);
        $this->assertSame(['espacos_compartilhados' => collect([$salaA->id, $salaB->id])->sort()->values()->all()], $historico[1]->depois);
        $this->assertSame(['espacos_compartilhados' => []], $historico[2]->depois);

        $this->getJson('/api/admin/equipamentos')->assertOk()->assertJsonPath('data.0.espacos_compartilhados', []);
    }

    public function test_validacoes_do_compartilhamento(): void
    {
        $this->comoAdmin();
        $lab = Espaco::factory()->laboratorio()->create();
        $sala = Espaco::factory()->create();
        $equipamento = Equipamento::factory()->for($lab)->create();

        $this->patchJson("/api/admin/equipamentos/{$equipamento->id}", ['espacos_compartilhados' => [$lab->id]])
            ->assertStatus(422)
            ->assertJsonPath('errors', ['espacos_compartilhados.0' => ['O equipamento já pertence a este espaço.']]);
        $this->patchJson("/api/admin/equipamentos/{$equipamento->id}", ['espacos_compartilhados' => [999]])
            ->assertStatus(422)->assertJsonValidationErrors('espacos_compartilhados.0');
        $this->patchJson("/api/admin/equipamentos/{$equipamento->id}", ['espacos_compartilhados' => [$sala->id, $sala->id]])
            ->assertStatus(422)->assertJsonValidationErrors('espacos_compartilhados.0');
        $this->patchJson("/api/admin/equipamentos/{$equipamento->id}", ['espacos_compartilhados' => 'todos'])
            ->assertStatus(422)->assertJsonValidationErrors('espacos_compartilhados');
    }

    public function test_nao_deixa_de_compartilhar_com_espaco_que_tem_reserva_futura_do_item(): void
    {
        $this->comoAdmin();
        $lab = Espaco::factory()->laboratorio()->create();
        [$salaA, $salaB] = Espaco::factory()->count(2)->create(['nome' => fn () => fake()->unique()->bothify('Sala ###')]);
        $projetor = Equipamento::factory()->for($lab)->compartilhadoCom($salaA, $salaB)->create();
        Reserva::factory()->create(['espaco_id' => $salaA->id, 'data_reserva' => '2026-11-12'])
            ->equipamentos()->attach($projetor->id, ['quantidade' => 1]);
        Reserva::factory()->create(['espaco_id' => $salaB->id, 'data_reserva' => '2026-11-02'])
            ->equipamentos()->attach($projetor->id, ['quantidade' => 1]);

        $this->patchJson("/api/admin/equipamentos/{$projetor->id}", ['espacos_compartilhados' => []])
            ->assertStatus(409)
            ->assertJsonPath('dados.espacos', [$salaA->id])
            ->assertJsonPath('message', "O equipamento tem reservas futuras em {$salaA->nome}. Ele só pode deixar de ser compartilhado com esses espaços depois delas.");

        $this->patchJson("/api/admin/equipamentos/{$projetor->id}", ['espacos_compartilhados' => [$salaA->id]])->assertOk();
    }

    public function test_listagem_publica_de_espaco_inativo_da_404(): void
    {
        $espaco = Espaco::factory()->inativo()->create();

        $this->getJson("/api/espacos/{$espaco->id}/equipamentos")->assertNotFound();
    }

    public function test_nao_reduz_estoque_abaixo_do_reservado(): void
    {
        $this->comoAdmin();
        $equipamento = Equipamento::factory()->create(['quantidade_total' => 5]);
        $reserva = Reserva::factory()->create(['espaco_id' => $equipamento->espaco_id]);
        $reserva->equipamentos()->attach($equipamento->id, ['quantidade' => 4]);

        $this->patchJson("/api/admin/equipamentos/{$equipamento->id}", ['quantidade_total' => 3])->assertStatus(409);
        $this->patchJson("/api/admin/equipamentos/{$equipamento->id}", ['quantidade_total' => 4])->assertOk();
    }
}
