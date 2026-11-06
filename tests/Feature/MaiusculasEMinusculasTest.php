<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Curso;
use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaiusculasEMinusculasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comoAdmin();
    }

    public function test_nome_de_espaco_unico_sem_diferenciar_maiusculas(): void
    {
        Espaco::factory()->create(['nome' => 'Quadra Poliesportiva']);

        $this->postJson('/api/admin/espacos', ['nome' => 'QUADRA poliesportiva', 'tipo' => 'QUADRA'])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
        $this->postJson('/api/admin/espacos', ['nome' => ' quadra poliesportiva ', 'tipo' => 'QUADRA'])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_edicao_aceita_o_proprio_nome_com_outra_capitalizacao(): void
    {
        $espaco = Espaco::factory()->create(['nome' => 'Sala 1']);
        Espaco::factory()->create(['nome' => 'Sala 2']);

        $this->patchJson("/api/admin/espacos/{$espaco->id}", ['nome' => 'SALA 1'])->assertOk();
        $this->patchJson("/api/admin/espacos/{$espaco->id}", ['nome' => 'sala 2'])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_nome_de_curso_unico_sem_diferenciar_maiusculas(): void
    {
        $curso = Curso::factory()->create(['nome' => 'Enfermagem']);
        Curso::factory()->create(['nome' => 'Medicina']);

        $this->postJson('/api/admin/cursos', ['nome' => 'ENFERMAGEM'])->assertStatus(422)->assertJsonValidationErrors('nome');
        $this->patchJson("/api/admin/cursos/{$curso->id}", ['nome' => 'medicina'])->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_nome_de_equipamento_unico_no_espaco_sem_diferenciar_maiusculas(): void
    {
        $quadra = Espaco::factory()->quadra()->create();
        $laboratorio = Espaco::factory()->laboratorio()->create();
        Equipamento::factory()->for($quadra)->create(['nome' => 'Bola']);
        $rede = Equipamento::factory()->for($quadra)->create(['nome' => 'Rede']);

        $this->postJson('/api/admin/equipamentos', ['espaco_id' => $quadra->id, 'nome' => 'BOLA', 'quantidade_total' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
        $this->postJson('/api/admin/equipamentos', ['espaco_id' => $laboratorio->id, 'nome' => 'BOLA', 'quantidade_total' => 1])
            ->assertCreated();
        $this->patchJson("/api/admin/equipamentos/{$rede->id}", ['nome' => 'bola'])
            ->assertStatus(422)->assertJsonValidationErrors('nome');
    }

    public function test_buscas_nao_diferenciam_maiusculas(): void
    {
        Reserva::factory()->create(['aluno_id' => Aluno::factory()->create(['nome_completo' => 'Maria Souza'])->id]);
        Equipamento::factory()->create(['nome' => 'Projetor']);

        $this->getJson('/api/admin/reservas?busca=MARIA')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/equipamentos?nome=PROJ')->assertOk()->assertJsonCount(1, 'data');
    }
}
