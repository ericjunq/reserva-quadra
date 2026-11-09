<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Equipamento;
use App\Models\Reserva;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinhasReservasTest extends TestCase
{
    use RefreshDatabase;

    private Aluno $aluno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aluno = Aluno::factory()->create(['registro_academico' => '2024000001', 'email' => 'maria@email.com']);
    }

    private function consultar(array $dados = [])
    {
        return $this->postJson('/api/minhas-reservas', [
            'registro_academico' => '2024000001',
            'email'              => 'maria@email.com',
            ...$dados,
        ]);
    }

    private function ids($resposta): array
    {
        return collect($resposta->json('data'))->pluck('id')->all();
    }

    public function test_mostra_as_reservas_futuras_do_aluno_por_padrao(): void
    {
        $passada = Reserva::factory()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-06']);
        $hoje = Reserva::factory()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-09']);
        $depois = Reserva::factory()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-12']);
        $revogada = Reserva::factory()->revogada()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-10']);
        Reserva::factory()->create(['data_reserva' => '2026-11-10']);

        $this->assertSame([$hoje->id, $revogada->id, $depois->id], $this->ids($this->consultar()->assertOk()));
        $this->assertSame([$depois->id, $revogada->id, $hoje->id, $passada->id], $this->ids($this->consultar(['periodo' => 'TODAS'])));
    }

    public function test_traz_espaco_equipamentos_e_justificativa(): void
    {
        $reserva = Reserva::factory()->revogada()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-10']);
        $reserva->equipamentos()->attach(Equipamento::factory()->create(['nome' => 'Bola'])->id, ['quantidade' => 2]);

        $this->consultar()->assertOk()
            ->assertJsonPath('data.0.espaco.id', $reserva->espaco_id)
            ->assertJsonPath('data.0.equipamentos.0.nome', 'Bola')
            ->assertJsonPath('data.0.equipamentos.0.devolucao.situacao', 'PENDENTE')
            ->assertJsonPath('data.0.status', 'REVOGADO')
            ->assertJsonPath('data.0.justificativa_revogacao', 'Revogada para teste');
    }

    public function test_nao_expoe_dados_pessoais_nem_auditoria(): void
    {
        Reserva::factory()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-10']);

        $resposta = $this->consultar()->assertOk()
            ->assertJsonMissingPath('data.0.aluno')
            ->assertJsonMissingPath('data.0.criado_por')
            ->assertJsonMissingPath('data.0.revogada_por');

        $this->assertStringNotContainsString('2024000001', $resposta->getContent());
        $this->assertStringNotContainsString('maria@email.com', $resposta->getContent());
    }

    public function test_email_sem_diferenciar_maiusculas(): void
    {
        Reserva::factory()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-10']);

        $this->consultar(['email' => 'MARIA@Email.com'])->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_dados_errados_recebem_sempre_a_mesma_resposta(): void
    {
        $outro = Aluno::factory()->create(['email' => 'joao@email.com']);

        $respostas = [
            $this->consultar(['email' => 'outra@email.com']),
            $this->consultar(['registro_academico' => '9999999999']),
            $this->consultar(['email' => 'joao@email.com']),
            $this->consultar(['registro_academico' => $outro->registro_academico]),
        ];

        foreach ($respostas as $resposta) {
            $resposta->assertNotFound()->assertExactJson(['message' => 'Não encontramos reservas para esses dados.']);
        }
    }

    public function test_aluno_inativo_consulta_e_anonimizado_nao(): void
    {
        Reserva::factory()->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-10']);
        $this->aluno->update(['ativo' => false]);

        $this->consultar()->assertOk()->assertJsonCount(1, 'data');

        $this->aluno->update(['anonimizado_em' => now()]);

        $this->consultar()->assertNotFound();
    }

    public function test_dados_vao_no_corpo_e_nao_na_url(): void
    {
        $this->getJson('/api/minhas-reservas?registro_academico=2024000001&email=maria@email.com')->assertStatus(405);
    }

    public function test_validacoes(): void
    {
        $this->postJson('/api/minhas-reservas', [])->assertStatus(422)->assertJsonValidationErrors(['registro_academico', 'email']);
        $this->consultar(['email' => 'invalido'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->consultar(['periodo' => 'ONTEM'])->assertStatus(422)->assertJsonValidationErrors('periodo');
    }

    public function test_paginacao(): void
    {
        Reserva::factory()->count(3)->create(['aluno_id' => $this->aluno->id, 'data_reserva' => '2026-11-10']);

        $this->postJson('/api/minhas-reservas?per_page=2', [
            'registro_academico' => '2024000001',
            'email'              => 'maria@email.com',
        ])->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 3);
    }
}
