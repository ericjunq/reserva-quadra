<?php

namespace Tests\Feature;

use App\Models\Aluno;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'nome_completo'      => 'Ana Souza',
            'registro_academico' => '2024000001',
            'email'              => 'ana@exemplo.com',
            'curso_id'           => Curso::factory()->create(['nome' => 'TI'])->id,
        ], $extra);
    }

    public function test_aluno_se_cadastra_com_curso(): void
    {
        $this->postJson('/api/alunos', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.registro_academico', '2024000001')
            ->assertJsonPath('data.curso.nome', 'TI');
    }

    public function test_ra_duplicado_e_recusado(): void
    {
        $aluno = Aluno::factory()->create();

        $this->postJson('/api/alunos', $this->payload(['registro_academico' => $aluno->registro_academico]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('registro_academico');
    }

    public function test_curso_inativo_e_email_invalido_sao_recusados(): void
    {
        $this->postJson('/api/alunos', $this->payload([
            'curso_id' => Curso::factory()->inativo()->create()->id,
            'email'    => 'nao-e-email',
        ]))->assertStatus(422)->assertJsonValidationErrors(['curso_id', 'email']);
    }

    public function test_listagem_de_alunos_e_so_para_admin(): void
    {
        Aluno::factory()->count(3)->create();

        $this->getJson('/api/admin/alunos')->assertUnauthorized();

        $this->comoAdmin();
        $this->getJson('/api/admin/alunos')->assertOk()->assertJsonCount(3, 'data');
    }
}
