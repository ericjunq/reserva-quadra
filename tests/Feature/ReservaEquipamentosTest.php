<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Espaco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservaEquipamentosTest extends TestCase
{
    use RefreshDatabase;

    private Espaco $quadra;
    private Equipamento $bola;

    protected function setUp(): void
    {
        parent::setUp();

        $this->quadra = Espaco::factory()->quadra(null)->create();
        $this->bola = Equipamento::factory()->for($this->quadra)->create(['nome' => 'Bola', 'quantidade_total' => 3]);
    }

    private function reservar(Espaco $espaco, array $equipamentos, array $extra = [])
    {
        return $this->postJson('/api/reservas', array_merge([
            'registro_academico' => Aluno::factory()->create()->registro_academico,
            'espaco_id'          => $espaco->id,
            'data_reserva'       => '2026-11-10',
            'horario'            => 'PRIMEIRO_HORARIO',
            'equipamentos'       => $equipamentos,
        ], $extra));
    }

    public function test_reserva_sem_equipamentos(): void
    {
        $this->reservar($this->quadra, [])->assertCreated()->assertJsonCount(0, 'data.equipamentos');
    }

    public function test_reserva_com_equipamentos(): void
    {
        $this->reservar($this->quadra, [['id' => $this->bola->id, 'quantidade' => 2]])
            ->assertCreated()
            ->assertJsonPath('data.equipamentos.0.nome', 'Bola')
            ->assertJsonPath('data.equipamentos.0.quantidade', 2);
    }

    public function test_validacoes_dos_itens(): void
    {
        $lab = Espaco::factory()->laboratorio()->create();
        $inativo = Equipamento::factory()->for($this->quadra)->inativo()->create();
        $b = $this->bola->id;

        $this->reservar($lab, [['id' => $b, 'quantidade' => 1]])->assertStatus(422)->assertJsonValidationErrors('equipamentos.0.id');
        $this->reservar($this->quadra, [['id' => $inativo->id, 'quantidade' => 1]])->assertStatus(422);
        $this->reservar($this->quadra, [['id' => $b, 'quantidade' => 1], ['id' => $b, 'quantidade' => 1]])->assertStatus(422);
        $this->reservar($this->quadra, [['id' => $b, 'quantidade' => 0]])->assertStatus(422);
        $this->reservar($this->quadra, [['id' => $b, 'quantidade' => 4]])->assertStatus(422)->assertJsonValidationErrors('equipamentos.0.quantidade');
    }

    public function test_dois_microscopios_e_tres_labs_no_mesmo_horario(): void
    {
        [$labA, $labB, $labC] = Espaco::factory()->laboratorio()->count(3)->create();
        $microscopio = Equipamento::factory()->for($labA)->compartilhavel()->create(['quantidade_total' => 2]);
        $item = [['id' => $microscopio->id, 'quantidade' => 1]];

        $this->reservar($labA, $item)->assertCreated();
        $this->reservar($labB, $item)->assertCreated();
        $this->reservar($labC, $item)
            ->assertStatus(409)
            ->assertJsonPath('dados.equipamentos_indisponiveis.0.disponivel', 0);

        $this->reservar($labC, $item, ['horario' => 'SEGUNDO_HORARIO'])->assertCreated();
        $this->reservar($labC, $item, ['data_reserva' => '2026-11-11'])->assertCreated();
    }

    public function test_revogacao_libera_o_estoque(): void
    {
        Mail::fake();
        $sala = Espaco::factory()->create();
        $microscopio = Equipamento::factory()->compartilhavel()->create(['quantidade_total' => 1]);
        $item = [['id' => $microscopio->id, 'quantidade' => 1]];

        $id = $this->reservar($sala, $item)->assertCreated()->json('data.id');
        $this->reservar($this->quadra, $item)->assertStatus(409);

        $this->comoAdmin();
        $this->postJson("/api/admin/reservas/{$id}/revogar", ['justificativa' => 'Manutenção do microscópio'])->assertOk();

        $this->reservar($this->quadra, $item)->assertCreated();
    }
}
