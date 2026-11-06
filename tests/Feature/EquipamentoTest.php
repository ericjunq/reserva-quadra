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

    public function test_listagem_publica_inclui_compartilhaveis_de_outros_espacos(): void
    {
        $sala = Espaco::factory()->create();
        $lab = Espaco::factory()->laboratorio()->create();
        Equipamento::factory()->for($sala)->create();
        Equipamento::factory()->for($lab)->compartilhavel()->create(['nome' => 'Microscópio']);
        Equipamento::factory()->for($lab)->create(['nome' => 'Bancada']);

        $this->getJson("/api/espacos/{$sala->id}/equipamentos")->assertJsonCount(2, 'data');
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
