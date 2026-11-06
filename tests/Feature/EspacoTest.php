<?php

namespace Tests\Feature;

use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspacoTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cria_espaco_com_limite_semanal(): void
    {
        $this->comoAdmin();

        $this->postJson('/api/admin/espacos', ['nome' => 'Quadra', 'tipo' => 'QUADRA', 'limite_semanal_por_curso' => 3])
            ->assertCreated()
            ->assertJsonPath('data.limite_semanal_por_curso', 3);

        $this->postJson('/api/admin/espacos', ['nome' => 'Quadra', 'tipo' => 'QUADRA'])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_rotas_de_admin_exigem_login(): void
    {
        $this->postJson('/api/admin/espacos', ['nome' => 'Quadra', 'tipo' => 'QUADRA'])->assertUnauthorized();
    }

    public function test_update_e_so_por_patch(): void
    {
        $this->comoAdmin();
        $espaco = Espaco::factory()->create();

        $this->putJson("/api/admin/espacos/{$espaco->id}", ['nome' => 'Novo'])->assertStatus(405);
        $this->patchJson("/api/admin/espacos/{$espaco->id}", ['nome' => 'Novo'])->assertOk()->assertJsonPath('data.nome', 'Novo');
    }

    public function test_listagem_publica_mostra_ativos_e_filtra_por_tipo(): void
    {
        Espaco::factory()->quadra()->create();
        Espaco::factory()->laboratorio()->count(2)->create();
        Espaco::factory()->laboratorio()->inativo()->create();

        $this->getJson('/api/espacos')->assertJsonCount(3, 'data');
        $this->getJson('/api/espacos?tipo=LABORATORIO')->assertJsonCount(2, 'data');
    }

    public function test_nao_desativa_espaco_com_reserva_futura(): void
    {
        $this->comoAdmin();
        $reserva = Reserva::factory()->create();

        $this->deleteJson("/api/admin/espacos/{$reserva->espaco_id}")->assertStatus(409);
        $this->patchJson("/api/admin/espacos/{$reserva->espaco_id}", ['ativo' => false])->assertStatus(409);
    }

    public function test_desativa_espaco_sem_reservas_futuras(): void
    {
        $this->comoAdmin();
        $espaco = Espaco::factory()->create();

        $this->deleteJson("/api/admin/espacos/{$espaco->id}")->assertNoContent();
        $this->getJson('/api/espacos')->assertJsonCount(0, 'data');
    }
}
